<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\Printing\ReceiptPrinter;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Push a new order to the counter printer.
 *
 * Queued so a slow or unplugged printer never holds up the customer's
 * checkout response. If it fails, ReceiptPrinter records that on the order and
 * the cashier screen picks it up — the receipt is not lost, only late.
 */
class PrintOrderReceipt implements ShouldQueue
{
    use Queueable;

    /** One retry: printers are usually either there or they are not. */
    public int $tries = 2;

    public int $backoff = 5;

    public function __construct(public readonly string $orderId) {}

    public function handle(ReceiptPrinter $printer): void
    {
        $order = Order::with('items')->find($this->orderId);

        // Already on paper, or gone. Either way there is nothing to do — this
        // is what makes a retry safe.
        if ($order === null || $order->printed()) {
            return;
        }

        $printer->printToNetwork($order);
    }
}
