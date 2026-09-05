<?php

namespace App\Services\Storefront;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Services\Checkout\Money;

/**
 * Discount codes (handoff 11).
 *
 * The frontend used to hold the whole table in its bundle and compute the
 * discount itself. Now it sends a code and this decides — including at order
 * time, where the code is re-checked from scratch rather than the client's
 * `discount` being believed. That re-check is the point of the whole file:
 * anything else lets a customer name their own price.
 */
class CouponService
{
    /**
     * Evaluate a code against a subtotal, in agorot.
     *
     * Never throws and never 404s: an invalid code is a normal, expected answer
     * (`valid: false`) rather than an error, because the customer is simply
     * typing into a box.
     *
     * @return array{coupon: ?Coupon, valid: bool, discount: int, message: string}
     */
    public function evaluate(?string $code, int $subtotalAgorot, ?Customer $customer = null): array
    {
        $code = trim((string) $code);

        if ($code === '') {
            return $this->reject('أدخل كود الخصم');
        }

        $coupon = Coupon::findByCode($code);

        // One message for "no such code", "switched off" and "expired": telling
        // them apart just makes guessing at the code space cheaper.
        if (! $coupon || ! $coupon->active || $coupon->expired() || $coupon->exhausted()) {
            return $this->reject('الكوبون غير صالح أو منتهي');
        }

        if ($coupon->min_subtotal !== null && $subtotalAgorot < Money::toAgorot($coupon->min_subtotal)) {
            return $this->reject(
                'الحد الأدنى لاستخدام هذا الكوبون ' . rtrim(rtrim(number_format($coupon->min_subtotal, 2), '0'), '.') . ' ₪',
            );
        }

        if ($customer && $this->exceededPersonalLimit($coupon, $customer)) {
            return $this->reject('لقد استخدمت هذا الكوبون من قبل');
        }

        $discount = $coupon->discountFor($subtotalAgorot);

        if ($discount <= 0) {
            return $this->reject('الكوبون غير صالح أو منتهي');
        }

        return [
            'coupon'   => $coupon,
            'valid'    => true,
            'discount' => $discount,
            'message'  => 'تم تطبيق الكوبون',
        ];
    }

    /**
     * Count one redemption. Called only once an order actually exists, so an
     * abandoned checkout does not burn a single-use code.
     */
    public function redeem(Coupon $coupon): void
    {
        $coupon->increment('used_count');
    }

    private function exceededPersonalLimit(Coupon $coupon, Customer $customer): bool
    {
        if ($coupon->per_customer_limit === null) {
            return false;
        }

        // A cancelled order does not count against the customer — they never
        // got the discount.
        $used = Order::where('customer_id', $customer->getKey())
            ->where('coupon_code', $coupon->code)
            ->whereNotIn('status', [Order::FULFILMENT_CANCELLED])
            ->count();

        return $used >= $coupon->per_customer_limit;
    }

    /** @return array{coupon: null, valid: false, discount: 0, message: string} */
    private function reject(string $message): array
    {
        return ['coupon' => null, 'valid' => false, 'discount' => 0, 'message' => $message];
    }
}
