<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Checkout\OrderPaymentService;
use App\Services\JawwalPay\ErrorCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Jawwal Pay pay-to-merchant, in two calls: ask for a code, then charge with it.
 *
 * Neither call takes an amount — it always comes off the order.
 */
class OrderPaymentController extends Controller
{
    public function __construct(private readonly OrderPaymentService $payments) {}

    public function requestOtp(Request $request, Order $order): JsonResponse
    {
        $this->guard($request, $order);

        $data = $request->validate([
            'token'  => ['required', 'string'],
            'wallet' => ['required', 'string', 'max:30'],
        ], [
            'wallet.required' => 'أدخل رقم محفظة جوال باي',
        ]);

        $payment = $this->payments->requestOtp($order, $data['wallet']);

        return $this->respond(
            $order,
            $payment,
            $payment->isOtpSent(),
            'تم إرسال رمز التحقق إلى رقم محفظتك',
        );
    }

    public function confirm(Request $request, Order $order): JsonResponse
    {
        $this->guard($request, $order);

        $data = $request->validate([
            'token' => ['required', 'string'],
            'otp'   => ['required', 'string', 'min:4', 'max:12'],
        ], [
            'otp.required' => 'أدخل رمز التحقق',
            'otp.min'      => 'رمز التحقق غير مكتمل',
        ]);

        $payment = $this->payments->confirm($order, $data['otp']);

        return $this->respond(
            $order,
            $payment,
            $payment->isPaid(),
            'تم الدفع بنجاح',
        );
    }

    /**
     * A gateway "no" is a 422, not a 500: the request was fine, the answer was
     * negative, and the storefront needs the reason to put on screen.
     */
    private function respond(Order $order, Payment $payment, bool $ok, string $successMessage): JsonResponse
    {
        return response()->json([
            'success'      => $ok,
            'status'       => $payment->status,
            'message'      => $ok ? $successMessage : ErrorCode::customerMessage($payment->error_code),
            // Kept in the payload on purpose: it is the first thing support
            // will ask the customer for.
            'errorCode'    => $ok ? null : $payment->error_code,
            'attemptsLeft' => max(0, Payment::MAX_CONFIRM_ATTEMPTS - $payment->confirm_attempts),
            'order'        => new OrderResource($order->fresh()->load('items', 'payments')),
        ], $ok ? 200 : 422);
    }

    /** 404, not 403 — a wrong token must not confirm the order exists. */
    private function guard(Request $request, Order $order): void
    {
        abort_unless($order->tokenMatches($request->input('token')), 404);
    }
}
