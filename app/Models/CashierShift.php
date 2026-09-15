<?php

namespace App\Models;

use App\Services\Checkout\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One cashier's session at the till.
 *
 * The point of the row is accountability: every cash payment taken while it is
 * open is stamped with this shift, so "how much cash came in" is never a query
 * over a guessed time range — it is a figure attached to a named person, and
 * the gap between what the system expected and what was actually counted is a
 * recorded number rather than an argument at the end of the night.
 *
 * Closing totals are FROZEN onto the row rather than recomputed on every view.
 * A refund processed tomorrow must not quietly rewrite a shift that was signed
 * off yesterday.
 */
class CashierShift extends Model
{
    protected $fillable = [
        'user_id', 'opened_at', 'closed_at', 'opening_float',
        'expected_cash', 'counted_cash', 'difference', 'totals', 'summary', 'notes', 'closed_by',
    ];

    protected $casts = [
        'opened_at'     => 'datetime',
        'closed_at'     => 'datetime',
        'opening_float' => 'float',
        'expected_cash' => 'float',
        'counted_cash'  => 'float',
        'difference'    => 'float',
        'totals'        => 'array',
        'summary'       => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'shift_id');
    }

    public function open(): bool
    {
        return $this->closed_at === null;
    }

    /** The shift this cashier currently has open, if any. */
    public static function openFor(User $user): ?self
    {
        return static::where('user_id', $user->getKey())->whereNull('closed_at')->latest('id')->first();
    }

    /**
     * Takings so far, by payment method, in shekels.
     *
     * Only orders whose money was actually collected during this shift count —
     * an order placed now and paid tomorrow belongs to tomorrow's drawer.
     *
     * @return array<string, float>
     */
    public function takings(): array
    {
        return $this->orders()
            ->where('payment_status', Order::STATUS_PAID)
            ->selectRaw('payment_method, SUM(total) as sum_total')
            ->groupBy('payment_method')
            ->pluck('sum_total', 'payment_method')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    /**
     * What should be in the drawer: the opening float plus cash taken.
     *
     * Card, wallet and transfer payments never touch the drawer, so only `cash`
     * counts here — that is the whole distinction the count is checking.
     *
     * `change_credited` is added because that money is physically in the
     * drawer too. A customer who hands over 100 for a 36 order and takes the
     * 64 as store credit leaves all 100 behind; counting only the order total
     * would show a 64 surplus at closing and send somebody hunting for an
     * error that never happened. The shop owes that 64 — but it owes it from
     * the wallet, not from this drawer.
     */
    public function expectedCashAgorot(): int
    {
        $paidCash = $this->orders()
            ->where('payment_status', Order::STATUS_PAID)
            ->where('payment_method', 'cash');

        // What was physically handed over: the note, when one was recorded,
        // otherwise the total. Any change was returned to the wallet or as a
        // transfer later — never out of this drawer — so all of it stays here.
        $cash = (clone $paidCash)->sum(DB::raw('COALESCE(tendered_amount, total)'));

        // Change handed back in notes is the one exception: it did leave.
        $cashChange = ChangeRefundRequest::whereIn('order_id', (clone $paidCash)->select('id'))
            ->where('refund_method', 'cash')
            ->where('status', ChangeRefundRequest::STATUS_COMPLETED)
            ->sum('amount');

        /*
         * Only refunds actually handed back in notes reduce the drawer.
         *
         * An order refunded as store credit moved onto the customer's balance
         * and took nothing out of the till, so subtracting it would report a
         * surplus that is really just the money still sitting there — and send
         * somebody looking for an error that never happened.
         *
         * Rows refunded before `refund_method` existed are read as cash, which
         * is what the old code assumed.
         */
        $refunded = $this->orders()
            ->where('payment_method', 'cash')
            ->where(fn ($query) => $query
                ->whereNull('refund_method')
                ->orWhere('refund_method', Order::REFUND_CASH))
            ->sum('refunded_amount');

        return Money::toAgorot($this->opening_float)
            + Money::toAgorot($cash)
            - Money::toAgorot($cashChange)
            - Money::toAgorot($refunded);
    }

    /**
     * Orders placed while this shift was open. The shift's archive.
     *
     * By creation time, not by `shift_id`: `shift_id` marks who took the cash,
     * and an order paid by transfer or wallet never gets one — but it was still
     * this shift's order to deal with.
     */
    public function windowOrders(): Builder
    {
        return Order::query()
            ->where('created_at', '>=', $this->opened_at)
            ->when($this->closed_at, fn (Builder $query) => $query->where('created_at', '<=', $this->closed_at));
    }

    /**
     * The sales figures for closing, in shekels.
     *
     * Sales are counted by when the money settled (`paid_at`) inside the shift,
     * across every method — cash, card, transfer, wallet — so net sales is the
     * shift's takings rather than just the drawer. Net is gross less refunds.
     *
     * @return array<string, float|int>
     */
    public function salesSummary(): array
    {
        $end  = $this->closed_at ?? now();
        $paid = Order::query()
            ->where('payment_status', Order::STATUS_PAID)
            ->whereBetween('paid_at', [$this->opened_at, $end]);

        $gross   = (float) (clone $paid)->sum('total');
        $refunds = (float) (clone $paid)->sum('refunded_amount');

        $changeRequests = ChangeRefundRequest::whereIn('order_id', $this->windowOrders()->select('id'))
            ->where('status', '!=', ChangeRefundRequest::STATUS_REJECTED);

        return [
            'orders'          => $this->windowOrders()->count(),
            'paidOrders'      => (clone $paid)->count(),
            'gross'           => round($gross, 2),
            'discounts'       => round((float) (clone $paid)->sum('discount'), 2),
            'deliveryFees'    => round((float) (clone $paid)->sum('delivery_fee'), 2),
            'refunds'         => round($refunds, 2),
            'net'             => round($gross - $refunds, 2),
            'changeToWallet'  => round((float) (clone $paid)->sum('change_credited'), 2),
            'changeRefunds'   => round((float) (clone $changeRequests)->sum('amount'), 2),
            'changePending'   => round((float) (clone $changeRequests)->where('status', ChangeRefundRequest::STATUS_PENDING)->sum('amount'), 2),
            'expectedCash'    => Money::toDecimal($this->expectedCashAgorot()),
        ];
    }
}
