<?php

use App\Models\Customer;
use App\Models\TopUpRequest;
use App\Models\User;
use App\Services\Auth\CustomerAuthService;
use App\Services\Checkout\Money;
use App\Services\Storefront\WalletService;
use Illuminate\Http\UploadedFile;

/**
 * Store credit (handoff 14).
 */

beforeEach(function () {
    fakePublicDisk();

    $this->customer = Customer::create(['name' => 'أحمد علي', 'phone' => '0599123456']);
    $this->headers  = ['Authorization' => app(CustomerAuthService::class)->issueToken($this->customer)];
    $this->wallet   = app(WalletService::class);
});

// ─── reading ────────────────────────────────────────────────────────────────

it('starts a customer at zero without needing a row up front', function () {
    test()->getJson('/api/wallet', $this->headers)
        ->assertOk()
        ->assertJsonPath('balance', 0)
        ->assertJsonPath('transactions', []);
});

it('lists the statement newest first', function () {
    $this->wallet->credit($this->customer, Money::toAgorot(50), 'شحن رصيد', 'bop');
    $this->wallet->debit($this->customer, Money::toAgorot(20), 'دفع طلب');

    $response = test()->getJson('/api/wallet', $this->headers)->assertOk();

    $response->assertJsonPath('balance', 30)
        ->assertJsonPath('transactions.0.type', 'debit')
        ->assertJsonPath('transactions.0.amount', 20)
        ->assertJsonPath('transactions.1.type', 'credit')
        ->assertJsonPath('transactions.1.method', 'bop');
});

it('keeps one customer wallet away from another', function () {
    $this->wallet->credit($this->customer, Money::toAgorot(50), 'شحن');

    $other   = Customer::create(['name' => 'آخر', 'phone' => '0598000000']);
    $headers = ['Authorization' => app(CustomerAuthService::class)->issueToken($other)];

    test()->getJson('/api/wallet', $headers)->assertOk()->assertJsonPath('balance', 0);
});

it('requires a token everywhere in the wallet', function () {
    test()->getJson('/api/wallet')->assertStatus(401);
    test()->postJson('/api/wallet/deduct', ['amount' => 5])->assertStatus(401);
    test()->getJson('/api/wallet/topup-requests')->assertStatus(401);
    test()->postJson('/api/wallet/topup-requests', ['amount' => 5, 'method' => 'bop'])->assertStatus(401);
});

// ─── spending ───────────────────────────────────────────────────────────────

it('deducts from the balance', function () {
    $this->wallet->credit($this->customer, Money::toAgorot(100), 'شحن');

    test()->postJson('/api/wallet/deduct', ['amount' => 22.5, 'label' => 'دفع طلب #ORD-M3K2'], $this->headers)
        ->assertOk()
        ->assertJsonPath('balance', 77.5);
});

it('refuses a deduction the balance cannot cover', function () {
    $this->wallet->credit($this->customer, Money::toAgorot(10), 'شحن');

    // 409, and decided here — never by the frontend's copy of the balance.
    test()->postJson('/api/wallet/deduct', ['amount' => 50], $this->headers)
        ->assertStatus(409)
        ->assertJson(['message' => 'الرصيد غير كافٍ']);

    expect($this->customer->wallet->fresh()->balance)->toBe(10.0);
});

it('will not take a negative amount as a deposit', function () {
    test()->postJson('/api/wallet/deduct', ['amount' => -50], $this->headers)->assertStatus(422);
});

// ─── topping up ─────────────────────────────────────────────────────────────

it('logs a top-up request without adding a shekel', function () {
    test()->post('/api/wallet/topup-requests', [
        'amount'       => 50,
        'method'       => 'bop',
        'receiptImage' => UploadedFile::fake()->image('receipt.png'),
    ], $this->headers)
        ->assertCreated()
        ->assertJsonPath('request.status', 'قيد المراجعة')
        ->assertJsonPath('request.amount', 50);

    // The whole point: submitting is not approving.
    expect((float) ($this->customer->wallet()->first()?->balance ?? 0))->toBe(0.0);
});

it('accepts a note when the customer could not upload a receipt', function () {
    test()->postJson('/api/wallet/topup-requests', [
        'amount'      => 50,
        'method'      => 'jawwal-manual',
        'receiptNote' => 'حوّلت من رقم 0599000000',
    ], $this->headers)->assertCreated();
});

