<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Printing\ReceiptPrinter;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * The counter's receipts.
 *
 * Behind the dashboard's own auth, not the storefront guard: these are staff
 * screens and a customer must never reach one — a receipt carries another
 * customer's name, phone and address.
 */
class ReceiptController extends Controller
{
    public function __construct(private readonly ReceiptPrinter $printer) {}

    /**
     * The printable receipt.
     *
     * `?auto=1` prints on load — that is how the cashier screen turns a new
     * order into paper without anybody clicking. Opening it by hand does not
     * print until asked, so a receipt can be read without wasting a slip.
     */
    public function show(Request $request, string $reference): View
    {
        $order = $this->find($reference);

        $view = view('receipts.order', [
            'doc'       => $this->printer->document($order),
            'width'     => $this->width($request),
            'autoPrint' => $request->boolean('auto'),
        ]);

        // Marked here rather than on the client: a browser that never fires
        // afterprint (or a window closed mid-dialog) would otherwise leave the
        // order looking unprinted forever, and the screen would print it again.
        if ($request->boolean('auto')) {
            $this->printer->markPrinted($order);
        }

        return $view;
    }

    /**
     * Orders the counter still has to deal with.
     *
     * Polled by the cashier screen. Deliberately small and cheap: only what is
     * needed to draw a card and decide whether to print it.
     */
    public function queue(Request $request): JsonResponse
    {
        $since = now()->subHours((int) config('storefront.cashier.lookback_hours', 12));

        $orders = Order::with('items')
            ->where('created_at', '>=', $since)
            ->whereNotIn('status', Order::FINAL_STATUSES)
            ->latest('created_at')
            ->limit(50)
            ->get();

        return response()->json([
            'autoPrint'       => (bool) config('storefront.cashier.auto_print', true),
            'pollSeconds'     => (int) config('storefront.cashier.poll_seconds', 10),
            'networkPrinter'  => $this->printer->networkAvailable(),
            'orders'          => $orders->map(fn (Order $order) => [
                'reference'      => $order->reference,
                'status'         => $order->status,
                'paymentStatus'  => $order->payment_status,
                'paid'           => $order->isPaid(),
                'deliveryMethod' => $order->delivery_method,
                'paymentMethod'  => $order->payment_method,
                'tableNumber'    => $order->table_number,
                'customerName'   => $order->customer_name,
                'customerPhone'  => $order->customer_phone,
                'area'           => $order->address['area'] ?? null,
                'total'          => $order->total,
                'itemCount'      => $order->items->sum('quantity'),
                'createdAt'      => $order->created_at?->toIso8601String(),
                // The screen prints exactly those the printer did not get.
                'printed'        => $order->printed(),
                'printCount'     => $order->print_count,
                'printError'     => $order->print_error,
            ])->values(),
        ]);
    }

    /** Push one order at the network printer again, by hand. */
    public function reprint(string $reference): JsonResponse
    {
        $order = $this->find($reference);

        return response()->json([
            'printed' => $this->printer->printToNetwork($order),
            'error'   => $order->fresh()->print_error,
        ]);
    }

    /** 58 mm and 80 mm are the two rolls that exist; anything else is a typo. */
    private function width(Request $request): int
    {
        $width = (int) $request->integer('width', (int) config('storefront.printer.width') === 32 ? 58 : 80);

        return in_array($width, [58, 80], true) ? $width : 80;
    }

    private function find(string $reference): Order
    {
        return Order::with('items', 'paidBy')
            ->where('reference', $reference)
            ->firstOrFail();
    }
}
