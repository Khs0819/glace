<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CashierShiftResource\Pages;
use App\Models\CashierShift;
use App\Models\Order;
use App\Services\Reporting\FinancialReport;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Closed tills, for the accountant to read.
 *
 * Everything here is a record of something that already happened, so nothing is
 * editable: a shift whose counted cash could be corrected afterwards is not
 * evidence of anything. A mistake is corrected by a note, not by a rewrite.
 */
class CashierShiftResource extends Resource
{
    protected static ?string $model = CashierShift::class;
    protected static ?string $navigationIcon = 'heroicon-o-inbox-stack';
    protected static ?string $navigationGroup = 'التقارير';
    protected static ?string $navigationLabel = 'ورديات الكاشير';
    protected static ?string $modelLabel = 'وردية';
    protected static ?string $pluralModelLabel = 'ورديات الكاشير';
    protected static ?int $navigationSort = 2;
    protected static ?string $slug = 'cashier-shifts';

    /** Shifts are opened from the cashier screen, where the till actually is. */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->isManager() ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $open = static::getModel()::whereNull('closed_at')->count();

        return $open > 0 ? $open . ' مفتوحة' : null;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('الوردية')->schema([
                Infolists\Components\TextEntry::make('user.name')->label('الكاشير'),
                Infolists\Components\TextEntry::make('opened_at')->label('فُتحت')->dateTime('d/m/Y — H:i'),
                Infolists\Components\TextEntry::make('closed_at')->label('أُغلقت')
                    ->dateTime('d/m/Y — H:i')->placeholder('ما زالت مفتوحة'),
                Infolists\Components\TextEntry::make('closer.name')->label('أغلقها')->placeholder('—'),
            ])->columns(4),

            Infolists\Components\Section::make('الدرج')->schema([
                Infolists\Components\TextEntry::make('opening_float')->label('نقد افتتاحي')->suffix(' ₪'),
                Infolists\Components\TextEntry::make('expected_cash')->label('المتوقع')->suffix(' ₪')->placeholder('—'),
                Infolists\Components\TextEntry::make('counted_cash')->label('المعدود')->suffix(' ₪')->placeholder('—'),
                Infolists\Components\TextEntry::make('difference')
                    ->label('الفرق')
                    ->suffix(' ₪')
                    ->placeholder('—')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    // Colour carries the meaning at a glance: green is balanced,
                    // red is short, amber is over.
                    ->color(fn ($state) => match (true) {
                        $state === null            => 'gray',
                        abs((float) $state) < 0.01 => 'success',
                        (float) $state < 0         => 'danger',
                        default                    => 'warning',
                    }),
            ])->columns(4),

            // Frozen at closing time; an open shift shows the live figures.
            Infolists\Components\Section::make('المبيعات')
                ->description(fn (CashierShift $record) => $record->open()
                    ? 'أرقام حيّة — تُجمَّد لحظة إغلاق الوردية'
                    : 'مجمّدة لحظة إغلاق الوردية')
                ->schema(collect([
                    'gross'          => 'إجمالي المبيعات',
                    'refunds'        => 'المرتجعات',
                    'net'            => 'صافي المبيعات',
                    'discounts'      => 'الخصومات',
                    'deliveryFees'   => 'رسوم التوصيل',
                    'changeToWallet' => 'باقٍ حُوّل للمحافظ',
                    'changeRefunds'  => 'طلبات استرداد الباقي',
                    'paidOrders'     => 'الطلبات المدفوعة',
                ])->map(fn (string $label, string $key) => Infolists\Components\TextEntry::make('summary_' . $key)
                    ->label($label)
                    ->state(fn (CashierShift $record) => ($record->summary ?? $record->salesSummary())[$key] ?? 0)
                    ->formatStateUsing(fn ($state) => $key === 'paidOrders'
                        ? (string) $state
                        : number_format((float) $state, 2) . ' ₪')
                    ->weight($key === 'net' ? \Filament\Support\Enums\FontWeight::Bold : null)
                )->values()->all())
                ->columns(4),

            // The cards that were on the board during this shift.
            Infolists\Components\Section::make('أرشيف طلبات الوردية')
                ->collapsible()
                ->schema([
                    Infolists\Components\TextEntry::make('archive')
                        ->label('')
                        ->state(fn (CashierShift $record) => static::archiveTable($record))
                        ->html(),
                ]),

            Infolists\Components\Section::make('التحصيل حسب الطريقة')
                ->schema([
                    Infolists\Components\KeyValueEntry::make('totals')
                        ->label('')
                        ->keyLabel('الطريقة')
                        ->valueLabel('المبلغ')
                        ->columnSpanFull(),
                ])
                // Frozen at closing time; an open shift has none yet.
                ->visible(fn (CashierShift $record) => filled($record->totals)),

