<?php

namespace App\Services\Orders;

use App\Models\ChangeRefundRequest;
use App\Models\Driver;
use App\Models\DriverSettlement;
use App\Models\Order;
use App\Services\Storefront\OrderRefundService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Moving an order along — and back — in one place.
 *
 * The cashier screen and the orders page each had their own copy of this, and
 * they disagreed: the orders page would put a delivery "في الطريق" with nobody
 * named, and its "assign driver" wrote a free-text name that never linked the
 * order to a driver at all. A mistake made on one screen could then not be
 * undone on either. Both screens now call this, so the rules are the same
 * wherever the button is pressed.
 *
 * Every refusal is a RuntimeException carrying the sentence to show the
 * cashier; the screens only decide how to show it.
 */
class OrderFulfilment
{
    public function __construct(private readonly OrderRefundService $refunds) {}

    /** Move an order forward along its own ladder. */
    public function advance(Order $order, string $status): void
    {
        if ($status === Order::FULFILMENT_REFUNDED) {
            // "مسترد" as a bare status moved no money and locked the order.
            throw new RuntimeException('الاسترداد يتم من زر «استرداد المبلغ» حتى يُسجَّل المال فعلاً');
        }

        if (! in_array($status, $order->allowedNextStatuses(), true)) {
            throw new RuntimeException($this->needsDriver($order, $status)
                ? 'عيّن سائقاً أولاً — لا يُغلق طلب توصيل بدون سائق'
                : 'حالة غير متاحة لهذا الطلب');
        }

        if ($this->needsDriver($order, $status)) {
            throw new RuntimeException('عيّن سائقاً أولاً — لا يُغلق طلب توصيل بدون سائق');
        }

        DB::transaction(function () use ($order, $status) {
            $order->update(array_filter([
                'status'       => $status,
                'delivered_at' => $status === Order::FULFILMENT_DELIVERED ? now() : $order->delivered_at,
                'received_at'  => $status === Order::FULFILMENT_RECEIVED ? now() : $order->received_at,
                'cancelled_at' => $status === Order::FULFILMENT_CANCELLED ? now() : $order->cancelled_at,
            ], fn ($value) => $value !== null));

            // The fee was booked when the driver was chosen. Arrival stamps it;
            // cancelling before the driver is paid takes it back.
            if ($status === Order::FULFILMENT_RECEIVED) {
                DriverSettlement::where('order_id', $order->getKey())
                    ->whereNull('delivered_at')
                    ->update(['delivered_at' => now()]);
            }

            if ($status === Order::FULFILMENT_CANCELLED) {
                DriverSettlement::where('order_id', $order->getKey())
                    ->whereNull('payout_id')
                    ->delete();
            }
        });
    }

    /**
     * Put an order back to an earlier step after a mistake.
     *
     * The cashier who pressed "تم التسليم" on a delivery nobody had taken yet
     * has to be able to undo it themselves: an order stuck in the wrong final
     * state could not be given a driver, moved on, or even looked at properly.
     * A refunded order is the one exception — money has moved, and a status
     * change cannot move it back.
     */
    public function correct(Order $order, string $status): void
    {
        if (! in_array($status, $order->correctableStatuses(), true)) {
            throw new RuntimeException($order->status === Order::FULFILMENT_REFUNDED
                ? 'طلب مسترد — المال أُعيد للزبون ولا يمكن تغيير حالته'
                : 'لا يمكن إرجاع الطلب إلى هذه الحالة');
        }

        if ($status === Order::FULFILMENT_ON_WAY && ! $order->canGoOnTheRoad()) {
            throw new RuntimeException('عيّن سائقاً أولاً — لا يكون طلب «في الطريق» بدون سائق');
        }

        $flow  = $order->fulfilmentFlow();
        $index = array_search($status, $flow, true);

        // Anything the order had passed through after the step it goes back to
        // is undone with it, so the tracker and the reports stop reading it.
        $passed = fn (string $milestone) => ($at = array_search($milestone, $flow, true)) !== false && $at <= $index;

        DB::transaction(function () use ($order, $status, $passed) {
            $wasCancelled = $order->status === Order::FULFILMENT_CANCELLED;

            $order->update([
                'status'       => $status,
                'delivered_at' => $passed(Order::FULFILMENT_DELIVERED) ? $order->delivered_at : null,
                'received_at'  => $passed(Order::FULFILMENT_RECEIVED) ? $order->received_at : null,
                'cancelled_at' => null,
            ]);

            // Back on the road: the driver has not delivered it after all.
            DriverSettlement::where('order_id', $order->getKey())
                ->whereNull('payout_id')
                ->update(['delivered_at' => null]);

            // Cancelling took the driver's fee back; un-cancelling a delivery
            // that still has its driver books it again.
            if ($wasCancelled && $order->driver_id !== null && ($driver = Driver::find($order->driver_id))) {
                $this->bookFee($order->fresh(), $driver, null);
            }
        });
    }

