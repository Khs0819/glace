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

it('lists live orders for the cashier screen', function () {
    $this->actingAs(User::factory()->create());
    routeOrder();
    routeOrder(['reference' => 'ORD-DONE01', 'status' => Order::FULFILMENT_RECEIVED]);

    $response = $this->getJson(route('receipts.queue'))->assertOk();

    // Finished orders are not the counter's problem any more.
    expect($response->json('orders'))->toHaveCount(1)
        ->and($response->json('orders.0.reference'))->toBe('ORD-PRINT1')
        ->and($response->json('orders.0.tableNumber'))->toBe('7');
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
