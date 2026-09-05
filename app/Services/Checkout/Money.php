<?php

namespace App\Services\Checkout;

/**
 * Shekel amounts as integer agorot.
 *
 * The catalog stores decimal(8,2) and Eloquent hands them back as floats. Summing
 * those float by float drifts, and a total that is a hair off is not a rounding
 * curiosity here — it is the number sent to the payment gateway and printed on
 * the receipt. So everything is converted once on the way in, added as integers,
 * and converted back once on the way out.
 */
class Money
{
    public static function toAgorot(float|int|string $shekels): int
    {
        return (int) round(((float) $shekels) * 100);
    }

    public static function toDecimal(int $agorot): float
    {
        return round($agorot / 100, 2);
    }

    /** "12.50" — for display and for anything that must not re-enter float math. */
    public static function format(int $agorot): string
    {
        return number_format($agorot / 100, 2, '.', '');
    }
}
