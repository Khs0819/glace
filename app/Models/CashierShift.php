<?php

namespace App\Models;

use App\Services\Checkout\Money;
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
        'expected_cash', 'counted_cash', 'difference', 'totals', 'notes', 'closed_by',
    ];

    protected $casts = [
        'opened_at'     => 'datetime',
        'closed_at'     => 'datetime',
        'opening_float' => 'float',
        'expected_cash' => 'float',
        'counted_cash'  => 'float',
        'difference'    => 'float',
        'totals'        => 'array',
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

        $cash   = (clone $paidCash)->sum('total');
        $change = (clone $paidCash)->sum('change_credited');

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
            + Money::toAgorot($change)
            - Money::toAgorot($refunded);
    }
}
