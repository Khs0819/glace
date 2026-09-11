<?php

use App\Models\Customer;
use App\Models\Order;
use App\Services\Printing\ReceiptDocument;

/**
 * What the slip actually says, checked against the paper the shop handed back.
 */

function layoutOrder(array $attributes = [], array $items = []): Order
{
    $customer = Customer::firstOrCreate(['phone' => '0593236412'], ['name' => 'كريم']);

    $order = $customer->orders()->create(array_merge([
        'reference'       => 'ORD-EFQ2AM',
        'public_token'    => Order::newPublicToken(),
        'customer_name'   => 'كريم',
        'customer_phone'  => '0593236412',
        'delivery_method' => 'delivery',
        'payment_method'  => 'bop',
        'subtotal'        => 38,
        'delivery_fee'    => 10,
        'discount'        => 5,
        'total'           => 43,
        'currency'        => 'ILS',
    ], $attributes));

    foreach ($items ?: [['كاس كبير كلاسيك', 10.0, 3, 30.0], ['براد كبير', 8.0, 1, 8.0]] as [$name, $unit, $qty, $total]) {
        $order->items()->create([
            'product_slug' => 'x', 'product_name' => $name, 'kind' => 'flat',
            'selection' => [], 'description' => '',
            'unit_price' => $unit, 'quantity' => $qty, 'addons_total' => 0, 'line_total' => $total,
        ]);
    }

    return $order;
}

function slip(Order $order, int $width = 42): string
{
    return implode(PHP_EOL, array_column((new ReceiptDocument($order))->lines($width), 'text'));
}

it('shows the unit price on a line of more than one', function () {
    // Three cups at ten: without the unit price the only way to check the
    // line against the menu is to divide.
    expect(slip(layoutOrder()))->toContain('3 × 10.00');
});

it('does not clutter a single-item line with its own unit price', function () {
    expect(slip(layoutOrder()))->not->toContain('1 × 8.00');
});

it('prints the delivery fee on a delivery', function () {
    expect(slip(layoutOrder()))->toContain('رسوم التوصيل');
});

it('no longer prints an unpaid banner', function () {
    // The slip is not produced in that state any more, and the banner was
    // occupying the most prominent box on the paper to say nothing.
    expect(slip(layoutOrder()))->not->toContain('غير مدفوع');
});

it('gives that box to the driver on a delivery', function () {
    $order = layoutOrder(['driver' => ['name' => 'أحمد سعيد', 'phone' => '0599876543']]);

    expect(slip($order))->toContain('السائق: أحمد سعيد · 0599876543');
});

it('says nothing about a driver when there is none', function () {
    expect(slip(layoutOrder()))->not->toContain('السائق');
});

it('says nothing about a driver on an order that is not a delivery', function () {
    $order = layoutOrder([
        'delivery_method' => 'dine-in',
        'payment_method'  => 'cash',
        'driver'          => ['name' => 'أحمد سعيد', 'phone' => '0599876543'],
    ]);

    expect(slip($order))->not->toContain('السائق');
});

it('keeps every line inside the paper width', function () {
    // The slip that came back had its values cut off the edge, which is what
    // a line longer than the roll looks like.
    foreach ((new ReceiptDocument(layoutOrder()))->lines(32) as $line) {
        expect(mb_strlen($line['text']))->toBeLessThanOrEqual(32);
    }
});

it('spends no double-height lines on the paper', function () {
    // Each one costs a line of roll for legibility nobody asked for.
    $large = array_filter(
        (new ReceiptDocument(layoutOrder()))->lines(42),
        fn (array $line) => $line['large'] ?? false,
    );

    expect($large)->toBe([]);
});
