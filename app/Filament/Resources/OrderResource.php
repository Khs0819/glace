<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Filament\Resources\OrderResource\RelationManagers;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Checkout\Money;
use App\Services\Storefront\WalletService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Orders come in from the storefront, priced and snapshotted. Nothing here
 * edits money: the total was computed at checkout and is what the customer was
 * charged, so the dashboard reads it and can cancel — never rewrite it.
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;
    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';
    protected static ?string $navigationGroup = 'الطلبات';
    protected static ?string $navigationLabel = 'الطلبات';
    protected static ?string $modelLabel = 'طلب';
    protected static ?string $pluralModelLabel = 'الطلبات';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'reference';

    /** @return array<int, string> */
    public static function getGloballySearchableAttributes(): array
    {
        return ['reference', 'customer_name', 'customer_phone'];
    }

    public static function getNavigationBadge(): ?string
    {
        // Anything that still needs a human: a charge we never got an answer
        // for outranks everything else here.
        $unresolved = Payment::where('status', Payment::STATUS_UNRESOLVED)->count();

        if ($unresolved > 0) {
            return $unresolved . ' غير مؤكد';
        }

        $open = static::getModel()::whereIn('payment_status', [Order::STATUS_PENDING, Order::STATUS_AWAITING_PAYMENT])->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return Payment::where('status', Payment::STATUS_UNRESOLVED)->exists() ? 'danger' : 'warning';
    }

    public static function canCreate(): bool
    {
        return false;
    }

    /** Totals and lines are a record of what happened; they are not editable. */
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return ! $record->isPaid();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([/* read-only — see infolist */]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('الطلب')
                ->icon('heroicon-o-receipt-percent')
                ->schema([
                    Infolists\Components\TextEntry::make('reference')
                        ->label('رقم الطلب')
                        ->copyable()
                        ->weight(\Filament\Support\Enums\FontWeight::Bold)
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                    Infolists\Components\TextEntry::make('status')
                        ->label('حالة الطلب')
                        ->badge()
                        ->color(fn (string $state) => static::fulfilmentColor($state)),
                    Infolists\Components\TextEntry::make('payment_status')
                        ->label('حالة الدفع')
                        ->badge()
                        ->formatStateUsing(fn (Order $record) => $record->statusLabel())
                        ->color(fn (string $state) => static::statusColor($state)),
                    Infolists\Components\TextEntry::make('created_at')
                        ->label('وقت الطلب')
                        ->dateTime('d/m/Y — H:i'),
                    Infolists\Components\TextEntry::make('paid_at')
                        ->label('وقت الدفع')
                        ->dateTime('d/m/Y — H:i')
                        ->placeholder('—'),
                ])->columns(5),

            Infolists\Components\Section::make('العميل')
                ->icon('heroicon-o-user')
                ->schema([
                    Infolists\Components\TextEntry::make('customer_name')->label('الاسم'),
                    Infolists\Components\TextEntry::make('customer_phone')
                        ->label('الهاتف')
                        ->copyable()
                        ->icon('heroicon-m-phone'),
                    Infolists\Components\TextEntry::make('notes')
                        ->label('ملاحظات')
                        ->placeholder('—')
                        ->columnSpanFull(),
                ])->columns(2),

            Infolists\Components\Section::make('التوصيل والدفع')
                ->icon('heroicon-o-truck')
                ->schema([
                    Infolists\Components\TextEntry::make('delivery_method')->label('طريقة الاستلام')->badge(),
                    Infolists\Components\TextEntry::make('payment_method')->label('طريقة الدفع')->badge(),
                    Infolists\Components\TextEntry::make('preparation_time')
                        ->label('وقت التحضير')->suffix(' دقيقة')->placeholder('—'),
                    Infolists\Components\TextEntry::make('estimated_delivery_time')
                        ->label('وقت التوصيل')->suffix(' دقيقة')->placeholder('—'),
                    Infolists\Components\TextEntry::make('scheduled_for')
                        ->label('موعد مطلوب')->dateTime('d/m/Y — H:i')->placeholder('—'),
                    Infolists\Components\TextEntry::make('cancel_reason')
                        ->label('سبب الإلغاء')->placeholder('—'),
                ])->columns(3),

            // A frozen copy taken when the order was placed — not a live read
            // of the customer's saved address, which they may since have
            // edited or deleted.
            Infolists\Components\Section::make('عنوان التوصيل')
                ->icon('heroicon-o-map-pin')
                ->schema([
                    Infolists\Components\TextEntry::make('address.name')->label('المستلم'),
                    Infolists\Components\TextEntry::make('address.phone')->label('الهاتف')->copyable(),
                    Infolists\Components\TextEntry::make('address.area')->label('المنطقة')->placeholder('—'),
                    Infolists\Components\TextEntry::make('address.city')->label('المدينة')->placeholder('—'),
                    Infolists\Components\TextEntry::make('address.street')->label('الشارع')->placeholder('—'),
                    Infolists\Components\TextEntry::make('address.landmark')->label('علامة مميزة')->placeholder('—'),
                ])
                ->columns(3)
                ->visible(fn (Order $record) => $record->delivery_method === 'delivery' && filled($record->address)),

            Infolists\Components\Section::make('السائق')
                ->icon('heroicon-o-identification')
                ->schema([
                    Infolists\Components\TextEntry::make('driver.name')->label('الاسم'),
                    Infolists\Components\TextEntry::make('driver.phone')->label('الهاتف')->copyable(),
                    Infolists\Components\TextEntry::make('driver.company')->label('الشركة')->placeholder('—'),
                    Infolists\Components\TextEntry::make('driver_assigned_at')
                        ->label('وقت التعيين')->dateTime('d/m/Y — H:i'),
                ])
                ->columns(4)
                ->visible(fn (Order $record) => filled($record->driver)),

            Infolists\Components\Section::make('إيصال التحويل')
                ->icon('heroicon-o-document-check')
                ->schema([
                    Infolists\Components\ImageEntry::make('receipt_image')
                        ->label('الصورة')->disk('public')->height(320)->placeholder('لم تُرفق صورة'),
                    Infolists\Components\TextEntry::make('receipt_note')
                        ->label('ملاحظة الزبون')->placeholder('—')->columnSpanFull(),
                ])
                ->visible(fn (Order $record) => $record->requiresReceipt()),

            Infolists\Components\Section::make('الأصناف')
                ->icon('heroicon-o-list-bullet')
                ->schema([
                    Infolists\Components\RepeatableEntry::make('items')
                        ->label('')
                        ->schema([
                            Infolists\Components\TextEntry::make('product_name')
                                ->label('المنتج')
                                ->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                            Infolists\Components\TextEntry::make('description')
                                ->label('التفاصيل')
                                ->columnSpan(2),
                            Infolists\Components\TextEntry::make('quantity')
                                ->label('الكمية')
                                ->badge(),
                            Infolists\Components\TextEntry::make('line_total')
                                ->label('الإجمالي')
                                ->suffix(' ₪')
                                ->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                        ])->columns(5),
                ]),

            Infolists\Components\Section::make('المبلغ')
                ->icon('heroicon-o-banknotes')
                ->schema([
                    Infolists\Components\TextEntry::make('subtotal')->label('المجموع')->suffix(' ₪'),
                    Infolists\Components\TextEntry::make('total')
                        ->label('الإجمالي المطلوب')
                        ->suffix(' ₪')
                        ->weight(\Filament\Support\Enums\FontWeight::Bold)
                        ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                        ->color('success'),
                ])->columns(2)->aside(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('reference')
                    ->label('رقم الطلب')
                    ->searchable()
                    ->copyable()
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('الوقت')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->description(fn (Order $record) => $record->created_at->diffForHumans()),
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('العميل')
                    ->searchable()
                    ->description(fn (Order $record) => $record->customer_phone),
                Tables\Columns\TextColumn::make('items_count')
                    ->label('أصناف')
                    ->counts('items')
                    ->badge()
                    ->color('gray')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('total')
                    ->label('الإجمالي')
                    ->suffix(' ₪')
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                // The first question anyone asks of a docket: where does it go?
                Tables\Columns\TextColumn::make('delivery_method')
                    ->label('القناة')
                    ->badge()
                    ->formatStateUsing(fn (Order $record) => match ($record->delivery_method) {
                        'dine-in'  => $record->table_number
                            ? 'داخل المحل · طاولة ' . $record->table_number
                            : 'داخل المحل',
                        'pickup'   => 'استلام',
                        'delivery' => 'توصيل' . ($record->address['area'] ?? null ? ' · ' . $record->address['area'] : ''),
                        default    => $record->delivery_method,
                    })
                    ->color(fn (Order $record) => match ($record->delivery_method) {
                        'dine-in'  => 'warning',
                        'delivery' => 'info',
                        default    => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('حالة الطلب')
                    ->badge()
                    ->color(fn (string $state) => static::fulfilmentColor($state)),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label('حالة الدفع')
                    ->badge()
                    ->formatStateUsing(fn (Order $record) => $record->statusLabel())
                    ->color(fn (string $state) => static::statusColor($state)),
                // A charge with no answer is the one row an admin must act on,
                // so it is called out in the list rather than buried in a tab.
                Tables\Columns\IconColumn::make('needs_review')
                    ->label('')
                    ->getStateUsing(fn (Order $record) => $record->payments()
                        ->where('status', Payment::STATUS_UNRESOLVED)->exists())
                    ->boolean()
                    ->trueIcon('heroicon-o-exclamation-triangle')
                    ->falseIcon('')
                    ->trueColor('danger')
                    ->tooltip(fn ($state) => $state ? 'محاولة دفع غير مؤكدة — تحتاج مراجعة' : null),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('حالة الطلب')
                    ->options(array_combine(
                        array_merge(Order::FULFILMENT_FLOWS['delivery'], Order::FULFILMENT_FLOWS['pickup'], [Order::FULFILMENT_CANCELLED, Order::FULFILMENT_REFUNDED]),
                        array_merge(Order::FULFILMENT_FLOWS['delivery'], Order::FULFILMENT_FLOWS['pickup'], [Order::FULFILMENT_CANCELLED, Order::FULFILMENT_REFUNDED]),
                    )),
                Tables\Filters\SelectFilter::make('payment_status')
                    ->label('حالة الدفع')
                    ->options(Order::STATUSES),
                Tables\Filters\Filter::make('needs_review')
                    ->label('يحتاج مراجعة')
                    ->query(fn (Builder $query) => $query->whereHas(
                        'payments',
                        fn (Builder $q) => $q->where('status', Payment::STATUS_UNRESOLVED),
                    ))
                    ->toggle(),
                Tables\Filters\SelectFilter::make('delivery_method')
                    ->label('القناة')
                    ->options([
                        'dine-in'  => 'داخل المحل',
                        'pickup'   => 'استلام',
                        'delivery' => 'توصيل',
                    ]),

                Tables\Filters\SelectFilter::make('payment_method')
                    ->label('طريقة الدفع')
                    ->options(array_combine(Order::PAYMENT_METHODS, Order::PAYMENT_METHODS)),

                // A docket nobody printed is an order the kitchen may never
                // have seen.
                Tables\Filters\Filter::make('not_printed')
                    ->label('لم تُطبع')
                    ->query(fn (Builder $query) => $query->whereNull('printed_at'))
                    ->toggle(),

                Tables\Filters\Filter::make('today')
                    ->label('اليوم')
                    ->query(fn (Builder $query) => $query->whereDate('created_at', today()))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('print')
                    ->label(fn (Order $record) => $record->printed() ? 'إعادة طباعة' : 'طباعة')
                    ->icon('heroicon-o-printer')
                    ->color('gray')
                    ->url(fn (Order $record) => route('receipts.show', $record->reference))
                    ->openUrlInNewTab(),

                // Move the order along its own ladder. The options come from
                // the record, so a pickup order is never offered "في الطريق"
                // and the storefront's tracker never sees a step it cannot draw.
                Tables\Actions\Action::make('advance')
                    ->label('تحديث الحالة')
                    ->icon('heroicon-o-arrow-right-circle')
                    ->color('primary')
                    ->visible(fn (Order $record) => ! $record->isFinal())
                    ->form(fn (Order $record) => [
                        Forms\Components\Select::make('status')
                            ->label('الحالة الجديدة')
                            ->options(array_combine($record->allowedNextStatuses(), $record->allowedNextStatuses()))
                            ->required()
                            ->native(false),

                        Forms\Components\TextInput::make('preparation_time')
                            ->label('وقت التحضير (دقيقة)')
                            ->numeric()->minValue(1)->maxValue(180)
                            ->default($record->preparation_time),

                        Forms\Components\TextInput::make('estimated_delivery_time')
                            ->label('وقت التوصيل المتوقع (دقيقة)')
                            ->numeric()->minValue(1)->maxValue(180)
                            ->default($record->estimated_delivery_time)
                            ->visible($record->delivery_method === 'delivery'),
                    ])
                    ->action(function (Order $record, array $data) {
                        $status = $data['status'];

                        $record->update(array_filter([
                            'status'                  => $status,
                            'preparation_time'        => $data['preparation_time'] ?? null,
                            'estimated_delivery_time' => $data['estimated_delivery_time'] ?? null,

                            // Stamped as the order passes each milestone, so
                            // the tracker can show when, not just whether.
                            'delivered_at' => $status === Order::FULFILMENT_DELIVERED ? now() : $record->delivered_at,
                            'received_at'  => $status === Order::FULFILMENT_RECEIVED ? now() : $record->received_at,
                            'cancelled_at' => $status === Order::FULFILMENT_CANCELLED ? now() : $record->cancelled_at,
                        ], fn ($value) => $value !== null));

                        Notification::make()->title('تم تحديث حالة الطلب')->success()->send();
                    }),

                // Only meaningful once something is actually going out.
                Tables\Actions\Action::make('assignDriver')
                    ->label('تعيين سائق')
                    ->icon('heroicon-o-truck')
                    ->color('info')
                    ->visible(fn (Order $record) => $record->delivery_method === 'delivery' && ! $record->isFinal())
                    ->form([
                        Forms\Components\TextInput::make('name')->label('اسم السائق')->required()->maxLength(120),
                        Forms\Components\TextInput::make('phone')->label('هاتف السائق')->required()->maxLength(20),
                        Forms\Components\TextInput::make('company')->label('الشركة')->maxLength(120),
                    ])
                    ->action(function (Order $record, array $data) {
                        $record->update([
                            'driver' => [
                                'id'      => (string) Str::ulid(),
                                'name'    => $data['name'],
                                'phone'   => $data['phone'],
                                'company' => $data['company'] ?? null,
                            ],
                            'driver_assigned_at' => now(),
                        ]);

                        Notification::make()->title('تم تعيين السائق')->success()->send();
                    }),

                // Money back onto the customer's wallet, deliberately manual:
                // handoff 12 is explicit that a refund is a decision somebody
                // makes after checking, not something cancellation triggers.
                Tables\Actions\Action::make('refundToWallet')
                    ->label('استرداد للمحفظة')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('استرداد قيمة الطلب')
                    ->modalDescription(fn (Order $record) => "سيُضاف {$record->total} ₪ إلى رصيد الزبون وتتحول حالة الطلب إلى «مسترد».")
                    ->visible(fn (Order $record) => $record->customer_id !== null
                        && $record->status !== Order::FULFILMENT_REFUNDED
                        && $record->total > 0)
                    ->action(function (Order $record) {
                        app(WalletService::class)->credit(
                            $record->customer,
                            Money::toAgorot($record->total),
                            'استرداد طلب #' . $record->reference,
                            'wallet',
                            null,
                            $record,
                        );

                        $record->update(['status' => Order::FULFILMENT_REFUNDED]);

                        Notification::make()->title('تم الاسترداد إلى محفظة الزبون')->success()->send();
                    }),

                Tables\Actions\Action::make('cancel')
                    ->label('إلغاء')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (Order $record) => ! $record->isPaid() && ! $record->isFinal())
                    ->requiresConfirmation()
                    ->modalHeading('إلغاء الطلب')
                    ->modalDescription('لن يعود بالإمكان دفع هذا الطلب بعد الإلغاء.')
                    ->action(fn (Order $record) => $record->update([
                        'status'         => Order::FULFILMENT_CANCELLED,
                        'payment_status' => Order::STATUS_CANCELLED,
                        'cancelled_at'   => now(),
                    ])),
            ])
            ->emptyStateHeading('لا توجد طلبات بعد')
            ->emptyStateDescription('ستظهر هنا الطلبات القادمة من الموقع فور إنشائها.');
    }

    /** The fulfilment ladder: grey while it waits, green once it has landed. */
    public static function fulfilmentColor(string $status): string
    {
        return match ($status) {
            Order::FULFILMENT_DELIVERED, Order::FULFILMENT_RECEIVED => 'success',
            Order::FULFILMENT_ON_WAY, Order::FULFILMENT_READY       => 'info',
            Order::FULFILMENT_PREPARING                             => 'warning',
            Order::FULFILMENT_CANCELLED, Order::FULFILMENT_REFUNDED => 'danger',
            default                                                 => 'gray',
        };
    }

    public static function statusColor(string $status): string
    {
        return match ($status) {
            Order::STATUS_PAID             => 'success',
            Order::STATUS_AWAITING_PAYMENT => 'info',
            Order::STATUS_PENDING          => 'warning',
            Order::STATUS_FAILED           => 'danger',
            default                        => 'gray',
        };
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PaymentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view'  => Pages\ViewOrder::route('/{record}'),
        ];
    }
}
