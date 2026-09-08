<?php

namespace App\Services\Storefront;

use App\Models\Order;
use App\Services\Checkout\Money;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Returning an order's money, in one place.
 *
 * There were two implementations of this — one on the cashier screen, one in
 * the orders table — and they disagreed: the cashier's recorded the amount and
 * the date, the table's only changed the status. An order refunded from the
 * table therefore read as "مسترد" in the list while every financial figure
 * still counted it as a completed sale. The refund had happened; the books
 * denied it.
 *
 * Marking the status and moving the money are the same act, so they belong in
 * one transaction. Split, the failure mode is silent: a customer credited with
 * no record of why, or an order labelled refunded with the money still here.
 */
class OrderRefundService
{
    public function __construct(private readonly WalletService $wallet) {}

    /**
     * Refund the full total as store credit.
     *
     * @throws RuntimeException when the order cannot take a refund
     */
    public function toWallet(Order $order): void
    {
        $this->assertRefundable($order);

        // Only this path needs somewhere to put the credit; cash can be handed
        // to a guest across the counter.
        if ($order->customer === null) {
            throw new RuntimeException('لا يمكن الاسترداد للمحفظة — الطلب بلا حساب زبون');
        }

        DB::transaction(function () use ($order) {
            $this->wallet->credit(
                $order->customer,
                Money::toAgorot($order->total),
                'استرداد طلب #' . $order->reference,
                'wallet',
                null,
                $order,
            );

            $order->update([
                'status'          => Order::FULFILMENT_REFUNDED,
                'refunded_amount' => $order->total,
                'refunded_at'     => now(),
                // Named so the drawer reconciliation knows this money never
                // left the till — it moved onto the customer's balance.
                'refund_method'   => Order::REFUND_WALLET,
            ]);
        });
    }

    /**
     * Refund in notes, out of the drawer.
     *
     * Recorded separately from a wallet refund because the two do opposite
     * things to the till: this one empties it, the other does not touch it.
     */
    public function inCash(Order $order): void
    {
        $this->assertRefundable($order);

        $order->update([
            'status'          => Order::FULFILMENT_REFUNDED,
            'refunded_amount' => $order->total,
            'refunded_at'     => now(),
            'refund_method'   => Order::REFUND_CASH,
        ]);
    }

    private function assertRefundable(Order $order): void
    {
        if ($order->isRefunded()) {
            throw new RuntimeException('هذا الطلب مسترد بالفعل');
        }

        if ($order->total <= 0) {
            throw new RuntimeException('لا يوجد مبلغ لاسترداده');
        }
    }
}
