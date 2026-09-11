<?php

use App\Services\Printing\EscPosPrinter;

/**
 * The code page index, which is the setting that decides whether Arabic is
 * Arabic or Latin punctuation.
 */

function escpos(array $overrides = []): EscPosPrinter
{
    return new EscPosPrinter(array_merge([
        'enabled' => true, 'host' => '127.0.0.1', 'port' => 9100, 'width' => 48,
    ], $overrides));
}

it('emits the ESC t sequence for a given table', function () {
    expect(escpos()->codePageCommand(22))->toBe("\x1Bt" . chr(22));
});

it('clamps a table number rather than emitting a stray byte', function () {
    // A bad value in .env must not turn into some other control character.
    expect(escpos()->codePageCommand(999))->toBe("\x1Bt" . chr(255))
        ->and(escpos()->codePageCommand(-5))->toBe("\x1Bt" . chr(0));
});

it('uses the configured table instead of guessing from the code page name', function () {
    // Epson puts CP864 at 37; other makes do not, and the guess is wrong there.
    $stream = escpos(['codepage' => 'CP864', 'codepage_table' => 22])
        ->render(new App\Services\Printing\ReceiptDocument(calibrationOrder()));

    expect($stream)->toContain("\x1Bt" . chr(22))
        ->and($stream)->not->toContain("\x1Bt" . chr(37));
});

it('falls back to the Epson numbering when no table is configured', function () {
    $stream = escpos(['codepage' => 'CP864'])
        ->render(new App\Services\Printing\ReceiptDocument(calibrationOrder()));

    expect($stream)->toContain("\x1Bt" . chr(37));
});

it('treats an empty table setting as unset', function () {
    // .env supplies '' for a blank line, which must not become table 0.
    $stream = escpos(['codepage' => 'CP1256', 'codepage_table' => ''])
        ->render(new App\Services\Printing\ReceiptDocument(calibrationOrder()));

    expect($stream)->toContain("\x1Bt" . chr(50));
});

it('refuses to send raw bytes when network printing is off', function () {
    expect(fn () => escpos(['enabled' => false])->sendRaw('x'))
        ->toThrow(RuntimeException::class);
});

function calibrationOrder(): App\Models\Order
{
    $customer = App\Models\Customer::firstOrCreate(['phone' => '0599123456'], ['name' => 'أحمد']);

    $order = $customer->orders()->create([
        'reference'       => App\Models\Order::newReference(),
        'public_token'    => App\Models\Order::newPublicToken(),
        'customer_name'   => 'أحمد',
        'customer_phone'  => '0599123456',
        'delivery_method' => 'dine-in',
        'payment_method'  => 'cash',
        'subtotal'        => 10,
        'total'           => 10,
        'currency'        => 'ILS',
    ]);

    $order->items()->create([
        'product_slug' => 'cup', 'product_name' => 'بوظة', 'kind' => 'builder',
        'selection' => [], 'description' => 'صغير',
        'unit_price' => 10, 'quantity' => 1, 'addons_total' => 0, 'line_total' => 10,
    ]);

    return $order;
}
