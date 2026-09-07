<?php

use App\Filament\Pages\CashierBoard;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\Printing\ReceiptDocument;
use App\Services\Storefront\WalletService;
use Livewire\Livewire;

/**
 * Over-payment at the counter: hand over 100 for a 36 order, get 64 as store
 * credit rather than coins.
 *
 * The rule that shapes all of it: the credit appears when the cashier takes
 * the cash, never when the order is placed.
 */

function changeOrder(array $attributes = []): Order
{
    $customer = Customer::firstOrCreate(['phone' => '0599123456'], ['name' => 'أحمد علي']);

    return $customer->orders()->create(array_merge([
        'reference'       => Order::newReference(),
        'public_token'    => Order::newPublicToken(),
        'customer_name'   => 'أحمد علي',
        'customer_phone'  => '0599123456',
        'delivery_method' => 'dine-in',
        'payment_method'  => 'cash',
        'subtotal'        => 36,
        'total'           => 36,
        'tendered_amount' => 100,
        'currency'        => 'ILS',
    ], $attributes));
}

/** The printed receipt as plain text; lines() yields text plus styling. */
function receiptText(Order $order): string
{
    return implode(PHP_EOL, array_column((new ReceiptDocument($order))->lines(48), 'text'));
}

it('owes change without crediting anything until the cash arrives', function () {
    $order = changeOrder();

    expect($order->changeDue())->toBe(64.0)
        ->and($order->changePending())->toBeTrue()
        ->and((float) $order->change_credited)->toBe(0.0);

    // Store credit for money nobody has handed over yet is money invented.
    expect(app(WalletService::class)->walletFor($order->customer)->balance)->toBe(0.0);
});

it('credits the change the moment the cashier takes the money', function () {
    $cashier = User::factory()->create();
    $this->actingAs($cashier);

    Livewire::test(CashierBoard::class)->callAction('openShift', ['opening_float' => 0]);

    $order = changeOrder();

    Livewire::test(CashierBoard::class)->call('markPaid', $order->reference);

    $order = $order->fresh();

    expect($order->isPaid())->toBeTrue()
        ->and((float) $order->change_credited)->toBe(64.0)
        ->and($order->change_credited_at)->not->toBeNull()
        ->and($order->changePending())->toBeFalse()
        ->and(app(WalletService::class)->walletFor($order->customer)->balance)->toBe(64.0);
});

it('does not credit the change twice when the button is clicked twice', function () {
    $cashier = User::factory()->create();
    $this->actingAs($cashier);

    Livewire::test(CashierBoard::class)->callAction('openShift', ['opening_float' => 0]);

    $order = changeOrder();

    Livewire::test(CashierBoard::class)->call('markPaid', $order->reference);
    Livewire::test(CashierBoard::class)->call('markPaid', $order->reference);

    expect(app(WalletService::class)->walletFor($order->fresh()->customer)->balance)->toBe(64.0);
});

it('leaves the wallet alone when the exact amount is handed over', function () {
    $cashier = User::factory()->create();
    $this->actingAs($cashier);

    Livewire::test(CashierBoard::class)->callAction('openShift', ['opening_float' => 0]);

    $order = changeOrder(['tendered_amount' => 36]);

    Livewire::test(CashierBoard::class)->call('markPaid', $order->reference);

    expect($order->fresh()->isPaid())->toBeTrue()
        ->and(app(WalletService::class)->walletFor($order->customer)->balance)->toBe(0.0);
});

it('tells the cashier on the paper not to hand back coins', function () {
    $lines = receiptText(changeOrder());

    // Without this the cashier reads 36 against a hundred note and gives back
    // 64 out of habit, and the shop has paid it twice.
    expect($lines)->toContain('المدفوع')
        ->and($lines)->toContain('الباقي (للمحفظة)')
        ->and($lines)->toContain('لا تُعِد باقياً نقداً');
});

it('says nothing about change on an ordinary receipt', function () {
    expect(receiptText(changeOrder(['tendered_amount' => null])))->not->toContain('الباقي');
});

it('expects the whole note in the drawer, not just the order total', function () {
    $cashier = User::factory()->create();
    $this->actingAs($cashier);

    Livewire::test(CashierBoard::class)->callAction('openShift', ['opening_float' => 0]);

    Livewire::test(CashierBoard::class)->call('markPaid', changeOrder()->reference);

    $shift = CashierShift::openFor($cashier);

    // 100 was handed over and none of it given back: 64 became store credit,
    // and all 100 is sitting in the drawer. Expecting 36 would report a 64
    // surplus and send somebody hunting an error that never happened.
    expect(App\Services\Checkout\Money::toDecimal($shift->expectedCashAgorot()))->toBe(100.0);

    Livewire::test(CashierBoard::class)
        ->callAction('closeShift', ['counted_cash' => 100, 'notes' => null]);

    expect($shift->fresh()->difference)->toBe(0.0);
});
