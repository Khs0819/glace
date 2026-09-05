<?php

use App\Models\Customer;
use App\Models\Order;
use App\Services\Printing\EscPosPrinter;
use App\Services\Printing\ReceiptDocument;
use App\Support\ArabicShaper;

/** Receipts: what goes on the paper, and how it gets there. */

function receiptOrder(array $attributes = []): Order
{
    $customer = Customer::create(['name' => 'أحمد علي', 'phone' => '0599123456']);

    $order = $customer->orders()->create(array_merge([
        'reference'       => 'ORD-M3K2A1',
        'public_token'    => str_repeat('a', 64),
        'customer_name'   => 'أحمد علي',
        'customer_phone'  => '0599123456',
        'delivery_method' => 'dine-in',
        'payment_method'  => 'cash',
        'table_number'    => '7',
        'subtotal'        => 33,
        'discount'        => 10,
        'total'           => 23,
        'currency'        => 'ILS',
    ], $attributes));

    $order->items()->create([
        'product_slug' => 'cup', 'product_name' => 'بوظة كاسة', 'kind' => 'builder',
        'selection' => ['sizeLabel' => 'صغير'], 'description' => 'صغير · فانيلا + إضافات: بندق',
        'unit_price' => 15, 'quantity' => 2, 'addons_total' => 3, 'line_total' => 33,
    ]);

    return $order->load('items');
}

it('puts the destination where the counter reads it first', function () {
    $doc = new ReceiptDocument(receiptOrder(), ['name' => 'جلاسيه الأمير']);

    expect($doc->kind())->toBe('داخل المحل')
        ->and($doc->destination())->toBe('طاولة 7');
});

it('shows the delivery area as the destination', function () {
    $doc = new ReceiptDocument(receiptOrder([
        'delivery_method' => 'delivery',
        'address' => ['area' => 'الرمال', 'city' => 'غزة', 'street' => 'شارع الجلاء'],
    ]), []);

    expect($doc->kind())->toBe('توصيل')->and($doc->destination())->toBe('الرمال');
});

it('carries the table number and customer in the header', function () {
    $doc    = new ReceiptDocument(receiptOrder(), []);
    $header = $doc->header();

    expect($header['رقم الطلب'])->toBe('ORD-M3K2A1')
        ->and($header['الطاولة'])->toBe('7')
        ->and($header['الزبون'])->toBe('أحمد علي');
});

it('omits totals that are zero', function () {
    $doc = new ReceiptDocument(receiptOrder(['discount' => 0, 'delivery_fee' => 0]), []);

    expect($doc->totals())->toHaveKey('المجموع')
        ->and($doc->totals())->not->toHaveKey('الخصم')
        ->and($doc->totals())->not->toHaveKey('رسوم التوصيل');
});

it('wraps printer lines to the paper width', function () {
    $doc = new ReceiptDocument(receiptOrder(), ['name' => 'جلاسيه الأمير']);

    foreach ($doc->lines(48) as $line) {
        // A line longer than the head silently wraps and ruins the alignment.
        expect(mb_strlen($line['text']))->toBeLessThanOrEqual(48);
    }
});

it('marks an unpaid order loudly on the paper', function () {
    $lines = collect((new ReceiptDocument(receiptOrder(), []))->lines())->pluck('text');

    expect($lines->contains(fn ($l) => str_contains($l, 'غير مدفوع')))->toBeTrue();
});

it('does not shout unpaid once the money is in', function () {
    $order = receiptOrder(['payment_status' => Order::STATUS_PAID]);
    $lines = collect((new ReceiptDocument($order, []))->lines())->pluck('text');

    expect($lines->contains(fn ($l) => str_contains($l, 'غير مدفوع')))->toBeFalse();
});

// ─── ESC/POS ────────────────────────────────────────────────────────────────

it('shapes arabic for a CP864 printer and leaves it alone for CP1256', function () {
    $shaping = new EscPosPrinter(['enabled' => true, 'host' => 'x', 'codepage' => 'CP864']);
    $raw     = new EscPosPrinter(['enabled' => true, 'host' => 'x', 'codepage' => 'CP1256']);

    // CP864 carries presentation forms, so we shape. CP1256 carries the base
    // letters and the printer shapes them — doing both is mojibake.
    expect($shaping->encode('مرحبا'))->not->toBe($raw->encode('مرحبا'));
});

it('emits an init, a codepage and a cut around the receipt', function () {
    $printer = new EscPosPrinter(['enabled' => true, 'host' => 'x', 'codepage' => 'CP864']);
    $bytes   = $printer->render(new ReceiptDocument(receiptOrder(), []));

    expect($bytes)->toStartWith("\x1B@")          // ESC @ — init
        ->and($bytes)->toContain("\x1Bt")         // ESC t — code page
        ->and($bytes)->toContain("\x1DVA");       // GS V A — cut
});

it('refuses to print when no printer is configured', function () {
    $printer = new EscPosPrinter(['enabled' => false]);

    expect($printer->enabled())->toBeFalse();

    expect(fn () => $printer->print(new ReceiptDocument(receiptOrder(), [])))
        ->toThrow(RuntimeException::class);
});

// ─── the shaper ─────────────────────────────────────────────────────────────

it('joins arabic letters into their contextual forms', function () {
    // meem initial, reh final, hah initial, beh medial, alef final
    expect(ArabicShaper::shape('مرحبا'))->toBe("\u{FEE3}\u{FEAE}\u{FEA3}\u{FE92}\u{FE8E}");
});

it('makes lam-alef a single ligature glyph', function () {
    expect(ArabicShaper::shape('لا'))->toBe("\u{FEFB}")
        ->and(mb_strlen(ArabicShaper::shape('لا')))->toBe(1);
});

it('does not join a letter that never joins forward', function () {
    // Dal has no initial form, so the letter after it stays isolated.
    expect(ArabicShaper::shape('دد'))->toBe("\u{FEA9}\u{FEA9}");
});

it('keeps latin and digits readable inside reversed arabic', function () {
    // The line is reversed for the printer; the reference must not be.
    expect(ArabicShaper::forPrinter('الطلب ORD-M3K2A1'))->toContain('ORD-M3K2A1');
});

it('leaves a pure latin line untouched', function () {
    expect(ArabicShaper::shape('ORD-M3K2A1'))->toBe('ORD-M3K2A1');
});

it('strips tashkeel rather than shaping around it', function () {
    expect(ArabicShaper::shape('مُرَحَبا'))->toBe(ArabicShaper::shape('مرحبا'));
});
