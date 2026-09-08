<?php

use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\Checkout\Money;
use App\Services\Reporting\FinancialReport;
use App\Services\Storefront\OrderRefundService;
use App\Services\Storefront\WalletService;

/**
 * Refunding an order.
 *
 * The bug these were written for: the orders table refunded by changing the
 * status and nothing else, so an order read "مسترد" in the list while every
 * financial figure still counted it as a completed sale.
 */

beforeEach(function () {
    $this->cashier = User::factory()->create();
    $this->actingAs($this->cashier);
});

function refundOrder(array $attributes = []): Order
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
        'paid_at'         => now(),
        'subtotal'        => 12,
        'total'           => 12,
        'currency'        => 'ILS',
    ], $attributes));
}

it('records the amount and the date, not just the status', function () {
    $order = refundOrder();

    app(OrderRefundService::class)->toWallet($order);

    $order = $order->fresh();

    // The status alone is what the orders table used to write, and it left
    // every figure below reading this as a completed sale.
    expect($order->status)->toBe(Order::FULFILMENT_REFUNDED)
        ->and((float) $order->refunded_amount)->toBe(12.0)
        ->and($order->refunded_at)->not->toBeNull()
        ->and($order->refund_method)->toBe(Order::REFUND_WALLET);
});

it('puts the money on the customer wallet', function () {
    $order = refundOrder();

    app(OrderRefundService::class)->toWallet($order);

    expect(app(WalletService::class)->walletFor($order->customer)->fresh()->balance)->toBe(12.0);
});

it('refuses to refund the same order twice', function () {
    $order = refundOrder();

    app(OrderRefundService::class)->toWallet($order);

    expect(fn () => app(OrderRefundService::class)->toWallet($order->fresh()))
        ->toThrow(RuntimeException::class);

    // One refund, one credit.
    expect(app(WalletService::class)->walletFor($order->customer)->fresh()->balance)->toBe(12.0);
});

it('shows the refund in the financial report', function () {
    app(OrderRefundService::class)->toWallet(refundOrder());

    $report = (new FinancialReport(now()->startOfDay(), now()->endOfDay()))->toArray();

    expect($report['adjustments']['refundedTotal'])->toBe(12.0)
        ->and($report['adjustments']['refundedOrders'])->toBe(1)
        ->and($report['sales']['refunded'])->toBe(12.0);
});

it('separates a wallet refund from one handed back in notes', function () {
    app(OrderRefundService::class)->toWallet(refundOrder());
    app(OrderRefundService::class)->inCash(refundOrder(['total' => 20]));

    $adjustments = (new FinancialReport(now()->startOfDay(), now()->endOfDay()))->toArray()['adjustments'];

    // They reconcile against different things: one emptied the till, the
    // other only moved a sale into what the shop owes.
    expect($adjustments['refundedToWallet'])->toBe(12.0)
        ->and($adjustments['refundedInCash'])->toBe(20.0)
        ->and($adjustments['refundedTotal'])->toBe(32.0);
});

it('does not take a wallet refund out of the drawer', function () {
    $shift = CashierShift::create([
        'user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_float' => 0,
    ]);

    $order = refundOrder();
    $order->update(['shift_id' => $shift->id]);

    app(OrderRefundService::class)->toWallet($order->fresh());

    // The 12 is still in the till; it moved onto the customer's balance.
    // Subtracting it would report a surplus that is really just the cash
    // sitting there, and send somebody hunting an error that never happened.
    expect(Money::toDecimal($shift->fresh()->expectedCashAgorot()))->toBe(12.0);
});

it('does take a cash refund out of the drawer', function () {
    $shift = CashierShift::create([
        'user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_float' => 0,
    ]);

    $order = refundOrder();
    $order->update(['shift_id' => $shift->id]);

    app(OrderRefundService::class)->inCash($order->fresh());

    expect(Money::toDecimal($shift->fresh()->expectedCashAgorot()))->toBe(0.0);
});

function guestOrder(): Order
{
    return Order::create([
        'reference'       => Order::newReference(),
        'public_token'    => Order::newPublicToken(),
        'customer_name'   => 'زائر',
        'customer_phone'  => '0599000111',
        'delivery_method' => 'pickup',
        'payment_method'  => 'cash',
        'payment_status'  => Order::STATUS_PAID,
        'subtotal'        => 15,
        'total'           => 15,
        'currency'        => 'ILS',
    ]);
}

it('refunds a guest in cash, who has no wallet to credit', function () {
    $order = guestOrder();

    app(OrderRefundService::class)->inCash($order);

    expect((float) $order->fresh()->refunded_amount)->toBe(15.0);
});

it('will not send a guest refund to a wallet that does not exist', function () {
    expect(fn () => app(OrderRefundService::class)->toWallet(guestOrder()))
        ->toThrow(RuntimeException::class);
});
