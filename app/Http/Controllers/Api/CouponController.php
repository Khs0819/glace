<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Checkout\Money;
use App\Services\Storefront\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Coupon preview for the cart screen (handoff 11).
 *
 * This is a preview and nothing more — no discount is reserved and no code is
 * spent here. The authoritative check happens again inside POST /orders, which
 * never trusts a `discount` sent by the client.
 */
class CouponController extends Controller
{
    public function __construct(private readonly CouponService $coupons) {}

    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code'     => ['required', 'string', 'max:40'],
            'subtotal' => ['required', 'numeric', 'min:0', 'max:100000'],
        ], [
            'code.required'     => 'أدخل كود الخصم',
            'subtotal.required' => 'قيمة الطلب مطلوبة',
        ]);

        $result = $this->coupons->evaluate(
            $data['code'],
            Money::toAgorot($data['subtotal']),
            $request->user(),
        );

        // 200 either way — an unknown code is an answer, not an error
        // (handoff 11).
        return response()->json(array_filter([
            'valid'    => $result['valid'],
            'code'     => $result['valid'] ? $result['coupon']->code : null,
            'discount' => Money::toDecimal($result['discount']),
            'message'  => $result['message'],
        ], static fn ($value) => $value !== null));
    }
}
