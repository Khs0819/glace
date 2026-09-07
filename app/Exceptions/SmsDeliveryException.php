<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * The message could not be handed to the gateway.
 *
 * Split out from a bare RuntimeException for one reason: an SMS provider
 * refusing us is not a fault in this application, and answering 500 says it
 * is. That mislabels the incident for whoever reads the logs, and it tells the
 * customer "something broke" when the truthful answer is "we cannot send you a
 * code right now" — which is a different instruction: wait, or call the shop.
 *
 * 503, because it is temporary and not the caller's doing.
 *
 * Extends RuntimeException so every existing `throws` contract and every test
 * expecting one still holds.
 */
class SmsDeliveryException extends RuntimeException
{
    public function __construct(
        string $message,
        /** Safe to show a customer; $message may name provider internals. */
        public readonly string $customerMessage = 'تعذّر إرسال رمز التحقق حالياً، حاول بعد قليل',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function render(Request $request): JsonResponse
    {
        // The real reason goes to the log, never to the response: it carries
        // provider codes and account details a customer has no use for.
        return response()->json(['message' => $this->customerMessage], 503);
    }
}
