<?php

namespace App\Filament\Pages;

use App\Models\CashierShift;
use App\Models\ChangeRefundRequest;
use App\Models\Driver;
use App\Models\DriverSettlement;
use App\Models\Order;
use App\Services\Checkout\Money;
use App\Services\Drivers\DriverPayoutService;
use App\Services\Orders\CashLimits;
use App\Services\Orders\OrderFulfilment;
use App\Services\Printing\ReceiptPrinter;
use App\Services\Storefront\OrderRefundService;
use App\Services\Storefront\WalletService;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use RuntimeException;

/**
 * The counter screen.
 *
 * One place a cashier can stand in front of all shift: new orders arrive on
 * their own, print themselves, and can be moved along or settled without
 * navigating anywhere. Everything on it is one click from the top of the page,
 * because the person using it has a queue of customers in front of them.
 *
 * Printing is belt and braces. The server pushes each order at the network
 * printer as it lands; this screen prints anything that did not make it, which
 * covers an unplugged printer, a paper jam or no printer at all.
 */
class CashierBoard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';
    protected static ?string $navigationLabel = 'شاشة الكاشير';
    protected static ?string $navigationGroup = 'الطلبات';
    protected static ?string $title = 'شاشة الكاشير';
    protected static ?int $navigationSort = 0;
    protected static string $view = 'filament.pages.cashier-board';
    protected static ?string $slug = 'cashier';

    /** Orders waiting on someone: what the badge counts. */
    public static function getNavigationBadge(): ?string
    {
        $count = Order::whereNotIn('status', Order::FINAL_STATUSES)
            ->where('created_at', '>=', now()->subHours((int) config('storefront.cashier.lookback_hours', 12)))
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    /** The shift this cashier has open, if any. */
    public function shift(): ?CashierShift
    {
        return CashierShift::openFor(auth()->user());
    }

    public function networkPrinter(): bool
    {
        return app(ReceiptPrinter::class)->networkAvailable();
    }

    /** @return array<string, mixed> */
    public function settings(): array
    {
        return [
            'poll'      => (int) config('storefront.cashier.poll_seconds', 10),
            'autoPrint' => (bool) config('storefront.cashier.auto_print', true),
            'width'     => (int) config('storefront.printer.width') === 32 ? 58 : 80,

            // Decided up front so a print click can start printing at once. A
            // browser only lets a page print straight from the click; asking
            // the server first and printing afterwards gets silently blocked.
            'networkPrinter' => $this->networkPrinter(),
        ];
    }

    // ─── shift control ──────────────────────────────────────────────────────

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('openShift')
                ->label('فتح وردية')
                ->icon('heroicon-o-lock-open')
                ->color('success')
                ->visible(fn () => $this->shift() === null)
                ->form([
                    Forms\Components\TextInput::make('opening_float')
                        ->label('النقد الافتتاحي في الدرج')
                        ->numeric()
                        ->default(0)
                        ->minValue(0)
                        ->suffix('₪')
                        // Without it the closing count is short by whatever was
                        // already in the drawer, and looks like a discrepancy.
                        ->helperText('المبلغ الموجود في الدرج قبل بدء البيع'),
                ])
                ->action(function (array $data) {
                    CashierShift::create([
                        'user_id'       => auth()->id(),
                        'opened_at'     => now(),
                        'opening_float' => $data['opening_float'] ?? 0,
                    ]);

                    Notification::make()->title('تم فتح الوردية')->success()->send();

                    // The board is scoped to the shift: redraw it now.
                    $this->dispatch('cashier-refresh');
                }),

            \Filament\Actions\Action::make('closeShift')
                ->label('إغلاق الوردية')
                ->icon('heroicon-o-lock-closed')
                ->color('danger')
                ->visible(fn () => $this->shift() !== null)
                ->modalHeading('إغلاق الوردية وتسليم الدرج')
                ->modalDescription(function () {
                    $shift = $this->shift();

                    if (! $shift) {
                        return null;
                    }

                    // Computed, not typed: the figures an accountant signs off
                    // on come from the orders, before anyone counts the drawer.
                    $s = $shift->salesSummary();
                    $f = fn ($value) => number_format((float) $value, 2) . ' ₪';

                    return new HtmlString(
                        '<div style="line-height:2">'
                        . 'إجمالي المبيعات: <b>' . $f($s['gross']) . '</b><br>'
                        . 'المرتجعات: ' . $f($s['refunds']) . '<br>'
                        . 'صافي المبيعات: <b style="font-size:1.15em">' . $f($s['net']) . '</b><br>'
                        . 'طلبات الوردية: ' . $s['orders'] . ' — المدفوعة: ' . $s['paidOrders'] . '<br>'
                        . 'باقٍ حُوّل لمحافظ الزبائن: ' . $f($s['changeToWallet']) . '<br>'
                        . 'طلبات استرداد الباقي: ' . $f($s['changeRefunds'])
                        . ($s['changePending'] > 0 ? ' (معلّق منها: ' . $f($s['changePending']) . ')' : '') . '<br>'
                        . 'المتوقع في الدرج: <b>' . $f($s['expectedCash']) . '</b> (يشمل النقد الافتتاحي)'
                        . '</div>'
                    );
                })
                ->form([
                    Forms\Components\TextInput::make('counted_cash')
                        ->label('النقد المعدود فعلياً')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->suffix('₪')
                        // Typed before the expected figure is revealed in the
                        // result, so the count is a count and not a copy.
                        ->helperText('اعدد الدرج وأدخل الرقم كما هو'),

                    Forms\Components\Textarea::make('notes')
                        ->label('ملاحظات')
                        ->rows(2)
                        ->placeholder('سبب الفرق إن وُجد'),
                ])
                ->action(fn (array $data) => $this->closeShift($data)),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function closeShift(array $data): void
    {
        $shift = $this->shift();

        if (! $shift) {
            return;
        }

        $expected = $shift->expectedCashAgorot();
        $counted  = Money::toAgorot($data['counted_cash']);

        // Frozen onto the row: a refund tomorrow must not rewrite a shift that
        // has already been signed off.
        $shift->update([
            'closed_at'     => now(),
            'closed_by'     => auth()->id(),
            'expected_cash' => Money::toDecimal($expected),
            'counted_cash'  => Money::toDecimal($counted),
            'difference'    => Money::toDecimal($counted - $expected),
            'totals'        => $shift->takings(),
            // Sales frozen next to the count, for the archive and the reports.
            'summary'       => $shift->salesSummary(),
            'notes'         => $data['notes'] ?? null,
        ]);

        $difference = Money::toDecimal($counted - $expected);
        $net        = number_format((float) ($shift->fresh()->summary['net'] ?? 0), 2);

        // The board is scoped to the shift: its cards go to the archive now.
        $this->dispatch('cashier-refresh');

        Notification::make()
            ->title('تم إغلاق الوردية — صافي المبيعات ' . $net . ' ₪')
            ->body(match (true) {
                abs($difference) < 0.01 => 'الدرج مطابق تماماً.',
                $difference > 0         => "زيادة {$difference} ₪ عن المتوقع.",
                default                 => 'عجز ' . abs($difference) . ' ₪ عن المتوقع.',
            })
            ->color(abs($difference) < 0.01 ? 'success' : 'warning')
            ->persistent()
            ->send();
    }

    // ─── actions on one order ───────────────────────────────────────────────

    /**
     * Take payment for an order settled at the counter.
     *
     * Only cash and card: everything else is settled by a gateway, a wallet
     * debit or a receipt somebody reviews, and none of those are the cashier's
     * to declare.
     */
    public function markPaid(string $reference): void
    {
        $order = Order::where('reference', $reference)->firstOrFail();

        if ($order->isPaid()) {
            return;
        }

        if (! $order->collectedByHand()) {
            Notification::make()
                ->title('هذا الطلب لا يُدفع عند الكاشير')
                ->body('طريقة الدفع: ' . $order->payment_method)
                ->warning()
                ->send();

            return;
        }

        $shift = $this->shift();

        if (! $shift) {
            // Without a shift the money has nowhere to be counted, and the
            // closing report would be short by exactly this amount.
            Notification::make()
                ->title('افتح وردية أولاً')
                ->body('لا يمكن استلام النقد بدون وردية مفتوحة، وإلا لن يظهر في تقرير الإغلاق.')
                ->danger()
                ->send();

            return;
        }

        if (! $this->withinCashLimits($order, (float) ($order->tendered_amount ?? $order->total))) {
            return;
        }

        $change = $order->changeDue();

        DB::transaction(function () use ($order, $shift, $change) {
            $order->update([
                'payment_status' => Order::STATUS_PAID,
                'paid_at'        => now(),
                'paid_by'        => auth()->id(),
                'shift_id'       => $shift->getKey(),
            ]);

            if ($change <= 0) {
                return;
            }

            // Now, and not a moment earlier: the credit is change from cash
            // that is in the drawer. Crediting it at checkout would hand out
            // store credit for money nobody had yet handed over.
            //
            // Inside the transaction so the two cannot part company — an order
            // marked paid without the credit is a customer short of their
            // change, with nothing to show it was ever owed.
            app(WalletService::class)->credit(
                $order->customer,
                Money::toAgorot($change),
                'باقي طلب #' . $order->reference,
                'cash',
                null,
                $order,
            );

            $order->update([
                'change_credited'    => $change,
                'change_credited_at' => now(),
            ]);
        });

        if ($change > 0) {
            Notification::make()
                ->title('تم استلام الدفع')
                ->body('أُضيف الباقي ' . number_format($change, 2) . ' ₪ إلى محفظة العميل — لا تُعِد نقداً.')
                ->success()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()->title('تم استلام الدفع')->success()->send();
    }

    /**
     * Accept cash payment with a specific tendered amount and create a change
     * refund request if the customer overpaid.
     *
     * This marks the order as paid immediately (the cashier has the money in
     * hand) and records a separate refund request for the change — reviewed
     * at shift close rather than transferred on the spot.
     */
    public function markPaidWithChange(string $reference, float $tendered, array $refundData): void
    {
        $order = Order::with('customer')->where('reference', $reference)->firstOrFail();

        if ($order->isPaid()) {
            return;
        }

        if (! $order->collectedByHand()) {
            Notification::make()
                ->title('هذا الطلب لا يُدفع عند الكاشير')
                ->warning()
                ->send();

            return;
        }

        $shift = $this->shift();

        if (! $shift) {
            Notification::make()
                ->title('افتح وردية أولاً')
                ->danger()
                ->send();

            return;
        }

        if ($tendered < $order->total) {
            Notification::make()->title('المبلغ المستلم أقل من إجمالي الطلب')->danger()->send();

            return;
        }

        if (! $this->withinCashLimits($order, $tendered)) {
            return;
        }

        $change = max(0.0, round($tendered - $order->total, 2));

        DB::transaction(function () use ($order, $shift, $tendered, $change, $refundData) {
            $order->update([
                'payment_status'  => Order::STATUS_PAID,
                'paid_at'         => now(),
                'paid_by'         => auth()->id(),
                'shift_id'        => $shift->getKey(),
                'tendered_amount' => $tendered,
            ]);

            if ($change > 0 && ! empty($refundData['refund_method'])) {
                ChangeRefundRequest::create([
                    'order_id'        => $order->getKey(),
                    'order_reference' => $order->reference,
                    'amount'          => $change,
                    'holder_name'     => $refundData['holder_name'] ?? $order->customer_name ?? '',
                    'holder_phone'    => $refundData['holder_phone'] ?? $order->customer_phone ?? '',
                    'refund_method'   => $refundData['refund_method'],
                    'notes'           => $refundData['notes'] ?? null,
                    'created_by'      => auth()->id(),
                ]);
            }
        });

        if ($change > 0) {
            Notification::make()
                ->title('تم استلام الدفع')
                ->body('تم إنشاء طلب استرداد الباقي ' . number_format($change, 2) . ' ₪')
                ->success()
                ->persistent()
                ->send();

            return;
        }

        Notification::make()->title('تم استلام الدفع')->success()->send();
    }

    /** Move an order one step along its own ladder. */
    public function advance(string $reference, string $status): void
    {
        $order = Order::where('reference', $reference)->firstOrFail();

        try {
            app(OrderFulfilment::class)->advance($order, $status);
        } catch (RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('تم تحديث الحالة')->success()->send();
    }

    /** Drivers the cashier can hand this order to, busiest state included. */
    public function drivers(): array
    {
        return Driver::active()
            ->withExists(['activeOrders as busy'])
            ->orderBy('busy')          // free hands first
            ->orderBy('name')
            ->get()
            ->map(fn (Driver $driver) => [
                'id'      => $driver->getKey(),
                'name'    => $driver->name,
                'company' => $driver->company,
                'phone'   => $driver->phone,
                'busy'    => (bool) $driver->busy,
                'status'  => $driver->busy ? 'في توصيل' : 'متاح',
            ])
            ->values()
            ->all();
    }

    /**
     * Hand a delivery to a driver, and put it on the road in the same breath.
     *
     * One action rather than two, because the two always happen together at
     * the counter — the driver is standing there — and splitting them is how
     * an order ends up assigned but never marked as gone.
     */
    public function assignDriver(string $reference, int $driverId): void
    {
        $order  = Order::where('reference', $reference)->firstOrFail();
        $driver = Driver::find($driverId);

        if (! $driver) {
            Notification::make()->title('السائق غير موجود')->danger()->send();

            return;
        }

        $changing = $order->driver_id !== null && $order->driver_id !== $driver->getKey();

        try {
            app(OrderFulfilment::class)->assignDriver($order, $driver, $this->shift()?->getKey());
        } catch (RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()
            ->title($changing ? 'تم تغيير السائق إلى ' . $driver->name : 'في الطريق مع ' . $driver->name)
            ->body($driver->phone)
            ->success()
            ->send();
    }

    /** Seat a dine-in order that arrived without a table. */
    public function setTable(string $reference, string $table): void
    {
        Order::where('reference', $reference)->firstOrFail()
            ->update(['table_number' => trim($table) ?: null]);

        Notification::make()->title('تم تحديد الطاولة')->success()->send();
    }

    /** @return array<int, string> */
    public function nextStatuses(string $reference): array
    {
        return Order::where('reference', $reference)->firstOrFail()->allowedNextStatuses();
    }

    /** Live figures for the strip along the top of the screen. */
    public function shiftSummary(): array
    {
        $shift = $this->shift();

        if (! $shift) {
            return [];
        }

        return [
            'opened'   => $shift->opened_at?->format('H:i'),
            'expected' => Money::toDecimal($shift->expectedCashAgorot()),
            'takings'  => $shift->takings(),
            'orders'   => $shift->orders()->count(),
        ];
    }

    /** Refund an order's total to the customer's wallet, by decision. */
    public function refundToWallet(string $reference): void
    {
        $order = Order::with('customer')->where('reference', $reference)->firstOrFail();

        if ($order->customer === null || $order->total <= 0) {
            Notification::make()->title('لا يمكن الاسترداد لهذا الطلب')->danger()->send();

            return;
        }

        try {
            app(OrderRefundService::class)->toWallet($order);
        } catch (RuntimeException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();

            return;
        }

        Notification::make()->title('تم الاسترداد إلى محفظة الزبون')->success()->send();
    }

    /**
     * Ask for the change of an already-paid cash order to be transferred back.
     *
     * The amount is not an input. It is what was received, less the total,
     * less whatever already went to the wallet or to an earlier request —
     * computed on the server, so the same change cannot be returned twice.
     *
     * @param  array<string, mixed>  $refundData
     */
    public function createStandaloneRefund(string $reference, array $refundData): void
    {
        $order  = Order::with('customer')->where('reference', $reference)->firstOrFail();
        $amount = $order->refundableChange();

        if ($amount <= 0) {
            Notification::make()
                ->title('لا يوجد باقٍ مستحق لهذا الطلب')
                ->body('إما أن المبلغ المستلم يساوي الإجمالي، أو أن الباقي أُعيد مسبقاً.')
                ->warning()
                ->send();

            return;
        }

        if (empty($refundData['refund_method'])) {
            Notification::make()->title('اختر وسيلة الاسترداد')->danger()->send();

            return;
        }

        $maxChange = (float) config('storefront.limits.max_change', 199);

        if ($amount > $maxChange) {
            Notification::make()
                ->title('المبلغ أكبر من الحد الأقصى للاسترداد')
                ->body("الحد الأقصى {$maxChange} ₪، والمبلغ المحسوب {$amount} ₪. راجع المبلغ المستلم أولاً.")
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        ChangeRefundRequest::create([
            'order_id'        => $order->getKey(),
            'order_reference' => $order->reference,
            'amount'          => $amount,
            'holder_name'     => $refundData['holder_name'] ?? $order->customer_name ?? '',
            'holder_phone'    => $refundData['holder_phone'] ?? $order->customer_phone ?? '',
            'refund_method'   => $refundData['refund_method'],
            'notes'           => $refundData['notes'] ?? null,
            'created_by'      => auth()->id(),
        ]);

        Notification::make()
            ->title('تم إنشاء طلب الاسترداد')
            ->body('مبلغ ' . number_format($amount, 2) . ' ₪ — يُحوَّل ويُرفع إشعاره من قسم طلبات الاسترداد')
            ->success()
            ->persistent()
            ->send();

        $this->dispatch('cashier-refresh');
    }

    /**
     * Send a receipt straight to the network printer, no browser window.
     *
     * Falls back gracefully: the JS side opens the browser path if this
     * returns success = false.
     *
     * @return array{success: bool, error: string|null}
     */
    public function printDirect(string $reference): array
    {
        $order = Order::with('items', 'paidBy')->where('reference', $reference)->firstOrFail();

        $printer = app(ReceiptPrinter::class);

        if (! $printer->networkAvailable()) {
            return ['success' => false, 'error' => 'الطابعة الشبكية غير متصلة'];
        }

        $success = $printer->printToNetwork($order);

        return [
            'success' => $success,
            'error'   => $success ? null : ($order->fresh()->print_error ?? 'خطأ غير معروف'),
        ];
    }

    /** Alert the cashier about the print result. */
    public function sendPrintAlert(string $title, string $body, string $type): void
    {
        // A status, not just a colour: it is what gives the notice its icon, and
        // a failed print must look like a failure at a glance.
        Notification::make()
            ->title($title)
            ->body($body)
            ->status(in_array($type, ['success', 'warning', 'danger', 'info'], true) ? $type : 'info')
            ->duration(6000)
            ->send();
    }

    /**
     * Take cash, and send any change to the customer's wallet.
     *
     * The cashier types what the customer handed over; whatever is above the
     * total becomes store credit instead of coins. With nothing above the total
     * this is simply taking the payment. A guest has no wallet, so a guest's
     * change has to go back as a transfer request instead.
     */
    public function markPaidToWallet(string $reference, float $tendered): void
    {
        $order = Order::with('customer')->where('reference', $reference)->firstOrFail();

        if ($order->isPaid()) {
            return;
        }

        if ($order->payment_method !== 'cash') {
            $this->markPaid($reference);

            return;
        }

        if (! $this->shift()) {
            Notification::make()
                ->title('افتح وردية أولاً')
                ->body('لا يمكن استلام النقد بدون وردية مفتوحة، وإلا لن يظهر في تقرير الإغلاق.')
                ->danger()
                ->send();

            return;
        }

        // An empty box means exactly the total was handed over.
        if ($tendered <= 0) {
            $tendered = (float) $order->total;
        }

        if ($tendered < $order->total) {
            Notification::make()->title('المبلغ المستلم أقل من إجمالي الطلب')->danger()->send();

            return;
        }

        if (! $this->withinCashLimits($order, $tendered)) {
            return;
        }

        if ($tendered > $order->total && $order->customer === null) {
            Notification::make()
                ->title('الزبون بدون حساب — لا توجد محفظة')
                ->body('استخدم «استلام + طلب استرداد» لتحويل الباقي له.')
                ->warning()
                ->persistent()
                ->send();

            return;
        }

        // What was actually handed over replaces whatever the customer declared
        // in the app; markPaid then credits exactly the difference, once.
        $order->update(['tendered_amount' => round($tendered, 2)]);

        $this->markPaid($reference);
    }

    /**
     * Confirm a transfer after reading its receipt, from the details window.
     *
     * Only for the methods proven by a receipt, and only when there is one to
     * read: confirming a payment nobody has seen proof of is how money that
     * never arrived gets counted as sales.
     */
    public function confirmTransferPayment(string $reference): void
    {
        $order = Order::where('reference', $reference)->firstOrFail();

        if ($order->isPaid()) {
            return;
        }

        if (! $order->requiresReceipt()) {
            Notification::make()->title('هذا الطلب لا يُدفع بتحويل')->warning()->send();

            return;
        }

        if (in_array($order->status, [Order::FULFILMENT_CANCELLED, Order::FULFILMENT_REFUNDED], true)) {
            Notification::make()->title('الطلب ملغي — لا يمكن تأكيد دفعه')->warning()->send();

            return;
        }

        if (blank($order->receipt_image) && blank($order->receipt_note)) {
            Notification::make()->title('لا يوجد إشعار دفع لمراجعته')->danger()->send();

            return;
        }

        $order->update([
            'payment_status' => Order::STATUS_PAID,
            'paid_at'        => now(),
            'paid_by'        => auth()->id(),
            'shift_id'       => $this->shift()?->getKey(),
        ]);

        Notification::make()->title('تم تأكيد الدفع')->success()->send();

        $this->dispatch('cashier-refresh');
    }

    /**
     * Everything about one order, for the details window on the board.
     *
     * @return array<string, mixed>
     */
    public function orderDetails(string $reference): array
    {
        $order = Order::with(['items', 'paymentAccount', 'changeRefundRequests', 'paidBy'])
            ->where('reference', $reference)
            ->firstOrFail();

        return [
            'reference'      => $order->reference,
            'status'         => $order->status,
            'createdAt'      => $order->created_at?->format('d/m/Y H:i'),
            'deliveryMethod' => $order->delivery_method,
            'tableNumber'    => $order->table_number,
            'customerName'   => $order->customer_name,
            'customerPhone'  => $order->customer_phone,
            'address'        => $order->address,
            'notes'          => filled($order->notes) ? $order->notes : null,
            'captainNote'    => filled($order->captain_note) ? $order->captain_note : null,
            'scheduledFor'   => $order->scheduled_for?->format('d/m/Y H:i'),

            'items' => $order->items->map(fn ($item) => [
                'name'        => $item->product_name,
                'qty'         => (int) $item->quantity,
                'unit'        => (float) $item->unit_price,
                'total'       => (float) $item->line_total,
                'description' => $item->description,
            ])->values()->all(),

            'subtotal'    => (float) $order->subtotal,
            'discount'    => (float) $order->discount,
            'couponCode'  => $order->coupon_code,
            'deliveryFee' => (float) $order->delivery_fee,
            'total'       => (float) $order->total,

            'paymentMethod' => $order->payment_method,
            'paid'          => $order->isPaid(),
            'paidAt'        => $order->paid_at?->format('d/m/Y H:i'),
            'paidBy'        => $order->paidBy?->name,
            'paidToAccount' => $order->paymentAccount?->holder_name,

            // The proof of a transfer, read here instead of on the orders page.
            'receiptImage'       => $order->receiptImageUrl(),
            'receiptNote'        => $order->receipt_note,
            'senderAccountName'  => $order->sender_account_name,
            'canConfirmTransfer' => ! $order->isPaid()
                && $order->requiresReceipt()
                && ! in_array($order->status, [Order::FULFILMENT_CANCELLED, Order::FULFILMENT_REFUNDED], true)
                && (filled($order->receipt_image) || filled($order->receipt_note)),

            'tenderedAmount'   => $order->tendered_amount,
            'changeCredited'   => (float) $order->change_credited,
            'refundableChange' => $order->refundableChange(),
            'refunds'          => $order->changeRefundRequests->map(fn (ChangeRefundRequest $request) => [
                'amount'  => (float) $request->amount,
                'method'  => $request->methodLabel(),
                'status'  => $request->status,
                'receipt' => $request->transferReceiptUrl(),
            ])->values()->all(),

            'driver' => $order->driver,
        ];
    }

    /**
     * What each driver is owed, and for which orders.
     *
     * Active drivers are always listed, plus any switched-off driver who is
     * still owed money — switching someone off must not hide a debt.
     *
     * @return array<int, array<string, mixed>>
     */
    public function driverBalances(): array
    {
        return Driver::query()
            ->where(fn ($query) => $query->where('active', true)->orWhereHas('unpaidSettlements'))
            ->withExists(['activeOrders as busy'])
            ->with(['unpaidSettlements' => fn ($query) => $query->latest('id')])
            ->orderBy('name')
            ->get()
            ->map(fn (Driver $driver) => [
                'id'      => $driver->getKey(),
                'name'    => $driver->name,
                'company' => $driver->company,
                'phone'   => $driver->phone,
                'busy'    => (bool) $driver->busy,
                'balance' => round((float) $driver->unpaidSettlements->sum('delivery_fee'), 2),
                'orders'  => $driver->unpaidSettlements->map(fn (DriverSettlement $row) => [
                    'reference' => $row->order_reference,
                    'fee'       => (float) $row->delivery_fee,
                    'delivered' => $row->delivered_at !== null,
                    'at'        => $row->created_at?->format('H:i'),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * Change refunds waiting for their transfer.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pendingRefunds(): array
    {
        return ChangeRefundRequest::where('status', ChangeRefundRequest::STATUS_PENDING)
            ->latest()
            ->get()
            ->map(fn (ChangeRefundRequest $request) => [
                'id'          => $request->getKey(),
                'reference'   => $request->order_reference,
                'kind'        => $request->kindLabel(),
                'wholeOrder'  => $request->isOrderRefund(),
                'amount'      => (float) $request->amount,
                'holderName'  => $request->holder_name,
                'holderPhone' => $request->holder_phone,
                'method'      => $request->methodLabel(),
                'notes'       => $request->notes,
                'at'          => $request->created_at?->format('H:i'),
            ])
            ->values()
            ->all();
    }

    /**
     * Both side panels in one call, so the screen refreshes them with a single
     * request instead of two.
     *
     * @return array{drivers: array<int, array<string, mixed>>, refunds: array<int, array<string, mixed>>}
     */
    public function panels(): array
    {
        return [
            'drivers' => $this->driverBalances(),
            'refunds' => $this->pendingRefunds(),
        ];
    }

    // ─── windows opened from the board with $wire.mountAction() ─────────────
    //
    // Filament actions rather than hand-built modals, because these take a file:
    // a transfer receipt is uploaded here, and the cashier never has to go to
    // another page to do it.

    public function createDriverAction(): Action
    {
        return Action::make('createDriver')
            ->label('سائق جديد')
            ->modalHeading('إضافة سائق')
            ->modalSubmitActionLabel('حفظ السائق')
            ->form([
                Forms\Components\TextInput::make('name')->label('اسم السائق')->required()->maxLength(120),
                Forms\Components\TextInput::make('phone')->label('رقم الهاتف')->tel()->required()->maxLength(20),
                Forms\Components\TextInput::make('company')->label('شركة التوصيل')->maxLength(120),
            ])
            ->action(function (array $data) {
                Driver::create([
                    'name'    => $data['name'],
                    'phone'   => $data['phone'],
                    'company' => $data['company'] ?? null,
                    'active'  => true,
                ]);

                Notification::make()->title('تمت إضافة السائق ' . $data['name'])->success()->send();

                $this->dispatch('cashier-refresh');
            });
    }

    public function completeRefundAction(): Action
    {
        return Action::make('completeRefund')
            ->modalHeading(fn (array $arguments) => ChangeRefundRequest::find($arguments['id'] ?? null)?->isOrderRefund()
                ? 'تأكيد استرداد قيمة الطلب'
                : 'تأكيد تحويل الباقي للزبون')
            ->modalDescription(function (array $arguments) {
                $request = ChangeRefundRequest::find($arguments['id'] ?? null);

                return $request
                    ? 'تحويل ' . number_format($request->amount, 2) . ' ₪ إلى ' . $request->holder_name
                        . ' (' . $request->holder_phone . ') عبر ' . $request->methodLabel()
                    : null;
            })
            ->modalSubmitActionLabel('تم التحويل')
            ->form([
                Forms\Components\FileUpload::make('transfer_receipt')
                    ->label('إشعار التحويل')
                    ->image()
                    ->disk('public')
                    ->directory('refund-receipts')
                    ->maxSize(4096)
                    ->required(),
            ])
            ->action(function (array $data, array $arguments) {
                $request = ChangeRefundRequest::find($arguments['id'] ?? null);

                if (! $request || ! $request->isPending()) {
                    Notification::make()->title('طلب الاسترداد غير متاح')->warning()->send();

                    return;
                }

                $request->complete($data['transfer_receipt'] ?? null, auth()->id());

                Notification::make()
                    ->title($request->isOrderRefund() ? 'تم تأكيد الاسترداد — الطلب مسترد' : 'تم تأكيد تحويل الباقي')
                    ->success()
                    ->send();

                $this->dispatch('cashier-refresh');
            });
    }

    /**
     * Put an order back a step after a mistake — a delivery marked delivered
     * before anyone took it, a cancellation pressed on the wrong card.
     * Open to the counter as well as the manager: the person who made the
     * mistake is the one standing there to fix it.
     */
    public function correctStatusAction(): Action
    {
        return Action::make('correctStatus')
            ->modalHeading('تصحيح حالة الطلب')
            ->modalDescription(function (array $arguments) {
                $order = Order::where('reference', $arguments['reference'] ?? '')->first();

                return $order ? "الطلب {$order->reference} — الحالة الحالية: {$order->status}" : null;
            })
            ->modalSubmitActionLabel('إرجاع الحالة')
            ->form(fn (array $arguments) => [
                Forms\Components\Select::make('status')
                    ->label('إرجاع الطلب إلى')
                    ->options(function () use ($arguments) {
                        $order = Order::where('reference', $arguments['reference'] ?? '')->first();
                        $steps = $order?->correctableStatuses() ?? [];

                        return array_combine($steps, $steps);
                    })
                    ->required()
                    ->native(false),
            ])
            ->action(function (array $data, array $arguments) {
                $order = Order::where('reference', $arguments['reference'] ?? '')->first();

                if (! $order) {
                    return;
                }

                try {
                    app(OrderFulfilment::class)->correct($order, $data['status']);
                } catch (RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()->title('تم تصحيح الحالة إلى «' . $data['status'] . '»')->success()->send();

                $this->dispatch('cashier-refresh');
            });
    }

    /**
     * Give a paid order's money back — the button "مسترد" in the status list
     * pretended to be. Wallet and cash settle now; a transfer goes to the
     * refunds list and the order reads "مسترد" once it has been sent.
     */
    public function refundOrderAction(): Action
    {
        $order = fn (array $arguments) => Order::with('customer')->where('reference', $arguments['reference'] ?? '')->first();

        return Action::make('refundOrder')
            ->modalHeading('استرداد قيمة الطلب')
            ->modalDescription(fn (array $arguments) => ($o = $order($arguments))
                ? "الطلب {$o->reference} — المبلغ " . number_format($o->total, 2) . ' ₪'
                : null)
            ->modalSubmitActionLabel('استرداد')
            ->form(fn (array $arguments) => [
                Forms\Components\Select::make('method')
                    ->label('طريقة الاسترداد')
                    ->options(array_filter([
                        'wallet' => $order($arguments)?->customer ? 'إلى محفظة الزبون (فوري)' : null,
                        'cash'   => 'نقداً من الدرج (فوري)',
                        'jawwal' => 'تحويل جوال باي',
                        'bop'    => 'تحويل بنك فلسطين',
                        'palpay' => 'تحويل بال باي',
                    ]))
                    ->required()
                    ->live()
                    ->native(false),

                Forms\Components\TextInput::make('holder_name')
                    ->label('اسم صاحب الحساب')
                    ->default(fn () => $order($arguments)?->customer_name)
                    ->required(fn (Forms\Get $get) => ! in_array($get('method'), ['wallet', 'cash', null], true))
                    ->visible(fn (Forms\Get $get) => ! in_array($get('method'), ['wallet', 'cash', null], true)),

                Forms\Components\TextInput::make('holder_phone')
                    ->label('رقم الحساب / المحفظة')
                    ->default(fn () => $order($arguments)?->customer_phone)
                    ->required(fn (Forms\Get $get) => ! in_array($get('method'), ['wallet', 'cash', null], true))
                    ->visible(fn (Forms\Get $get) => ! in_array($get('method'), ['wallet', 'cash', null], true)),

                Forms\Components\Textarea::make('notes')->label('سبب الاسترداد')->rows(2),
            ])
            ->action(function (array $data, array $arguments) use ($order) {
                $record = $order($arguments);

                if (! $record) {
                    return;
                }

                try {
                    $request = app(OrderFulfilment::class)->refund($record, $data['method'], $data, auth()->id());
                } catch (RuntimeException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();

                    return;
                }

                Notification::make()
                    ->title($request
                        ? 'أُضيف طلب الاسترداد إلى قائمة طلبات الاسترداد — يُغلق بعد رفع إشعار التحويل'
                        : 'تم الاسترداد — الطلب مسترد')
                    ->success()
                    ->persistent()
                    ->send();

                $this->dispatch('cashier-refresh');
            });
    }

    /**
     * The counter's two cash ceilings, shown as a notice rather than thrown:
     * a refusal here is an instruction to the cashier, not an error.
     */
    private function withinCashLimits(Order $order, float $received): bool
    {
        try {
            app(CashLimits::class)->assertCanReceive(auth()->user(), $order, $received);
        } catch (RuntimeException $e) {
            Notification::make()
                ->title('لا يمكن استلام هذا المبلغ')
                ->body($e->getMessage())
                ->danger()
                ->persistent()
                ->send();

            return false;
        }

        return true;
    }

    public function payDriverAction(): Action
    {
        return Action::make('payDriver')
            ->modalHeading('تحويل رصيد السائق')
            ->modalDescription(function (array $arguments) {
                $driver = Driver::find($arguments['driver'] ?? null);

                return $driver
                    ? $driver->name . ' — المستحق: ' . number_format($driver->balance(), 2) . ' ₪'
                    : null;
            })
            ->modalSubmitActionLabel('تم التحويل')
            ->form([
                Forms\Components\FileUpload::make('receipt')
                    ->label('إشعار التحويل')
                    ->image()
                    ->disk('public')
                    ->directory('driver-payouts')
                    ->maxSize(4096)
                    ->required(),
                Forms\Components\Textarea::make('notes')->label('ملاحظات')->rows(2),
            ])
            ->action(function (array $data, array $arguments) {
                $driver = Driver::find($arguments['driver'] ?? null);

                $payout = $driver
                    ? app(DriverPayoutService::class)->pay($driver, $data['receipt'] ?? null, $data['notes'] ?? null, auth()->id())
                    : null;

                if (! $payout) {
                    Notification::make()->title('لا يوجد رصيد مستحق لهذا السائق')->warning()->send();

                    return;
                }

                Notification::make()
                    ->title('تم تحويل ' . number_format($payout->amount, 2) . ' ₪ إلى ' . $driver->name)
                    ->success()
                    ->send();

                $this->dispatch('cashier-refresh');
            });
    }
}
