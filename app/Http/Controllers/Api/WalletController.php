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

    /**
     * The statement, paginated.
     *
     * `GET /wallet` carries the same rows inline, which is fine for a wallet a
     * week old and not for one two years old — it loads every row ever written
     * to render a balance. This is the endpoint the history screen should use,
     * and the one the storefront was already calling.
     */
    public function transactions(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->integer('perPage', 20)));

        $transactions = $this->wallet->walletFor($request->user())
            ->transactions()
            ->paginate($perPage, ['*'], 'page', max(1, (int) $request->integer('page', 1)));

        $rows = collect($transactions->items())->map($this->transaction(...))->values();

        return response()->json([
            // Two keys, one list. `items` is this codebase's pagination
            // envelope; `transactions` is what GET /wallet calls the same rows,
            // and the storefront reads that name there. The contract never
            // specified this endpoint, so until the frontend says which it
            // wants, answering to both costs nothing and a wrong guess costs a
            // release.
            'items'        => $rows,
            'transactions' => $rows,
            'total'        => $transactions->total(),
            'page'         => $transactions->currentPage(),
            'perPage'      => $transactions->perPage(),
            'totalPages'   => $transactions->lastPage(),
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
     * Check the balance covers an amount. **This no longer moves money.**
     *
     * It used to, and that cost a real customer real credit. An order paid
     * with `wallet` is debited inside order creation — in the same database
     * transaction as the order row, so the two can never part company. A
     * storefront that called this first and then created the order was
     * therefore charging twice on success, and once for nothing whenever the
     * order failed after the debit went through. The second is what happened:
     * credit taken, no order, refunded by hand from the dashboard.
     *
     * Making it a check rather than deleting it keeps that storefront working
     * unchanged — it calls this, sees success, creates the order, and is
     * debited exactly once — while removing every way for money to move
     * without an order attached to it. There is no legitimate reason for a
     * client to spend credit outside a purchase, so the capability is gone
     * rather than guarded.
     *
     * 409 when the balance is short, matching what the old debit answered, so
     * a caller's existing error branch still fires at the same moment.
     */
    public function deduct(Request $request): JsonResponse
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'label'  => ['nullable', 'string', 'max:190'],
        ], [
            'amount.required' => 'المبلغ مطلوب',
        ]);

        $wallet  = $this->wallet->walletFor($request->user());
        $balance = Money::toAgorot($wallet->balance);
        $amount  = Money::toAgorot($data['amount']);

        if ($balance < $amount) {
            // Advisory only: the balance is checked again under a row lock
            // when the order is created, which is the check that decides.
            return response()->json(['message' => 'الرصيد غير كافٍ'], 409);
        }

        return response()->json([
            'balance'    => $wallet->balance,
            'sufficient' => true,
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
