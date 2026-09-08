<?php

use App\Models\Customer;
use App\Models\Order;
use App\Services\Auth\CustomerAuthService;
use App\Services\Checkout\Money;
use App\Services\Storefront\WalletService;
use Tests\Support\CatalogFactory;

/**
 * Paying from the wallet, along the exact path the storefront walks:
 * POST /wallet/deduct, then POST /orders.
 *
 * A customer lost credit to this in production — the deduct went through, the
 * order did not, and nobody could put it back without a human. These pin down
 * that money only ever moves attached to an order.
 */

beforeEach(function () {
    fakePublicDisk();

    $this->customer = Customer::create(['name' => 'مصطفى', 'phone' => '0595796456']);
    $this->headers  = ['Authorization' => app(CustomerAuthService::class)->issueToken($this->customer)];

    app(WalletService::class)->credit($this->customer, Money::toAgorot(100), 'شحن');

    $this->product = CatalogFactory::flatList('loqaimat', ['name' => 'لقيمات']);
    CatalogFactory::item($this->product, 'nutella', ['label' => 'نوتيلا', 'price' => 25]);
});

function walletBalance(): float
{
    return app(WalletService::class)->walletFor(test()->customer)->fresh()->balance;
}

function walletOrder(array $overrides = [])
{
    return test()->post('/api/orders', array_merge([
        'items' => json_encode([[
            'productId' => test()->product->id,
            'itemId'    => 'nutella',
            'quantity'  => 1,
        ]]),
        'paymentMethod'  => 'wallet',
        'deliveryMethod' => 'pickup',
    ], $overrides), test()->headers);
}

it('charges exactly once along the storefront path', function () {
    // Step 1, as the storefront does it.
    test()->postJson('/api/wallet/deduct', ['amount' => 25], $this->headers)->assertOk();

    // Nothing has moved yet: a check is not a payment.
    expect(walletBalance())->toBe(100.0);

    // Step 2.
    walletOrder()->assertCreated();

    // 25 once, not 50.
    expect(walletBalance())->toBe(75.0);
});

it('takes nothing when the order fails after the balance was checked', function () {
    test()->postJson('/api/wallet/deduct', ['amount' => 25], $this->headers)->assertOk();

    // The order is refused — an unknown item.
    walletOrder(['items' => json_encode([[
        'productId' => $this->product->id,
        'itemId'    => 'does-not-exist',
        'quantity'  => 1,
    ]])])->assertStatus(422);

    // This is the exact failure that cost a real customer their credit.
    expect(walletBalance())->toBe(100.0)
        ->and(Order::count())->toBe(0);
});

it('refuses to spend credit with no order attached', function () {
    test()->postJson('/api/wallet/deduct', ['amount' => 40], $this->headers)->assertOk();
    test()->postJson('/api/wallet/deduct', ['amount' => 40], $this->headers)->assertOk();
    test()->postJson('/api/wallet/deduct', ['amount' => 40], $this->headers)->assertOk();

    // Three calls, no purchase, no movement.
    expect(walletBalance())->toBe(100.0);
});

it('still says 409 when the balance will not cover the order', function () {
    test()->postJson('/api/wallet/deduct', ['amount' => 500], $this->headers)
        ->assertStatus(409);
});

it('rolls the debit back when the order write fails', function () {
    // The balance is decided under a row lock at order time, not by the
    // advisory check: that is the one that has to hold.
    walletOrder(['items' => json_encode([[
        'productId' => $this->product->id,
        'itemId'    => 'nutella',
        'quantity'  => 50,
    ]])]);

    // 50 × 25 = 1250, well over 100 — refused, and the balance is untouched.
    expect(walletBalance())->toBe(100.0)
        ->and(Order::count())->toBe(0);
});

it('settles a wallet order the moment it is placed', function () {
    $response = walletOrder()->assertCreated();

    $order = Order::where('reference', $response->json('reference'))->firstOrFail();

    // The credit is money the shop already holds; there is nothing to collect.
    expect($order->isPaid())->toBeTrue()
        ->and($order->paid_at)->not->toBeNull();
});
