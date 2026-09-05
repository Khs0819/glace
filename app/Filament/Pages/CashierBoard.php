<?php

namespace App\Filament\Pages;

use App\Models\CashierShift;
use App\Models\Order;
use App\Services\Checkout\Money;
use App\Services\Printing\ReceiptPrinter;
use App\Services\Storefront\WalletService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

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

                    $expected = Money::toDecimal($shift->expectedCashAgorot());

                    return "المتوقع في الدرج: {$expected} ₪ (يشمل النقد الافتتاحي).";
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
            'notes'         => $data['notes'] ?? null,
        ]);

        $difference = Money::toDecimal($counted - $expected);

        Notification::make()
            ->title('تم إغلاق الوردية')
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

        $order->update([
            'payment_status' => Order::STATUS_PAID,
            'paid_at'        => now(),
            'paid_by'        => auth()->id(),
            'shift_id'       => $shift->getKey(),
        ]);

        Notification::make()->title('تم استلام الدفع')->success()->send();
    }

    /** Move an order one step along its own ladder. */
    public function advance(string $reference, string $status): void
    {
        $order = Order::where('reference', $reference)->firstOrFail();

        if (! in_array($status, $order->allowedNextStatuses(), true)) {
            Notification::make()->title('حالة غير متاحة لهذا الطلب')->danger()->send();

            return;
        }

        $order->update(array_filter([
            'status'       => $status,
            'delivered_at' => $status === Order::FULFILMENT_DELIVERED ? now() : $order->delivered_at,
            'received_at'  => $status === Order::FULFILMENT_RECEIVED ? now() : $order->received_at,
            'cancelled_at' => $status === Order::FULFILMENT_CANCELLED ? now() : $order->cancelled_at,
        ], fn ($value) => $value !== null));

        Notification::make()->title('تم تحديث الحالة')->success()->send();
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

        app(WalletService::class)->credit(
            $order->customer,
            Money::toAgorot($order->total),
            'استرداد طلب #' . $order->reference,
            'wallet',
            null,
            $order,
        );

        $order->update([
            'status'          => Order::FULFILMENT_REFUNDED,
            'refunded_amount' => $order->total,
            'refunded_at'     => now(),
        ]);

        Notification::make()->title('تم الاسترداد إلى محفظة الزبون')->success()->send();
    }
}
