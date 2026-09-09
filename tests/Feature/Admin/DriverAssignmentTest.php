<?php

use App\Filament\Pages\CashierBoard;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

/**
 * Handing a delivery to a driver.
 *
 * The rule the whole thing exists for: "في الطريق" with nobody named is a
 * delivery the shop cannot answer a question about.
 */

beforeEach(fn () => $this->actingAs(User::factory()->create()));

function deliveryOrder(array $attributes = []): Order
{
    $customer = Customer::firstOrCreate(['phone' => '0599123456'], ['name' => 'أحمد']);

    return $customer->orders()->create(array_merge([
        'reference'       => Order::newReference(),
        'public_token'    => Order::newPublicToken(),
        'customer_name'   => 'أحمد',
        'customer_phone'  => '0599123456',
        'delivery_method' => 'delivery',
        'payment_method'  => 'cash',
        'status'          => Order::FULFILMENT_PREPARING,
        'subtotal'        => 40,
        'total'           => 50,
        'currency'        => 'ILS',
    ], $attributes));
}

function driver(array $attributes = []): Driver
{
    return Driver::create(array_merge([
        'name' => 'أحمد سعيد', 'phone' => '0599876543', 'company' => 'توصيل فلسطين', 'active' => true,
    ], $attributes));
}

it('refuses to put a delivery on the road with no driver', function () {
    $order = deliveryOrder();

    Livewire::test(CashierBoard::class)->call('advance', $order->reference, Order::FULFILMENT_ON_WAY);

    // The customer rings to ask where it is, and nobody can say.
    expect($order->fresh()->status)->toBe(Order::FULFILMENT_PREPARING);
});

it('assigns a driver and puts the order on the road in one step', function () {
    $order  = deliveryOrder();
    $driver = driver();

    Livewire::test(CashierBoard::class)->call('assignDriver', $order->reference, $driver->getKey());

    $order = $order->fresh();

    expect($order->status)->toBe(Order::FULFILMENT_ON_WAY)
        ->and($order->driver_id)->toBe($driver->getKey())
        ->and($order->driver['name'])->toBe('أحمد سعيد')
        ->and($order->driver['phone'])->toBe('0599876543')
        ->and($order->driver_assigned_at)->not->toBeNull();
});

it('lets a delivery that already has a driver move on', function () {
    $order = deliveryOrder(['driver_id' => driver()->getKey()]);

    Livewire::test(CashierBoard::class)->call('advance', $order->reference, Order::FULFILMENT_ON_WAY);

    expect($order->fresh()->status)->toBe(Order::FULFILMENT_ON_WAY);
});

it('keeps the driver readable after they are renamed', function () {
    $order  = deliveryOrder();
    $driver = driver();

    Livewire::test(CashierBoard::class)->call('assignDriver', $order->reference, $driver->getKey());

    $driver->update(['name' => 'اسم آخر تماماً']);

    // The delivery says what it said on the day it went out.
    expect($order->fresh()->driver['name'])->toBe('أحمد سعيد');
});

it('will not hand an order to a driver who has been switched off', function () {
    $order = deliveryOrder();

    Livewire::test(CashierBoard::class)
        ->call('assignDriver', $order->reference, driver(['active' => false])->getKey());

    expect($order->fresh()->status)->toBe(Order::FULFILMENT_PREPARING)
        ->and($order->fresh()->driver_id)->toBeNull();
});

it('refuses to put a driver on an order that is not a delivery', function () {
    $order = deliveryOrder(['delivery_method' => 'pickup']);

    Livewire::test(CashierBoard::class)->call('assignDriver', $order->reference, driver()->getKey());

    expect($order->fresh()->driver_id)->toBeNull();
});

// ─── who is free ────────────────────────────────────────────────────────────

it('reads a driver as busy while an order of theirs is on the road', function () {
    $driver = driver();

    expect($driver->busy())->toBeFalse();

    deliveryOrder(['driver_id' => $driver->getKey(), 'status' => Order::FULFILMENT_ON_WAY]);

    // Derived from the orders, never a flag somebody has to remember to clear.
    expect($driver->fresh()->busy())->toBeTrue()
        ->and($driver->fresh()->statusLabel())->toBe('في توصيل');
});

it('frees a driver the moment their delivery is received', function () {
    $driver = driver();
    $order  = deliveryOrder(['driver_id' => $driver->getKey(), 'status' => Order::FULFILMENT_ON_WAY]);

    $order->update(['status' => Order::FULFILMENT_RECEIVED]);

    expect($driver->fresh()->busy())->toBeFalse();
});

it('offers free drivers before busy ones', function () {
    $busy = driver(['name' => 'أ مشغول']);
    driver(['name' => 'ب متاح', 'phone' => '0599111222']);

    deliveryOrder(['driver_id' => $busy->getKey(), 'status' => Order::FULFILMENT_ON_WAY]);

    $offered = Livewire::test(CashierBoard::class)->instance()->drivers();

    // A cashier with a queue should not have to read past the busy ones.
    expect($offered[0]['name'])->toBe('ب متاح')
        ->and($offered[0]['busy'])->toBeFalse()
        ->and($offered[1]['busy'])->toBeTrue();
});

it('does not offer a driver who is switched off', function () {
    driver(['active' => false]);

    expect(Livewire::test(CashierBoard::class)->instance()->drivers())->toBe([]);
});

// ─── the screens ────────────────────────────────────────────────────────────

it('renders the drivers list, create and edit screens', function () {
    $driver = driver();

    $this->get(App\Filament\Resources\DriverResource::getUrl('index'))->assertSuccessful();
    $this->get(App\Filament\Resources\DriverResource::getUrl('create'))->assertSuccessful();
    $this->get(App\Filament\Resources\DriverResource::getUrl('edit', ['record' => $driver]))
        ->assertSuccessful();
});

it('still renders the cashier screen with a driver on an order', function () {
    $d = driver();
    deliveryOrder(['driver_id' => $d->getKey(), 'driver' => $d->snapshot(), 'status' => Order::FULFILMENT_ON_WAY]);

    Livewire::test(CashierBoard::class)->assertOk();
});
