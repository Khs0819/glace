<?php

namespace App\Services\Storefront;

use App\Models\Customer;
use App\Models\Order;
use App\Models\TopUpRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\Checkout\Money;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Store credit (handoff 14).
 *
 * Two rules hold everything else up:
 *
 *  1. The balance only ever moves inside a transaction that also writes the
 *     ledger row explaining the move, with the wallet row locked for the
 *     duration. Two concurrent debits cannot both read the same balance and
 *     both succeed.
 *
 *  2. Nothing a customer can reach adds credit. A top-up request is inert until
 *     an admin approves it in the dashboard — the frontend used to expose
 *     `approveTopUpRequest` to the browser console, which is precisely the hole
 *     this closes.
 */
class WalletService
{
    public function walletFor(Customer $customer): Wallet
    {
        return Wallet::forCustomer($customer);
    }

    /**
     * Take money off the balance.
     *
     * @param  int  $amountAgorot  must be positive
     *
     * @throws RuntimeException when the balance will not cover it
     */
    public function debit(Customer $customer, int $amountAgorot, string $label, ?Order $order = null): WalletTransaction
    {
        if ($amountAgorot <= 0) {
            throw new RuntimeException('مبلغ غير صالح');
        }

        return DB::transaction(function () use ($customer, $amountAgorot, $label, $order) {
            $wallet = $this->lock($customer);

            $balance = Money::toAgorot($wallet->balance);

            // The server is the arbiter, never the frontend's copy of the
            // balance (handoff 14).
            if ($balance < $amountAgorot) {
                throw new RuntimeException('الرصيد غير كافٍ');
            }

            return $this->append($wallet, $balance - $amountAgorot, [
                'amount'   => Money::toDecimal($amountAgorot),
                'type'     => WalletTransaction::TYPE_DEBIT,
                'label'    => $label,
                'method'   => 'wallet',
                'order_id' => $order?->getKey(),
            ]);
        });
    }

    /** Put money back — a refund, or a correction made in the dashboard. */
    public function credit(
        Customer $customer,
        int $amountAgorot,
        string $label,
        ?string $method = null,
        ?string $receiptImage = null,
        ?Order $order = null,
    ): WalletTransaction {
        if ($amountAgorot <= 0) {
            throw new RuntimeException('مبلغ غير صالح');
        }

        return DB::transaction(function () use ($customer, $amountAgorot, $label, $method, $receiptImage, $order) {
            $wallet = $this->lock($customer);

            return $this->append($wallet, Money::toAgorot($wallet->balance) + $amountAgorot, [
                'amount'        => Money::toDecimal($amountAgorot),
                'type'          => WalletTransaction::TYPE_CREDIT,
                'label'         => $label,
                'method'        => $method,
                'receipt_image' => $receiptImage,
                'order_id'      => $order?->getKey(),
            ]);
        });
    }

    /**
     * Log a request to add credit. Adds nothing to the balance — that is what
     * approval is for.
     *
     * @param  array<string, mixed>  $data
     */
    public function requestTopUp(Customer $customer, array $data): TopUpRequest
    {
        return $customer->topUpRequests()->create([
            'amount'        => $data['amount'],
            'method'        => $data['method'],
            'status'        => TopUpRequest::STATUS_PENDING,
            'receipt_image' => $data['receiptImage'] ?? null,
            'receipt_note'  => $data['receiptNote'] ?? null,
            'phone'         => PhoneNumber::normalize($data['phone'] ?? null),
        ]);
    }

    /**
     * Approve a top-up: the only path by which a balance goes up on its own.
     *
     * Idempotent — a request that already produced a credit is returned
     * unchanged rather than crediting twice. Double-clicking Approve in the
     * dashboard must not be worth money.
     */
    public function approveTopUp(TopUpRequest $request, ?User $admin = null, ?string $note = null): TopUpRequest
    {
        if ($request->alreadyCredited()) {
            return $request;
        }

        return DB::transaction(function () use ($request, $admin, $note) {
            // Re-read under the lock: two admins hitting Approve at the same
            // moment must not both get past the guard above.
            $fresh = TopUpRequest::whereKey($request->getKey())->lockForUpdate()->first();

            if (! $fresh || $fresh->alreadyCredited()) {
                return $fresh ?? $request;
            }

            $transaction = $this->credit(
                $fresh->customer,
                Money::toAgorot($fresh->amount),
                'شحن رصيد',
                $fresh->method,
                $fresh->receipt_image,
            );

            $fresh->update([
                'status'         => TopUpRequest::STATUS_APPROVED,
                'reviewed_by'    => $admin?->getKey(),
                'reviewed_at'    => now(),
                'review_note'    => $note,
                'transaction_id' => $transaction->getKey(),
            ]);

            return $fresh->fresh();
        });
    }

    public function rejectTopUp(TopUpRequest $request, ?User $admin = null, ?string $note = null): TopUpRequest
    {
        if ($request->alreadyCredited()) {
            return $request;
        }

        $request->update([
            'status'      => TopUpRequest::STATUS_REJECTED,
            'reviewed_by' => $admin?->getKey(),
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);

        return $request->fresh();
    }

    /**
     * Row-level lock. Everything that moves a balance takes this first, so a
     * read-then-write cannot interleave with another one.
     */
    private function lock(Customer $customer): Wallet
    {
        $wallet = Wallet::forCustomer($customer);

        return Wallet::whereKey($wallet->getKey())->lockForUpdate()->first() ?? $wallet;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function append(Wallet $wallet, int $newBalanceAgorot, array $attributes): WalletTransaction
    {
        $balance = Money::toDecimal($newBalanceAgorot);

        $wallet->update(['balance' => $balance]);

        // balance_after is written from the same number the wallet just took,
        // so the statement and the running total cannot disagree later.
        return $wallet->transactions()->create($attributes + ['balance_after' => $balance]);
    }
}
