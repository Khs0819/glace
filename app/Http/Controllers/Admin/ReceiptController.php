<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashierShift;
use App\Models\ChangeRefundRequest;
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
     * The open shift's orders, for the cashier screen.
     *
     * Polled every few seconds, so it carries everything a card and the shift
     * strip need in one round trip — the screen never has to ask twice.
     */
    public function queue(Request $request): JsonResponse
    {
        $base = [
            'autoPrint'      => (bool) config('storefront.cashier.auto_print', true),
            'pollSeconds'    => (int) config('storefront.cashier.poll_seconds', 3),
            'networkPrinter' => $this->printer->networkAvailable(),
            'serverTime'     => now()->toIso8601String(),
        ];

        $shift = CashierShift::openFor($request->user());

        /*
         * The board belongs to the shift.
         *
         * Closing a shift sends its cards to the archive (the shift's own page),
         * and a new shift starts from an empty board. With no shift open there
         * is nothing to show — but orders keep arriving from the storefront, so
         * how many are waiting is reported, and the screen can say so instead of
         * looking quiet while customers wait.
         */
        if (! $shift) {
            return response()->json($base + [
                'shiftOpen' => false,
                'waiting'   => Order::whereNotIn('status', Order::FINAL_STATUSES)
                    ->where('created_at', '>=', now()->subHours((int) config('storefront.cashier.lookback_hours', 12)))
                    ->count(),
                'summary'   => null,
                'orders'    => [],
            ]);
        }

        /*
         * Closed orders of this shift are included, not filtered out.
         *
         * The counter needs to look one up as often as it needs to work on
         * one — "did that delivery go out?", a reprint, a customer back at the
         * till. They arrive already printed, so auto-print skips them on its
         * own and no docket comes out twice.
         */
        $orders = Order::with(['items', 'paymentAccount', 'changeRefundRequests'])
            ->where('created_at', '>=', $shift->opened_at)
            ->latest('created_at')
            ->limit(300)
            ->get();

        $summary = $shift->salesSummary();

        return response()->json($base + [
            'shiftOpen' => true,
            'summary'   => [
                'openedAt'     => $shift->opened_at?->format('H:i'),
                'orders'       => $summary['orders'],
                'gross'        => $summary['gross'],
                'net'          => $summary['net'],
                'expectedCash' => $summary['expectedCash'],
            ],
            'orders'    => $orders->map(fn (Order $order) => [
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

                // Which of the shop's accounts the transfer landed in. The
                // method alone does not say which one to go and check.
                'paidToAccount'  => $order->paymentAccount?->holder_name,

                // Frozen on the order, so it still reads correctly for a
                // driver who has since been renamed or removed.
                'driver'         => $order->driver ? [
                    'name'    => $order->driver['name'] ?? null,
                    'phone'   => $order->driver['phone'] ?? null,
                    'company' => $order->driver['company'] ?? null,
                ] : null,
                'needsDriver'    => $order->delivery_method === 'delivery' && $order->driver_id === null,
                'final'          => $order->isFinal(),

                // Shown on the card only when present: a customer's note can be
                // the one thing the counter must not miss.
                'notes'          => filled($order->notes) ? $order->notes : null,
                'deliveryFee'    => (float) $order->delivery_fee,

                // Proof of a transfer, and whether it still needs confirming.
                'requiresReceipt' => $order->requiresReceipt(),
                'hasReceipt'      => filled($order->receipt_image) || filled($order->receipt_note),

                // Cash: what was declared or taken, and the change still owed.
                // The owed figure is computed here so the counter can never be
                // offered a refund of change that already went back.
                'tenderedAmount'   => $order->tendered_amount,
                'changeCredited'   => (float) $order->change_credited,
                'refundableChange' => $order->tendered_amount === null ? 0.0 : max(0.0, round(
                    $order->tendered_amount - $order->total - (float) $order->change_credited
                    - (float) $order->changeRefundRequests
                        ->where('status', '!=', ChangeRefundRequest::STATUS_REJECTED)
                        ->sum('amount'),
                    2,
                )),
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