            Infolists\Components\Section::make('ملاحظات')
                ->schema([
                    Infolists\Components\TextEntry::make('notes')->label('')->placeholder('—'),
                ])
                ->visible(fn (CashierShift $record) => filled($record->notes)),
        ]);
    }

    /**
     * The shift's orders as a plain table.
     *
     * Built as markup rather than a repeatable entry: the rows come from the
     * orders placed during the shift, not from a relationship, and a repeatable
     * entry does not resolve plain arrays into its columns. Every value is
     * escaped.
     */
    public static function archiveTable(CashierShift $record): HtmlString
    {
        $orders = $record->windowOrders()->latest('created_at')->get();

        if ($orders->isEmpty()) {
            return new HtmlString('<div style="color:#6b7280">لا توجد طلبات في هذه الوردية.</div>');
        }

        $head = ['الطلب', 'الوقت', 'الزبون', 'الاستلام', 'الدفع', 'الحالة', 'الدفع', 'الإجمالي'];
        $cell = 'padding:6px 8px;border-bottom:1px solid #e5e7eb;text-align:start;white-space:nowrap';

        $html = '<div style="overflow-x:auto"><table style="width:100%;border-collapse:collapse;font-size:13px"><thead><tr>';

        foreach ($head as $label) {
            $html .= '<th style="' . $cell . ';font-weight:700">' . e($label) . '</th>';
        }

        $html .= '</tr></thead><tbody>';

        foreach ($orders as $order) {
            $values = [
                $order->reference,
                $order->created_at?->format('H:i'),
                $order->customer_name,
                FinancialReport::CHANNEL_LABELS[$order->delivery_method] ?? $order->delivery_method,
                FinancialReport::PAYMENT_LABELS[$order->payment_method] ?? $order->payment_method,
                $order->status,
                $order->isPaid() ? 'مدفوع' : 'غير مدفوع',
                number_format((float) $order->total, 2) . ' ₪',
            ];

            $html .= '<tr>';

            foreach ($values as $value) {
                $html .= '<td style="' . $cell . '">' . e((string) $value) . '</td>';
            }

            $html .= '</tr>';
        }

        return new HtmlString($html . '</tbody></table></div>');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('الكاشير')
                    ->searchable()
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold),

                Tables\Columns\TextColumn::make('opened_at')->label('فُتحت')->dateTime('d/m/Y — H:i')->sortable(),

                Tables\Columns\TextColumn::make('closed_at')
                    ->label('أُغلقت')
                    ->dateTime('d/m/Y — H:i')
                    ->placeholder('مفتوحة')
                    ->badge(fn (CashierShift $record) => $record->open())
                    ->color(fn (CashierShift $record) => $record->open() ? 'warning' : null),

                Tables\Columns\TextColumn::make('orders_count')->label('طلبات')->counts('orders')->badge()->color('gray'),

                Tables\Columns\TextColumn::make('net_sales')
                    ->label('صافي المبيعات')
                    ->state(fn (CashierShift $record) => $record->summary['net'] ?? null)
                    ->suffix(' ₪')
                    ->placeholder('—')
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold),

                Tables\Columns\TextColumn::make('expected_cash')->label('المتوقع')->suffix(' ₪')->placeholder('—'),
                Tables\Columns\TextColumn::make('counted_cash')->label('المعدود')->suffix(' ₪')->placeholder('—'),

                Tables\Columns\TextColumn::make('difference')
                    ->label('الفرق')
                    ->suffix(' ₪')
                    ->placeholder('—')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->color(fn ($state) => match (true) {
                        $state === null            => 'gray',
                        abs((float) $state) < 0.01 => 'success',
                        (float) $state < 0         => 'danger',
                        default                    => 'warning',
                    }),
            ])
            ->defaultSort('opened_at', 'desc')
            ->filters([
                Tables\Filters\Filter::make('open')
                    ->label('مفتوحة الآن')
                    ->query(fn ($query) => $query->whereNull('closed_at'))
                    ->toggle(),

                // The rows worth looking at: a drawer that did not balance.
                Tables\Filters\Filter::make('mismatched')
                    ->label('بها فرق')
                    ->query(fn ($query) => $query->whereNotNull('closed_at')
                        ->where(fn ($q) => $q->where('difference', '>', 0.01)->orWhere('difference', '<', -0.01)))
                    ->toggle(),

                Tables\Filters\SelectFilter::make('user_id')
                    ->label('الكاشير')
                    ->relationship('user', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->visible(fn () => auth()->user()?->isManager()),
            ])
            ->emptyStateHeading('لا توجد ورديات')
            ->emptyStateDescription('تُفتح الورديات من شاشة الكاشير.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCashierShifts::route('/'),
            'view'  => Pages\ViewCashierShift::route('/{record}'),
        ];
    }
}
