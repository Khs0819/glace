<?php

namespace App\Services\Orders;

use App\Models\Order;
use App\Models\User;
use RuntimeException;

/**
 * The two ceilings on cash taken at the counter, checked in one place.
 *
 *   max_change — the most change one cash payment may produce, for anyone.
 *   accountant_max_cash — the most cash an accountant may take for one order.
 *   Anything above it is taken by a manager.
 *
 * Every way of taking cash on the cashier screen calls this. The ceilings were
 * first written into each button separately, and one of them ("استلام + طلب
 * استرداد") was missed — it would create a refund of any size.
 */
class CashLimits
{
    public static function maxChange(): float
    {
        return (float) config('storefront.limits.max_change', 199);
    }

    public static function accountantMaxCash(): float
    {
        return (float) config('storefront.limits.accountant_max_cash', 200);
    }

    /**
     * @param  float  $received  what is physically handed over for this order
     *
     * @throws RuntimeException with the sentence to show the cashier
     */
    public function assertCanReceive(?User $user, Order $order, float $received): void
    {
        if ($order->payment_method !== 'cash') {
            return;
        }

        // Checked first: an accountant asked to take 250 needs to be told to
        // call the manager, not that the change is too large.
        if ($user !== null && ! $user->isManager() && $received > self::accountantMaxCash() + 0.001) {
            $limit = self::format(self::accountantMaxCash());

            throw new RuntimeException(
                "المحاسب لا يستلم أكثر من {$limit} ₪ نقداً في الطلب الواحد — يستلمه المدير.",
            );
        }

        $change = round($received - (float) $order->total, 2);

        if ($change > self::maxChange() + 0.001) {
            throw new RuntimeException('الباقي أكبر من الحد الأقصى (' . self::format(self::maxChange()) . ' ₪) — تأكد من المبلغ المستلم.');
        }
    }

    private static function format(float $amount): string
    {
        return rtrim(rtrim(number_format($amount, 2, '.', ''), '0'), '.');
    }
}