    /**
     * Give a delivery to a driver — or to a different one, after a mis-tap.
     *
     * Choosing a driver for an order still at the counter puts it on the road
     * in the same breath: the driver is standing there. Changing the driver of
     * an order already out, or already delivered, only changes the name and
     * moves the fee; it does not send the order back out.
     */
    public function assignDriver(Order $order, Driver $driver, ?int $shiftId = null): void
    {
        if ($order->delivery_method !== 'delivery') {
            throw new RuntimeException('هذا الطلب ليس توصيلاً');
        }

        if (! $driver->active) {
            throw new RuntimeException('السائق غير مفعّل');
        }

        if (in_array($order->status, [Order::FULFILMENT_CANCELLED, Order::FULFILMENT_REFUNDED], true)) {
            throw new RuntimeException('الطلب ملغي — لا يمكن تعيين سائق');
        }

        $settlement = DriverSettlement::where('order_id', $order->getKey())->first();

        // Once the fee has been transferred to a driver, the record of who did
        // this delivery is part of a payment and is not rewritten.
        if ($settlement?->paidOut() && $settlement->driver_id !== $driver->getKey()) {
            throw new RuntimeException('حُوِّلت أجرة هذا التوصيل للسائق — لا يمكن تغيير السائق بعدها');
        }

        $flow   = $order->fulfilmentFlow();
        $before = array_search($order->status, $flow, true) < array_search(Order::FULFILMENT_ON_WAY, $flow, true);

        DB::transaction(function () use ($order, $driver, $shiftId, $before) {
            $order->update(array_filter([
                'driver_id'          => $driver->getKey(),
                // Frozen alongside the link: renaming a driver next month must
                // not rewrite what this delivery said today.
                'driver'             => $driver->snapshot(),
                'driver_assigned_at' => now(),
                'status'             => $before ? Order::FULFILMENT_ON_WAY : null,
            ], fn ($value) => $value !== null));

            $this->bookFee($order->fresh(), $driver, $shiftId);
        });
    }

    /**
     * Give a paid order's money back.
     *
     * Wallet and cash settle on the spot. A transfer cannot: somebody has to
     * send it and keep the receipt, so it becomes a request in the refunds list
     * — the same list the change refunds go through — and the order reads
     * "مسترد" only once that request is marked done.
     *
     * @param  array{holder_name?: ?string, holder_phone?: ?string, notes?: ?string}  $details
     */
    public function refund(Order $order, string $method, array $details = [], ?int $userId = null): ?ChangeRefundRequest
    {
        if (! $order->isPaid()) {
            throw new RuntimeException('الطلب غير مدفوع — لا يوجد مبلغ لاسترداده، ألغِه فقط');
        }

        if ($order->isRefunded()) {
            throw new RuntimeException('هذا الطلب مسترد بالفعل');
        }

        if ($order->hasPendingOrderRefund()) {
            throw new RuntimeException('يوجد طلب استرداد لهذا الطلب بانتظار التحويل');
        }

        return DB::transaction(function () use ($order, $method, $details, $userId) {
            // The order is no longer going anywhere, and a driver who never
            // took it out is not owed for it.
            DriverSettlement::where('order_id', $order->getKey())->whereNull('payout_id')->delete();

            if ($method === 'wallet') {
                $this->refunds->toWallet($order);

                return null;
            }

            if ($method === 'cash') {
                $this->refunds->inCash($order);

                return null;
            }

            if (! array_key_exists($method, ChangeRefundRequest::REFUND_METHODS)) {
                throw new RuntimeException('اختر طريقة الاسترداد');
            }

            $order->update([
                'status'       => Order::FULFILMENT_CANCELLED,
                'cancelled_at' => $order->cancelled_at ?? now(),
            ]);

            return ChangeRefundRequest::create([
                'order_id'        => $order->getKey(),
                'order_reference' => $order->reference,
                'kind'            => ChangeRefundRequest::KIND_ORDER,
                'amount'          => $order->total,
                'holder_name'     => $details['holder_name'] ?: ($order->customer_name ?? ''),
                'holder_phone'    => $details['holder_phone'] ?: ($order->customer_phone ?? ''),
                'refund_method'   => $method,
                'notes'           => $details['notes'] ?? null,
                'created_by'      => $userId,
            ]);
        });
    }

    /** A delivery cannot be closed without somebody having carried it. */
    private function needsDriver(Order $order, string $status): bool
    {
        return $order->delivery_method === 'delivery'
            && $order->driver_id === null
            && in_array($status, [Order::FULFILMENT_ON_WAY, Order::FULFILMENT_RECEIVED, Order::FULFILMENT_DELIVERED], true);
    }

    /**
     * One fee row per order. A different driver takes the fee over rather than
     * being paid a second one; a fee already paid out is left alone.
     */
    private function bookFee(Order $order, Driver $driver, ?int $shiftId): void
    {
        $settlement = DriverSettlement::firstOrNew(['order_id' => $order->getKey()]);

        if ($settlement->exists && $settlement->paidOut()) {
            return;
        }

        $settlement->fill([
            'driver_id'       => $driver->getKey(),
            'shift_id'        => $settlement->shift_id ?? $shiftId,
            'order_reference' => $order->reference,
            'order_total'     => $order->total,
            'delivery_fee'    => (float) $order->delivery_fee,
            'payment_method'  => $order->payment_method,
            'cash_collected'  => false,
        ])->save();
    }
}
