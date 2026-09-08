<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Storefront\WalletService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Find orders labelled refunded that never recorded a refund, and settle them.
 *
 * These exist because the orders table used to refund by changing the status
 * and nothing else. The row then reads "مسترد" in the list while every
 * financial figure still counts it as a completed sale — and no amount of
 * fixing the code repairs the rows already written that way.
 *
 * Two shapes, and they need opposite treatment, which is why this asks rather
 * than guessing:
 *
 *   The money DID reach the customer (a wallet credit exists for the order):
 *   only the order columns are missing. Fill them in.
 *
 *   The money never moved: the order was only labelled. Either credit it now
 *   or clear the label — both are decisions, not repairs.
 *
 * Read-only without --fix.
 */
class RepairRefunds extends Command
{
    protected $signature = 'orders:repair-refunds
        {--fix : Write the corrections instead of only listing them}
        {--credit : For rows where no money moved, refund to the wallet now}';

    protected $description = 'Repair orders marked refunded that carry no refund amount';

    public function handle(WalletService $wallet): int
    {
        $broken = Order::with('customer')
            ->where('status', Order::FULFILMENT_REFUNDED)
            ->where(fn ($query) => $query->whereNull('refunded_at')->orWhere('refunded_amount', '<=', 0))
            ->get();

        if ($broken->isEmpty()) {
            $this->components->info('Every order marked refunded records its refund.');

            return self::SUCCESS;
        }

        $rows = [];

        foreach ($broken as $order) {
            // A credit already logged against this order is the evidence that
            // the money did reach the customer.
            $credited = $order->customer
                ? $order->customer->wallet?->transactions()
                    ->where('order_id', $order->getKey())
                    ->where('type', 'credit')
                    ->sum('amount')
                : 0;

            $rows[] = [
                'order'    => $order->reference,
                'total'    => $order->total,
                'credited' => (float) $credited,
                'action'   => (float) $credited > 0 ? 'record it' : 'no money moved',
            ];
        }

        $this->table(['order', 'total', 'credited', 'what is needed'], $rows);

        if (! $this->option('fix')) {
            $this->newLine();
            $this->comment('  Re-run with --fix to record the refunds that already happened.');
            $this->comment('  Add --credit to also refund the rows where no money ever moved.');

            return self::FAILURE;
        }

        $recorded = 0;
        $credited = 0;

        foreach ($broken as $order) {
            $already = $order->customer
                ? (float) ($order->customer->wallet?->transactions()
                    ->where('order_id', $order->getKey())
                    ->where('type', 'credit')
                    ->sum('amount') ?? 0)
                : 0.0;

            if ($already > 0) {
                // The refund happened; only the bookkeeping is missing.
                $order->update([
                    'refunded_amount' => $already,
                    'refunded_at'     => $order->updated_at,
                    'refund_method'   => Order::REFUND_WALLET,
                ]);
                $recorded++;

                continue;
            }

            if (! $this->option('credit')) {
                continue;
            }

            if ($order->customer === null || $order->total <= 0) {
                $this->warn("  {$order->reference}: no customer to credit — refund in cash from the dashboard.");

                continue;
            }

            DB::transaction(function () use ($order, $wallet) {
                $wallet->credit(
                    $order->customer,
                    \App\Services\Checkout\Money::toAgorot($order->total),
                    'استرداد طلب #' . $order->reference,
                    'wallet',
                    null,
                    $order,
                );

                $order->update([
                    'refunded_amount' => $order->total,
                    'refunded_at'     => now(),
                    'refund_method'   => Order::REFUND_WALLET,
                ]);
            });

            $credited++;
        }

        $this->newLine();
        $this->components->info("Recorded {$recorded} refund(s) that had already been paid.");

        if ($credited > 0) {
            $this->components->info("Credited {$credited} order(s) that had never been refunded.");
        }

        return self::SUCCESS;
    }
}
