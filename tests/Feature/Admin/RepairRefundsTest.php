<?php

use App\Models\Customer;
use App\Models\Order;
use App\Services\Checkout\Money;
use App\Services\Storefront\WalletService;

/**
 * Repairing rows written by the old refund path, which changed the status and
 * nothing else. Fixing the code does not fix the rows already on disk.
 */

function labelledRefund(array $attributes = []): Order
{
    $customer = Customer::firstOrCreate(['phone' => '0595796456'], ['name' => 'مصطفى']);

    return $customer->orders()->create(array_merge([
        'reference'       => Order::newReference(),
        'public_token'    => Order::newPublicToken(),
        'customer_name'   => 'مصطفى',
        'customer_phone'  => '0595796456',
        'delivery_method' => 'dine-in',
        'payment_method'  => 'cash',
        'payment_status'  => Order::STATUS_PAID,
        'status'          => Order::FULFILMENT_REFUNDED,
        'subtotal'        => 12,
        'total'           => 12,
        'currency'        => 'ILS',
    ], $attributes));
}

it('reports broken rows without touching them', function () {
    labelledRefund();

    $this->artisan('orders:repair-refunds')->assertExitCode(1);

    expect(Order::whereNotNull('refunded_at')->count())->toBe(0);
});

it('records a refund that was paid but never written down', function () {
    $order = labelledRefund();

    // The money did reach the customer; only the order columns are missing.
    app(WalletService::class)->credit(
        $order->customer, Money::toAgorot(12), 'استرداد', 'wallet', null, $order,
    );

    $this->artisan('orders:repair-refunds --fix')->assertSuccessful();

    $order = $order->fresh();

    expect((float) $order->refunded_amount)->toBe(12.0)
        ->and($order->refunded_at)->not->toBeNull()
        ->and($order->refund_method)->toBe(Order::REFUND_WALLET);
});

it('does not credit twice when repairing a refund already paid', function () {
    $order = labelledRefund();

    app(WalletService::class)->credit(
        $order->customer, Money::toAgorot(12), 'استرداد', 'wallet', null, $order,
    );

    $this->artisan('orders:repair-refunds --fix --credit')->assertSuccessful();

    // Recording is not refunding: the balance must not move.
    expect(app(WalletService::class)->walletFor($order->customer)->fresh()->balance)->toBe(12.0);
});

it('leaves a never-refunded row alone unless told to credit it', function () {
    $order = labelledRefund();

    $this->artisan('orders:repair-refunds --fix')->assertSuccessful();

    // No evidence the money moved, so --fix alone will not invent a refund.
    expect($order->fresh()->refunded_at)->toBeNull()
        ->and(app(WalletService::class)->walletFor($order->customer)->fresh()->balance)->toBe(0.0);
});

it('credits a never-refunded row when explicitly asked', function () {
    $order = labelledRefund();

    $this->artisan('orders:repair-refunds --fix --credit')->assertSuccessful();

    expect((float) $order->fresh()->refunded_amount)->toBe(12.0)
        ->and(app(WalletService::class)->walletFor($order->customer)->fresh()->balance)->toBe(12.0);
});

it('says nothing is wrong when every refund is recorded', function () {
    $this->artisan('orders:repair-refunds')->assertSuccessful();
});

// ─── mislabelled, not refunded ──────────────────────────────────────────────

it('puts a mislabelled order back in its real status', function () {
    $order = labelledRefund();

    // Array form, not a command string: the Arabic statuses contain spaces
    // and argv splitting would tear them in half.
    $this->artisan('orders:repair-refunds', ['--fix' => true, '--clear' => Order::FULFILMENT_DELIVERED])
        ->assertSuccessful();

    $order = $order->fresh();

    expect($order->status)->toBe(Order::FULFILMENT_DELIVERED)
        ->and($order->refunded_at)->toBeNull()
        ->and((float) $order->refunded_amount)->toBe(0.0)
        // Nothing was owed, so nothing is paid.
        ->and(app(WalletService::class)->walletFor($order->customer)->fresh()->balance)->toBe(0.0);
});

it('refuses a status that does not belong to the order\'s own ladder', function () {
    // "في الطريق" is a delivery state and means nothing on a dine-in order.
    $order = labelledRefund();

    $this->artisan('orders:repair-refunds', ['--fix' => true, '--clear' => Order::FULFILMENT_ON_WAY])
        ->assertExitCode(1);

    expect($order->fresh()->status)->toBe(Order::FULFILMENT_REFUNDED);
});

it('never clears a refund that actually happened', function () {
    $order = labelledRefund();

    app(WalletService::class)->credit(
        $order->customer, Money::toAgorot(12), 'استرداد', 'wallet', null, $order,
    );
    $order->update(['refunded_amount' => 12, 'refunded_at' => now()]);

    // It is no longer in the broken set at all, so --clear cannot reach it.
    $this->artisan('orders:repair-refunds', ['--fix' => true, '--clear' => Order::FULFILMENT_DELIVERED])
        ->assertSuccessful();

    expect($order->fresh()->status)->toBe(Order::FULFILMENT_REFUNDED)
        ->and((float) $order->fresh()->refunded_amount)->toBe(12.0);
});
