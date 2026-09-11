<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\User;

/** The counter's receipt routes, and who may reach them. */

function routeOrder(array $attributes = []): Order
{
    $customer = Customer::firstOrCreate(['phone' => '0599123456'], ['name' => 'أحمد علي']);

    $order = $customer->orders()->create(array_merge([
        'reference'       => 'ORD-PRINT1',
        'public_token'    => Order::newPublicToken(),
        'customer_name'   => 'أحمد علي',
        'customer_phone'  => '0599123456',
        'delivery_method' => 'dine-in',
        'payment_method'  => 'cash',
        'table_number'    => '7',
        'subtotal'        => 30, 'total' => 30, 'currency' => 'ILS',
    ], $attributes));

    $order->items()->create([
        'product_slug' => 'cup', 'product_name' => 'بوظة كاسة', 'kind' => 'builder',
        'selection' => [], 'description' => 'صغير · فانيلا',
        'unit_price' => 30, 'quantity' => 1, 'addons_total' => 0, 'line_total' => 30,
    ]);

    return $order;
}

it('renders a printable receipt for staff', function () {
    $this->actingAs(User::factory()->create());
    $order = routeOrder();

    $this->get(route('receipts.show', $order->reference))
        ->assertOk()
        ->assertSee('ORD-PRINT1')
        ->assertSee('بوظة كاسة', false)
        ->assertSee('طاولة 7', false);
});

it('keeps receipts away from anyone not signed into the dashboard', function () {
    $order = routeOrder();

    // A receipt carries another customer's name, phone and address.
    $this->get(route('receipts.show', $order->reference))->assertRedirect();
    $this->getJson(route('receipts.queue'))->assertUnauthorized();
});

it('marks the order printed only when it actually auto-printed', function () {
    $this->actingAs(User::factory()->create());
    $order = routeOrder();

    $this->get(route('receipts.show', $order->reference))->assertOk();
    expect($order->fresh()->printed())->toBeFalse();

    $this->get(route('receipts.show', $order->reference) . '?auto=1')->assertOk();
    expect($order->fresh()->printed())->toBeTrue()
        ->and($order->fresh()->print_count)->toBe(1);
});

it('marks a reprint as a duplicate on the paper', function () {
    $this->actingAs(User::factory()->create());
    $order = routeOrder();

    $this->get(route('receipts.show', $order->reference) . '?auto=1')->assertOk();

    // So a second slip cannot be passed off as a second sale.
    $this->get(route('receipts.show', $order->reference))
        ->assertOk()
        ->assertSee('نسخة مُعادة', false);
});

it('lists orders for the cashier screen, finished ones included', function () {
    $this->actingAs(User::factory()->create());
    routeOrder();
    routeOrder(['reference' => 'ORD-DONE01', 'status' => Order::FULFILMENT_RECEIVED]);

    $response = $this->getJson(route('receipts.queue'))->assertOk();

    // The screen filters in the browser, so an order the feed leaves out
    // cannot be searched for there at all — and the counter looks up a
    // finished order as often as it works on a live one. `final` is what the
    // status chips read to tell them apart.
    expect($response->json('orders'))->toHaveCount(2)
        ->and($response->json('orders.1.reference'))->toBe('ORD-PRINT1')
        ->and($response->json('orders.1.tableNumber'))->toBe('7')
        ->and(collect($response->json('orders'))->firstWhere('reference', 'ORD-DONE01')['final'])
        ->toBeTrue();
});

it('serves the receipt at both paper widths', function () {
    $this->actingAs(User::factory()->create());
    $order = routeOrder();

    foreach ([58, 80] as $width) {
        $this->get(route('receipts.show', $order->reference) . '?width=' . $width)
            ->assertOk()
            ->assertSee("size: {$width}mm auto", false);
    }
});

it('draws within the printable width, not the paper width', function () {
    // 80 mm paper prints 72 mm and 58 mm prints 48 mm. Drawing at the paper
    // width pushed the order number, date and phone off the head.
    $this->actingAs(User::factory()->create());
    $order = routeOrder();

    $this->get(route('receipts.show', $order->reference) . '?width=80')
        ->assertSee('width: 72mm', false);

    $this->get(route('receipts.show', $order->reference) . '?width=58')
        ->assertSee('width: 48mm', false);
});
