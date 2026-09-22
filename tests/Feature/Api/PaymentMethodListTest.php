<?php

use App\Filament\Resources\PaymentAccountResource;
use App\Filament\Resources\TopUpRequestResource;
use App\Models\Customer;
use App\Models\PaymentAccount;
use App\Models\TopUpRequest;
use App\Models\User;
use App\Services\Auth\CustomerAuthService;
use Livewire\Livewire;

/**
 * GET /payment-accounts as the storefront's list of payment options, the name
 * the dashboard gives each one, and the reason a top-up was refused.
 */

beforeEach(function () {
    $this->customer = Customer::create(['name' => 'يوسف', 'phone' => '0599123456']);
    $this->headers  = ['Authorization' => app(CustomerAuthService::class)->issueToken($this->customer)];

    PaymentAccount::updateOrCreate(['method' => 'palpay'], [
        'holder_name' => 'جلاسيه الأمير', 'primary_label' => 'رقم المحفظة', 'primary_value' => '0599999978',
        'active' => true,
    ]);
});

function listedMethods(): array
{
    return array_column(test()->getJson('/api/payment-accounts')->assertOk()->json(), 'method');
}

// ─── only what is switched on ───────────────────────────────────────────────

it('drops card from the list when the shop switches it off', function () {
    expect(listedMethods())->toContain('visa');

    PaymentAccount::where('method', 'visa')->update(['active' => false]);

    expect(listedMethods())->not->toContain('visa');
});

it('drops cash from the list when the shop switches it off', function () {
    PaymentAccount::where('method', 'cash')->update(['active' => false]);

    expect(listedMethods())->not->toContain('cash');
});

it('drops a transfer method when it is switched off, and refuses a top-up on it', function () {
    PaymentAccount::where('method', 'palpay')->update(['active' => false]);

    expect(listedMethods())->not->toContain('palpay');

    test()->postJson('/api/wallet/topup-requests', [
        'amount' => 50, 'method' => 'palpay', 'receiptNote' => 'حوّلت', 'senderAccountName' => 'يوسف',
    ], $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.method.0', 'طريقة الشحن غير متاحة حالياً');
});

it('lists automatic Jawwal Pay once the shop switches it on', function () {
    // Ships off until the gateway is verified against production.
    expect(listedMethods())->not->toContain('jawwal');

    PaymentAccount::where('method', 'jawwal')->update(['active' => true]);

    $jawwal = collect(test()->getJson('/api/payment-accounts')->json())->firstWhere('method', 'jawwal');

    expect($jawwal['type'])->toBe('gateway');
});

// ─── the name the customer sees ─────────────────────────────────────────────

it('sends the name set in the dashboard', function () {
    PaymentAccount::where('method', 'palpay')->update(['display_name' => 'بال باي (تحويل فوري)']);

    $palpay = collect(test()->getJson('/api/payment-accounts')->json())->firstWhere('method', 'palpay');

    expect($palpay['displayName'])->toBe('بال باي (تحويل فوري)');
});

it('leaves displayName out when none is set, so the storefront uses its own label', function () {
    $palpay = collect(test()->getJson('/api/payment-accounts')->json())->firstWhere('method', 'palpay');

    expect($palpay)->not->toHaveKey('displayName');
});

it('sets the name from the dashboard form', function () {
    $this->actingAs(User::factory()->create());
    $account = PaymentAccount::where('method', 'cash')->sole();

    Livewire::test(PaymentAccountResource\Pages\EditPaymentAccount::class, ['record' => $account->getKey()])
        ->fillForm(['display_name' => 'كاش عند الاستلام'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($account->fresh()->display_name)->toBe('كاش عند الاستلام');
});

// ─── why a top-up was refused ───────────────────────────────────────────────

it('tells the customer the reason their top-up was rejected', function () {
    $request = $this->customer->topUpRequests()->create([
        'amount' => 500, 'method' => 'palpay', 'status' => TopUpRequest::STATUS_PENDING, 'receipt_note' => 'حوّلت',
    ]);

    $this->actingAs(User::factory()->create());

    Livewire::test(TopUpRequestResource\Pages\ListTopUpRequests::class)
        ->callTableAction('reject', $request, ['note' => 'الصورة غير واضحة، يرجى رفع صورة أوضح للإشعار']);

    test()->getJson('/api/wallet/topup-requests', $this->headers)
        ->assertOk()
        ->assertJsonPath('requests.0.status', 'مرفوض')
        ->assertJsonPath('requests.0.rejectionReason', 'الصورة غير واضحة، يرجى رفع صورة أوضح للإشعار');
});

it('sends no rejection reason on a request that was not rejected', function () {
    $this->customer->topUpRequests()->create([
        'amount' => 50, 'method' => 'palpay', 'status' => TopUpRequest::STATUS_APPROVED,
        'receipt_note' => 'حوّلت', 'review_note' => 'ملاحظة داخلية للإدارة',
    ]);

    expect(test()->getJson('/api/wallet/topup-requests', $this->headers)->json('requests.0'))
        ->not->toHaveKey('rejectionReason');
});
