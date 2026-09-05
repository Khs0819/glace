<?php

namespace App\Services\Reporting;

use App\Models\CashierShift;
use App\Models\Order;
use App\Models\TopUpRequest;
use App\Models\WalletTransaction;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * The numbers an accountant has to be able to defend.
 *
 * Two rules run through the whole file:
 *
 *  1. **Sales are counted when the money is settled, not when the order was
 *     placed.** An order taken at 23:50 and paid at 00:10 belongs to the second
 *     day's takings, and a shift's drawer only contains what was handed over
 *     during that shift.
 *
 *  2. **A wallet top-up is not a sale.** It is money received against a future
 *     purchase — a liability until it is spent. Counting it as revenue would
 *     book the same shekel twice: once when it was deposited and again when it
 *     paid for an order. It gets its own section for exactly that reason.
 */
class FinancialReport
{
    public function __construct(
        private readonly CarbonInterface $from,
        private readonly CarbonInterface $to,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'from'          => $this->from->toDateTimeString(),
            'to'            => $this->to->toDateTimeString(),
            'sales'         => $this->sales(),
            'salesByMethod' => $this->salesByMethod(),
            'salesByChannel' => $this->salesByChannel(),
            'deposits'      => $this->deposits(),
            'adjustments'   => $this->adjustments(),
            'shifts'        => $this->shifts(),
            'reconciliation' => $this->reconciliation(),
        ];
    }

    // ─── sales ──────────────────────────────────────────────────────────────

    /**
     * Settled orders in the window.
     *
     * Scoped by `paid_at`, so this is takings rather than order volume.
     */
    public function paidOrders()
    {
        return Order::query()
            ->where('payment_status', Order::STATUS_PAID)
            ->whereBetween('paid_at', [$this->from, $this->to]);
    }

    /** @return array<string, float|int> */
    public function sales(): array
    {
        $row = $this->paidOrders()
            ->selectRaw('COUNT(*) as orders, COALESCE(SUM(subtotal),0) as subtotal, '
                . 'COALESCE(SUM(discount),0) as discount, COALESCE(SUM(delivery_fee),0) as delivery_fee, '
                . 'COALESCE(SUM(total),0) as total, COALESCE(SUM(refunded_amount),0) as refunded')
            ->first();

        $gross    = (float) $row->total;
        $refunded = (float) $row->refunded;

        return [
            'orders'      => (int) $row->orders,
            'subtotal'    => round((float) $row->subtotal, 2),
            'discount'    => round((float) $row->discount, 2),
            'deliveryFee' => round((float) $row->delivery_fee, 2),
            'gross'       => round($gross, 2),
            'refunded'    => round($refunded, 2),
            // What the shop actually kept — the figure that reconciles to cash.
            'net'         => round($gross - $refunded, 2),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function salesByMethod(): array
    {
        return $this->paidOrders()
            ->selectRaw('payment_method, COUNT(*) as orders, COALESCE(SUM(total),0) as total, '
                . 'COALESCE(SUM(refunded_amount),0) as refunded')
            ->groupBy('payment_method')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'method'   => $row->payment_method,
                'label'    => self::PAYMENT_LABELS[$row->payment_method] ?? $row->payment_method,
                'orders'   => (int) $row->orders,
                'total'    => round((float) $row->total, 2),
                'refunded' => round((float) $row->refunded, 2),
                'net'      => round((float) $row->total - (float) $row->refunded, 2),
                // Wallet spending is not new money: it was banked when the
                // top-up was approved, and is drawn down here.
                'isNewMoney' => $row->payment_method !== 'wallet',
            ])
            ->all();
    }

    /** Where the order came from — the counter, a bag, or a driver. */
    public function salesByChannel(): array
    {
        return $this->paidOrders()
            ->selectRaw('delivery_method, COUNT(*) as orders, COALESCE(SUM(total),0) as total')
            ->groupBy('delivery_method')
            ->get()
            ->map(fn ($row) => [
                'channel' => $row->delivery_method,
                'label'   => self::CHANNEL_LABELS[$row->delivery_method] ?? $row->delivery_method,
                'orders'  => (int) $row->orders,
                'total'   => round((float) $row->total, 2),
            ])
            ->all();
    }

    // ─── deposits ───────────────────────────────────────────────────────────

    /**
     * Wallet money: taken in, spent, and still owed.
     *
     * `outstanding` is the shop's liability — credit customers have paid for
     * and not yet used. It is a balance, not a flow, so it is read as it stands
     * now rather than over the window.
     */
    public function deposits(): array
    {
        $approved = TopUpRequest::where('status', TopUpRequest::STATUS_APPROVED)
            ->whereBetween('reviewed_at', [$this->from, $this->to]);

        $byMethod = (clone $approved)
            ->selectRaw('method, COUNT(*) as count, COALESCE(SUM(amount),0) as total')
            ->groupBy('method')
            ->get()
            ->map(fn ($row) => [
                'method' => $row->method,
                'label'  => self::PAYMENT_LABELS[$row->method] ?? $row->method,
                'count'  => (int) $row->count,
                'total'  => round((float) $row->total, 2),
            ])
            ->all();

        $spent = WalletTransaction::where('type', WalletTransaction::TYPE_DEBIT)
            ->whereBetween('created_at', [$this->from, $this->to])
            ->sum('amount');

        return [
            'approved'    => round((float) (clone $approved)->sum('amount'), 2),
            'count'       => (clone $approved)->count(),
            'byMethod'    => $byMethod,
            'pending'     => round((float) TopUpRequest::where('status', TopUpRequest::STATUS_PENDING)->sum('amount'), 2),
            'spent'       => round((float) $spent, 2),
            // Live balance across every wallet: what the shop still owes.
            'outstanding' => round((float) DB::table('wallets')->sum('balance'), 2),
        ];
    }

    // ─── what reduces the take ──────────────────────────────────────────────

    /**
     * Discounts, refunds and cancellations — the three ways money leaves
     * without a sale, and the three worth a second pair of eyes.
     */
    public function adjustments(): array
    {
        $discounted = Order::whereBetween('created_at', [$this->from, $this->to])
            ->where('discount', '>', 0);

        $byCoupon = (clone $discounted)
            ->selectRaw('coupon_code, COUNT(*) as uses, COALESCE(SUM(discount),0) as total')
            ->whereNotNull('coupon_code')
            ->groupBy('coupon_code')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($row) => [
                'code'  => $row->coupon_code,
                'uses'  => (int) $row->uses,
                'total' => round((float) $row->total, 2),
            ])
            ->all();

        $cancelled = Order::whereBetween('created_at', [$this->from, $this->to])
            ->where('status', Order::FULFILMENT_CANCELLED);

        $refunded = Order::whereBetween('refunded_at', [$this->from, $this->to])
            ->where('refunded_amount', '>', 0);

        return [
            'discountTotal'  => round((float) (clone $discounted)->sum('discount'), 2),
            'discountOrders' => (clone $discounted)->count(),
            'byCoupon'       => $byCoupon,

            'cancelledOrders' => (clone $cancelled)->count(),
            // What the cancelled orders would have been worth, so the scale of
            // lost revenue is visible rather than just a count.
            'cancelledValue'  => round((float) (clone $cancelled)->sum('total'), 2),

            'refundedOrders' => (clone $refunded)->count(),
            'refundedTotal'  => round((float) (clone $refunded)->sum('refunded_amount'), 2),
        ];
    }

    // ─── shifts ─────────────────────────────────────────────────────────────

    /** @return array<int, array<string, mixed>> */
    public function shifts(): array
    {
        return CashierShift::with('user')
            ->whereBetween('opened_at', [$this->from, $this->to])
            ->orderByDesc('opened_at')
            ->get()
            ->map(fn (CashierShift $shift) => [
                'id'         => $shift->id,
                'cashier'    => $shift->user?->name,
                'openedAt'   => $shift->opened_at?->format('d/m/Y H:i'),
                'closedAt'   => $shift->closed_at?->format('d/m/Y H:i'),
                'open'       => $shift->open(),
                'float'      => round($shift->opening_float, 2),
                'expected'   => $shift->expected_cash,
                'counted'    => $shift->counted_cash,
                'difference' => $shift->difference,
                'orders'     => $shift->orders()->count(),
            ])
            ->all();
    }

    /**
     * The one figure that has to hold: cash the system says was taken, against
     * cash actually counted into the drawer.
     *
     * A persistent gap in one direction is the signal worth acting on; a shift
     * that was never closed is called out separately, because an uncounted
     * drawer is not the same as a balanced one.
     */
    public function reconciliation(): array
    {
        $closed = CashierShift::whereBetween('opened_at', [$this->from, $this->to])
            ->whereNotNull('closed_at');

        return [
            'shiftsClosed'  => (clone $closed)->count(),
            'shiftsOpen'    => CashierShift::whereBetween('opened_at', [$this->from, $this->to])
                ->whereNull('closed_at')->count(),
            'expectedCash'  => round((float) (clone $closed)->sum('expected_cash'), 2),
            'countedCash'   => round((float) (clone $closed)->sum('counted_cash'), 2),
            'difference'    => round((float) (clone $closed)->sum('difference'), 2),
            'shortShifts'   => (clone $closed)->where('difference', '<', -0.01)->count(),
            'overShifts'    => (clone $closed)->where('difference', '>', 0.01)->count(),
        ];
    }

    public const PAYMENT_LABELS = [
        'cash'          => 'نقداً',
        'visa'          => 'بطاقة',
        'wallet'        => 'محفظة',
        'jawwal'        => 'جوال باي',
        'jawwal-manual' => 'جوال باي (تحويل)',
        'bop'           => 'بنك فلسطين',
        'paypal'        => 'PayPal',
    ];

    public const CHANNEL_LABELS = [
        'dine-in'  => 'داخل المحل',
        'pickup'   => 'استلام',
        'delivery' => 'توصيل',
    ];
}
