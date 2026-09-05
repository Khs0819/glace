<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * The order is in a state that cannot be charged — already paid, cancelled, or
 * out of attempts. Not a validation problem (nothing the customer typed is
 * wrong) and not a gateway problem, so it renders as a conflict.
 */
class PaymentNotAllowedException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 409)
    {
        parent::__construct($message);
    }

    public static function alreadyPaid(): self
    {
        return new self('هذا الطلب مدفوع بالفعل');
    }

    public static function closed(string $statusLabel): self
    {
        return new self("لا يمكن الدفع لطلب حالته: {$statusLabel}");
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $this->getMessage(),
        ], $this->status);
    }
}
