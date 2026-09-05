<?php

namespace App\Services\JawwalPay;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Raised when we never got a usable answer out of the Service Bus — a network
 * failure, a non-2xx status, a body that is not JSON, or a login that did not
 * hand back a token.
 *
 * A *business* rejection (errorCd 89 "Invalid OTP" and friends) is NOT an
 * exception: it is a valid answer that has to be persisted against the payment,
 * so those come back as a failed JawwalPayResponse instead.
 */
class JawwalPayException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly ?string $endpoint = null,
        public readonly ?int $status = null,
        public readonly ?string $body = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public static function transport(string $endpoint, Throwable $previous): self
    {
        return new self(
            "تعذّر الاتصال بخدمة جوال باي ({$endpoint}): {$previous->getMessage()}",
            endpoint: $endpoint,
            previous: $previous,
        );
    }

    public static function httpStatus(string $endpoint, int $status, string $body): self
    {
        return new self(
            "خدمة جوال باي أرجعت حالة {$status} على {$endpoint}",
            endpoint: $endpoint,
            status: $status,
            body: $body,
        );
    }

    public static function malformed(string $endpoint, string $body): self
    {
        return new self(
            "رد غير مفهوم من خدمة جوال باي على {$endpoint}",
            endpoint: $endpoint,
            body: $body,
        );
    }

    public static function notConfigured(string $missing): self
    {
        return new self("إعدادات جوال باي ناقصة: {$missing}");
    }

    /** Caught here rather than spent as a round trip that returns code 56. */
    public static function invalidReceiver(string $number): self
    {
        return new self('رقم محفظة غير صالح: ' . MobileNumber::mask($number));
    }

    /** Wording safe to put in front of a customer. */
    public function customerMessage(): string
    {
        return 'خدمة الدفع غير متاحة حالياً، يرجى المحاولة بعد قليل';
    }

    /**
     * The gateway is down or unreachable — that is a 503, not a 500. The real
     * message stays in the log; the customer gets the safe one.
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->customerMessage(),
        ], 503);
    }
}
