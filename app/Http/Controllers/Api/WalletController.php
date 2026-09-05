<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\TopUpRequestRequest;
use App\Models\TopUpRequest;
use App\Models\WalletTransaction;
use App\Services\Checkout\Money;
use App\Services\Storefront\ReceiptStorage;
use App\Services\Storefront\WalletService;
use App\Support\MediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Store credit (handoff 14).
 *
 * Note what is missing: there is no endpoint here that approves a top-up. That
 * is deliberate and is the whole point of the rewrite — approval happens in the
 * Filament dashboard and nowhere a customer can reach.
 */
class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $wallet,
        private readonly ReceiptStorage $receipts,
    ) {}

    public function show(Request $request): JsonResponse
    {
        $wallet = $this->wallet->walletFor($request->user());

        return response()->json([
            'balance'      => $wallet->balance,
            'transactions' => $wallet->transactions->map($this->transaction(...))->values(),
        ]);
    }

    public function topUpRequests(Request $request): JsonResponse
    {
        return response()->json([
            'requests' => $request->user()->topUpRequests->map($this->topUpRequest(...))->values(),
        ]);
    }

    public function storeTopUpRequest(TopUpRequestRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Stored as a real file on the public disk, never base64 in a column
        // (handoff 14).
        $data['receiptImage'] = $this->receipts->store($request->file('receiptImage'), 'topups');

        $topUp = $this->wallet->requestTopUp($request->user(), $data);

        // 201 with "قيد المراجعة": submitting adds nothing to the balance.
        return response()->json(['request' => $this->topUpRequest($topUp)], 201);
    }

    /**
     * Spend credit. Kept because handoff 14 specifies it, but an order paid
     * with `wallet` debits inside order creation instead — one transaction, so
     * a debit can never succeed against an order that then fails to save.
     */
    public function deduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'label'  => ['nullable', 'string', 'max:190'],
        ], [
            'amount.required' => 'المبلغ مطلوب',
        ]);

        try {
            $this->wallet->debit(
                $request->user(),
                Money::toAgorot($data['amount']),
                $data['label'] ?? 'خصم من الرصيد',
            );
        } catch (RuntimeException $e) {
            // 409, not 422: the request was valid, the balance simply is not.
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'balance' => $this->wallet->walletFor($request->user())->fresh()->balance,
        ]);
    }

    /** @return array<string, mixed> */
    private function transaction(WalletTransaction $transaction): array
    {
        return array_filter([
            'id'           => $transaction->id,
            'date'         => $transaction->created_at?->toIso8601String(),
            'amount'       => $transaction->amount,
            'type'         => $transaction->type,
            'label'        => $transaction->label,
            'method'       => $transaction->method,
            'receiptImage' => MediaUrl::resolve($transaction->receipt_image),
        ], static fn ($value) => $value !== null);
    }

    /** @return array<string, mixed> */
    private function topUpRequest(TopUpRequest $request): array
    {
        return array_filter([
            'id'           => $request->id,
            'amount'       => $request->amount,
            'method'       => $request->method,
            'status'       => $request->status,
            'createdAt'    => $request->created_at?->toIso8601String(),
            'receiptImage' => MediaUrl::resolve($request->receipt_image),
            'receiptNote'  => $request->receipt_note,
            'phone'        => $request->phone,
        ], static fn ($value) => $value !== null);
    }
}