it('insists on some evidence of the transfer', function () {
    test()->postJson('/api/wallet/topup-requests', ['amount' => 50, 'method' => 'bop'], $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.receiptNote.0', 'أرفق صورة الإيصال أو اكتب ملاحظة توضح التحويل');
});

it('refuses an unsupported top-up method', function () {
    test()->postJson('/api/wallet/topup-requests', [
        'amount' => 50, 'method' => 'bitcoin', 'receiptNote' => 'x',
    ], $this->headers)->assertStatus(422);
});

it('refuses a receipt that is not an image', function () {
    test()->post('/api/wallet/topup-requests', [
        'amount'       => 50,
        'method'       => 'bop',
        'receiptImage' => UploadedFile::fake()->create('x.php', 5, 'application/x-php'),
    ], $this->headers)->assertStatus(422);
});

it('shows a customer only their own top-up requests', function () {
    test()->postJson('/api/wallet/topup-requests', [
        'amount' => 50, 'method' => 'bop', 'receiptNote' => 'x',
    ], $this->headers)->assertCreated();

    $other   = Customer::create(['name' => 'آخر', 'phone' => '0598000000']);
    $headers = ['Authorization' => app(CustomerAuthService::class)->issueToken($other)];

    test()->getJson('/api/wallet/topup-requests', $headers)->assertOk()->assertJsonCount(0, 'requests');
    test()->getJson('/api/wallet/topup-requests', $this->headers)->assertOk()->assertJsonCount(1, 'requests');
});

// ─── approval is an admin act ───────────────────────────────────────────────

it('exposes no route by which a customer can approve their own top-up', function () {
    $request = $this->wallet->requestTopUp($this->customer, [
        'amount' => 50, 'method' => 'bop', 'receiptNote' => 'x',
    ]);

    // The frontend used to do this from the browser console. Every plausible
    // shape of that call must simply not exist.
    foreach ([
        "/api/wallet/topup-requests/{$request->id}/approve",
        "/api/wallet/topup-requests/{$request->id}",
        '/api/wallet/credit',
        '/api/wallet/topup',
    ] as $url) {
        expect(test()->postJson($url, ['status' => 'مكتمل'], $this->headers)->status())
            ->toBeIn([404, 405]);
    }

    expect($request->fresh()->status)->toBe(TopUpRequest::STATUS_PENDING)
        ->and((float) ($this->customer->wallet()->first()?->balance ?? 0))->toBe(0.0);
});

it('credits the balance when an admin approves, and mirrors the receipt', function () {
    $request = $this->wallet->requestTopUp($this->customer, [
        'amount' => 50, 'method' => 'bop', 'receiptImage' => 'receipts/proof.png',
    ]);

    $this->wallet->approveTopUp($request, User::factory()->create());

    expect($this->customer->wallet->fresh()->balance)->toBe(50.0);

    // The statement shows the same proof that was reviewed (handoff 14).
    $transaction = $this->customer->wallet->transactions()->first();

    expect($transaction->type)->toBe('credit')
        ->and($transaction->method)->toBe('bop')
        ->and($transaction->receipt_image)->toBe('receipts/proof.png')
        ->and($request->fresh()->status)->toBe(TopUpRequest::STATUS_APPROVED);
});

it('does not pay twice when approve is clicked twice', function () {
    $request = $this->wallet->requestTopUp($this->customer, [
        'amount' => 50, 'method' => 'bop', 'receiptNote' => 'x',
    ]);

    $this->wallet->approveTopUp($request);
    $this->wallet->approveTopUp($request->fresh());
    $this->wallet->approveTopUp($request->fresh());

    expect($this->customer->wallet->fresh()->balance)->toBe(50.0)
        ->and($this->customer->wallet->transactions()->count())->toBe(1);
});

it('adds nothing when a top-up is rejected', function () {
    $request = $this->wallet->requestTopUp($this->customer, [
        'amount' => 50, 'method' => 'bop', 'receiptNote' => 'x',
    ]);

    $this->wallet->rejectTopUp($request, User::factory()->create(), 'الإيصال غير واضح');

    expect($request->fresh()->status)->toBe(TopUpRequest::STATUS_REJECTED)
        ->and((float) ($this->customer->wallet()->first()?->balance ?? 0))->toBe(0.0);
});

it('will not credit a request that was already rejected and then approved by mistake', function () {
    $request = $this->wallet->requestTopUp($this->customer, [
        'amount' => 50, 'method' => 'bop', 'receiptNote' => 'x',
    ]);

    $this->wallet->approveTopUp($request);
    $this->wallet->rejectTopUp($request->fresh());

    // Rejecting after crediting must not claw the money back silently, and
    // must not let a second approval add it again.
    expect($request->fresh()->status)->toBe(TopUpRequest::STATUS_APPROVED)
        ->and($this->customer->wallet->fresh()->balance)->toBe(50.0);
});

// ─── the ledger ─────────────────────────────────────────────────────────────

it('records the balance after every movement', function () {
    $this->wallet->credit($this->customer, Money::toAgorot(100), 'شحن');
    $this->wallet->debit($this->customer, Money::toAgorot(30), 'دفع');
    $this->wallet->credit($this->customer, Money::toAgorot(10), 'استرداد');

    $running = $this->customer->wallet->transactions()
        ->reorder('created_at')->orderBy('id')->pluck('balance_after')->all();

    expect(array_map('floatval', $running))->toBe([100.0, 70.0, 80.0])
        ->and($this->customer->wallet->fresh()->balance)->toBe(80.0);
});
