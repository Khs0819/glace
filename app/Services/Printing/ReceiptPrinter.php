<?php

namespace App\Services\Printing;

use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Prints an order's receipt, and remembers that it did.
 *
 * Two paths, by design (and by the shop's choice):
 *
 *   1. The network printer, when one is configured. The server pushes the
 *      receipt the moment the order lands; nobody has to be looking at a screen.
 *   2. The cashier screen, which picks up anything the printer did not get and
 *      prints it through the browser. Also how a reprint happens.
 *
 * `printed_at` is what keeps the two from fighting: the cashier screen only
 * offers to print orders that carry no timestamp, so a receipt the network
 * printer already produced is not silently duplicated.
 *
 * A printing failure never fails the order. A customer whose payment went
 * through must not see an error because a printer was unplugged — the failure
 * is recorded on the order and the cashier screen picks it up instead.
 */
class ReceiptPrinter
{
    public function __construct(
        private readonly EscPosPrinter $printer,
        /** @var array<string, mixed> */
        private readonly array $shop = [],
    ) {}

    public function document(Order $order): ReceiptDocument
    {
        return new ReceiptDocument($order->loadMissing('items', 'paidBy'), $this->shop);
    }

    /**
     * Try the network printer. Returns whether paper came out.
     *
     * Never throws: see the class note.
     */
    public function printToNetwork(Order $order): bool
    {
        if (! $this->printer->enabled()) {
            return false;
        }

        try {
            $this->printer->print($this->document($order));
        } catch (Throwable $e) {
            // Recorded on the order so the cashier screen can show that this
            // one still needs paper, and why.
            $order->forceFill([
                'print_error' => $e->getMessage(),
            ])->saveQuietly();

            Log::warning('receipt print failed', [
                'order' => $order->reference,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        $this->markPrinted($order);

        return true;
    }

    /** Record that a receipt was produced, by whichever path. */
    public function markPrinted(Order $order): void
    {
        $order->forceFill([
            'printed_at'  => now(),
            'print_count' => $order->print_count + 1,
            'print_error' => null,
        ])->saveQuietly();
    }

    public function networkAvailable(): bool
    {
        return $this->printer->enabled();
    }
}
