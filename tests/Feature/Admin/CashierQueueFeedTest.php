<?php

use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\User;

/**
 * The JSON the cashier screen polls.
 *
 * The screen filters and searches in the browser over what this returns, so
 * anything missing here is invisible there — including the closed orders the
 * counter looks up all day.
 */

beforeEach(fn () => $this->actingAs(User::factory()->create()));

function feedOrder(array $attributes = []): Order
{
    $customer = Customer::firstOrCreate(['phone' => '0599123456'], ['name' => 'أحمد']);

    return $customer->orders()->create(array_merge([
        'reference'       => Order::newReference(),
        'public_token'    => Order::newPublicToken(),
        'customer_name'   => 'أحمد',
        'customer_phone'  => '0599123456',
        'delivery_method' => 'pickup',
        'payment_method'  => 'cash',
        'subtotal'        => 20,
        'total'           => 20,
        'currency'        => 'ILS',
    ], $attributes));
}

it('includes closed orders so the counter can look one up', function () {
    feedOrder(['status' => Order::FULFILMENT_DELIVERED]);
    feedOrder(['status' => Order::FULFILMENT_REVIEW]);

    $orders = $this->getJson(route('receipts.queue'))->assertOk()->json('orders');

    // Filtering happens in the browser; an order the feed omits cannot be
    // found there at all.
    expect($orders)->toHaveCount(2)
        ->and(collect($orders)->pluck('final')->all())->toContain(true, false);
});

it('names the account a transfer landed in', function () {
    $account = PaymentAccount::create([
        'method' => 'jawwal-manual', 'holder_name' => 'يوسف عماد',
        'primary_label' => 'رقم جوال باي', 'primary_value' => '0599000111', 'active' => true,
    ]);

    feedOrder(['payment_method' => 'jawwal-manual', 'payment_account_id' => $account->getKey()]);

    // "Paid by Jawwal Pay" does not say which of the shop's numbers to check.
    expect($this->getJson(route('receipts.queue'))->json('orders.0.paidToAccount'))
        ->toBe('يوسف عماد');
});

it('flags a delivery that still has nobody to carry it', function () {
    feedOrder(['delivery_method' => 'delivery', 'status' => Order::FULFILMENT_PREPARING]);

    $order = $this->getJson(route('receipts.queue'))->json('orders.0');

    expect($order['needsDriver'])->toBeTrue()
        ->and($order['driver'])->toBeNull();
});

it('carries the driver once one is assigned', function () {
    $driver = Driver::create(['name' => 'أحمد سعيد', 'phone' => '0599876543', 'company' => 'توصيل فلسطين']);

    feedOrder([
        'delivery_method' => 'delivery',
        'status'          => Order::FULFILMENT_ON_WAY,
        'driver_id'       => $driver->getKey(),
        'driver'          => $driver->snapshot(),
    ]);

    $order = $this->getJson(route('receipts.queue'))->json('orders.0');

    expect($order['needsDriver'])->toBeFalse()
        ->and($order['driver']['name'])->toBe('أحمد سعيد')
        ->and($order['driver']['phone'])->toBe('0599876543')
        ->and($order['driver']['company'])->toBe('توصيل فلسطين');
});

it('says nothing about a driver on a pickup order', function () {
    feedOrder(['delivery_method' => 'pickup']);

    expect($this->getJson(route('receipts.queue'))->json('orders.0.needsDriver'))->toBeFalse();
});
