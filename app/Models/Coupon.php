<?php

namespace App\Models;

use App\Services\Checkout\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * A discount code, owned by the dashboard rather than the bundle.
 *
 * The frontend used to hold the whole table in cartStore.ts, which meant every
 * code in the shop was readable by anyone who opened devtools (handoff 11).
 */
class Coupon extends Model
{
    public const TYPE_FIXED   = 'fixed';
    public const TYPE_PERCENT = 'percent';

    protected $fillable = [
        'code', 'type', 'value', 'max_discount', 'min_subtotal',
        'expires_at', 'usage_limit', 'per_customer_limit', 'used_count', 'active',
    ];

    protected $casts = [
        'value'        => 'float',
        'max_discount' => 'float',
        'min_subtotal' => 'float',
        'expires_at'   => 'datetime',
        'active'       => 'boolean',
    ];

    /** Codes are stored and compared upper-case, so "glace10" is not a second coupon. */
    public function setCodeAttribute(?string $value): void
    {
        $this->attributes['code'] = $value === null ? null : mb_strtoupper(trim($value));
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', mb_strtoupper(trim($code)))->first();
    }

    public function expired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function exhausted(): bool
    {
        return $this->usage_limit !== null && $this->used_count >= $this->usage_limit;
    }

    /**
     * How much this takes off, in agorot, given a subtotal in agorot.
     *
     * Percent coupons are computed here rather than trusted from the client —
     * handoff 11 is explicit that the response carries a final shekel figure
     * whichever kind the coupon is.
     */
    public function discountFor(int $subtotalAgorot): int
    {
        $discount = $this->type === self::TYPE_PERCENT
            ? (int) round($subtotalAgorot * $this->value / 100)
            : Money::toAgorot($this->value);

        if ($this->max_discount !== null) {
            $discount = min($discount, Money::toAgorot($this->max_discount));
        }

        // A discount may zero an order out but must never turn it into a payout.
        return max(0, min($discount, $subtotalAgorot));
    }
}
