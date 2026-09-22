<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Services\Auth\CustomerAuthService;
use Tests\Support\CatalogFactory;

/**
 * The two ceilings the shop set for launch, and the switch that takes a
 * payment method off the storefront.
 */

beforeEach(function () {
    fakePublicDisk();

    $this->customer = Customer::create(['name' => 'أحمد علي', 'phone' => '0599123456']);
    $this->headers  = ['Authorization' => app(CustomerAuthService::class)->issueToken($this->customer)];

    // 12 ₪ a unit, ordered two at a time: a 24 ₪ cart.
    $this->product = CatalogFactory::flatList('milkshake', ['name' => 'ميلك شيك']);
    CatalogFactory::item($this->product, 'vanilla', ['label' => 'فانيلا', 'price' => 12]);
});

function limitsPayload(array $overrides = []): array
{
    $items = [[
        'productId'  => test()->product->id,
        'name'       => 'ميلك شيك',
        'selections' => [['kind' => 'item', 'id' => 'vanilla', 'label' => 'فانيلا', 'qty' => 1]],
        'quantity'   => 2,
    ]];

    return array_merge([
        'items'          => json_encode($items),
        'paymentMethod'  => 'cash',
        'deliveryMethod' => 'pickup',
    ], $overrides);
}

// ─── change from a cash payment ─────────────────────────────────────────────

it('takes a cash payment whose change is exactly the ceiling', function () {
    // 24 ₪ cart, 223 handed over: 199 change.
    test()->post('/api/orders', limitsPayload(['paidAmount' => 223]), $this->headers)->assertCreated();

    expect((float) Order::sole()->tendered_amount)->toBe(223.0);
});

it('refuses a cash payment whose change is over the ceiling', function () {
    test()->post('/api/orders', limitsPayload(['paidAmount' => 224]), $this->headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors('paidAmount');

    expect(Order::count())->toBe(0);
});

it('reads the ceiling from the settings rather than a number in the code', function () {
    config(['storefront.limits.max_change' => 10]);

    test()->post('/api/orders', limitsPayload(['paidAmount' => 35]), $this->headers)->assertStatus(422);
    test()->post('/api/orders', limitsPayload(['paidAmount' => 34]), $this->headers)->assertCreated();
});

// ─── topping up a wallet ────────────────────────────────────────────────────

it('accepts a top-up request up to the ceiling', function () {
    test()->postJson('/api/wallet/topup-requests', [
        'amount'            => 500,
        'method'            => 'bop',
        'receiptNote'       => 'حوّلت',
        'senderAccountName' => 'أحمد علي',
    ], $this->headers)->assertCreated();
});

it('refuses a top-up request over the ceiling', function () {
    test()->postJson('/api/wallet/topup-requests', [
        'amount'            => 501,
        'method'            => 'bop',
        'receiptNote'       => 'حوّلت',
        'senderAccountName' => 'أحمد علي',
    ], $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.amount.0', 'أقصى مبلغ للشحن هو 500 ₪');
});

// ─── showing and hiding payment options ─────────────────────────────────────

it('tells the storefront which payment options to show', function () {
    $methods = test()->getJson('/api/store/status')->assertOk()->json('paymentMethods');

    expect($methods)->toHaveKeys(Order::PAYMENT_METHODS)
        ->and($methods['cash'])->toBeTrue()
        // Automatic Jawwal Pay ships off: it charges a real wallet, and the
        // shop turns it on once the gateway is verified against production.
        ->and($methods['jawwal'])->toBeFalse();
});

it('refuses an order on a payment method the shop switched off', function () {
    PaymentAccount::where('method', 'cash')->update(['active' => false]);

    test()->getJson('/api/store/status')->assertJsonPath('paymentMethods.cash', false);

    test()->post('/api/orders', limitsPayload(), $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.paymentMethod.0', 'طريقة الدفع غير متاحة حالياً');

    expect(Order::count())->toBe(0);
});

it('lists the counter methods that are on, without account details', function () {
    // The storefront builds its options from this list, so cash and card are
    // in it — but a card reader has no account number to show.
    $accounts = collect(test()->getJson('/api/payment-accounts')->assertOk()->json())->keyBy('method');

    expect($accounts)->toHaveKeys(['cash', 'visa'])
        ->and($accounts['cash']['type'])->toBe('counter')
        ->and($accounts['cash'])->not->toHaveKeys(['holderName', 'primaryLabel', 'primaryValue']);
});

it('leaves a method with no account row available', function () {
    // The wallet is not an account the shop is paid into, and a method nobody
    // has ever configured behaves as it did before there was a switch.
    PaymentAccount::where('method', 'cash')->delete();

    test()->getJson('/api/store/status')->assertJsonPath('paymentMethods.cash', true);
    test()->post('/api/orders', limitsPayload(), $this->headers)->assertCreated();
});

it('puts a switched-off method back on when the shop switches it on', function () {
    PaymentAccount::where('method', 'cash')->update(['active' => false]);
    test()->post('/api/orders', limitsPayload(), $this->headers)->assertStatus(422);

    PaymentAccount::where('method', 'cash')->update(['active' => true]);
    test()->post('/api/orders', limitsPayload(), $this->headers)->assertCreated();
});
