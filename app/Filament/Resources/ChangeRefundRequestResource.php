<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ChangeRefundRequestResource\Pages;
use App\Models\ChangeRefundRequest;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Review queue for cash change refund requests.
 *
 * The cashier creates these throughout the day when a customer overpays in
 * cash. At shift close the cashier (or manager) works through the list,
 * transfers the money through the chosen method, and marks each one done.
 */
class ChangeRefundRequestResource extends Resource
{
    protected static ?string $model = ChangeRefundRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-arrow-uturn-left';
    protected static ?string $navigationGroup = 'الطلبات';
    protected static ?string $navigationLabel = 'طلبات الاسترداد';
    protected static ?string $modelLabel = 'طلب استرداد';
    protected static ?string $pluralModelLabel = 'طلبات الاسترداد';
    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $count = ChangeRefundRequest::where('status', 'pending')->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('order_reference')
                ->label('رقم الطلب')
                ->disabled(),

            Forms\Components\TextInput::make('amount')
                ->label('المبلغ')
                ->suffix('₪')
                ->disabled(),

            Forms\Components\TextInput::make('holder_name')
                ->label('اسم صاحب الحساب')
                ->disabled(),

            Forms\Components\TextInput::make('holder_phone')
                ->label('رقم الجوال')
                ->disabled(),

            Forms\Components\TextInput::make('refund_method')
                ->label('وسيلة الاسترداد')
                ->formatStateUsing(fn ($state) => ChangeRefundRequest::REFUND_METHODS[$state] ?? $state)
                ->disabled(),

            Forms\Components\TextInput::make('createdBy.name')
                ->label('طلبه')
                ->disabled(),

            Forms\Components\TextInput::make('reviewedBy.name')
                ->label('حوّله')
                ->disabled(),

            Forms\Components\Textarea::make('notes')
                ->label('سبب الاسترداد')
                ->disabled()
                ->columnSpanFull(),

            Forms\Components\Textarea::make('review_note')
                ->label('ملاحظة المراجعة / سبب الرفض')
                ->disabled()
                ->visible(fn (?ChangeRefundRequest $record) => filled($record?->review_note))
                ->columnSpanFull(),

            Forms\Components\FileUpload::make('transfer_receipt')
                ->label('إشعار التحويل')
                ->image()
                ->disk('public')
                ->disabled()
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('order_reference')
                    ->label('رقم الطلب')
                    ->searchable()
                    ->weight('bold')
                    ->copyable(),

                // The change from a cash sale, or a whole order given back.
                Tables\Columns\TextColumn::make('kind')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => ChangeRefundRequest::KINDS[$state] ?? ChangeRefundRequest::KINDS[ChangeRefundRequest::KIND_CHANGE])
                    ->color(fn (?string $state) => $state === ChangeRefundRequest::KIND_ORDER ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('ILS')
                    ->weight('bold')
                    ->color('success')
                    // What the tab adds up to, under the column: how much is
                    // owed today, or how much went back this month.
                    ->summarize(Tables\Columns\Summarizers\Sum::make()->label('المجموع')->money('ILS')),

                Tables\Columns\TextColumn::make('holder_name')
                    ->label('صاحب الحساب')
                    ->searchable(),

                Tables\Columns\TextColumn::make('holder_phone')
                    ->label('الجوال')
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('refund_method')
                    ->label('الوسيلة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => ChangeRefundRequest::REFUND_METHODS[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'jawwal' => 'info',
                        'bop'    => 'warning',
                        'palpay' => 'primary',
                        'cash'   => 'success',
                        default  => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'pending'   => 'بانتظار المراجعة',
                        'completed' => 'تم التحويل',
                        'rejected'  => 'مرفوض',
                        default     => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'pending'   => 'warning',
                        'completed' => 'success',
                        'rejected'  => 'danger',
                        default     => 'gray',
                    }),

                // Why the money is going back. The first question asked of any
                // row in this list, and it used to read "ملاحظات".
                Tables\Columns\TextColumn::make('notes')
                    ->label('سبب الاسترداد')
                    ->wrap()
                    ->limit(60)
                    ->tooltip(fn (ChangeRefundRequest $record) => $record->notes)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('طلبه')
                    ->description(fn (ChangeRefundRequest $record) => $record->created_at?->format('H:i - d/m'))
                    ->sortable(['created_at'])
                    ->placeholder('—'),

                // Who sent the transfer, and when. Without these the archive
                // says a refund happened but not who is answerable for it.
                Tables\Columns\TextColumn::make('reviewedBy.name')
                    ->label('المحاسب')
                    ->description(fn (ChangeRefundRequest $record) => $record->reviewed_at?->format('H:i - d/m'))
                    ->sortable(['reviewed_at'])
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('review_note')
                    ->label('سبب الرفض')
                    ->wrap()
                    ->limit(40)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\ImageColumn::make('transfer_receipt')
                    ->label('الإشعار')
                    ->disk('public')
                    ->height(36)
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        'pending'   => 'بانتظار المراجعة',
                        'completed' => 'تم التحويل',
                        'rejected'  => 'مرفوض',
                    ]),

                Tables\Filters\SelectFilter::make('refund_method')
                    ->label('الوسيلة')
                    ->options(ChangeRefundRequest::REFUND_METHODS),
            ])
            ->actions([
                Tables\Actions\Action::make('markCompleted')
                    ->label('✅ تم التحويل')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('تأكيد التحويل')
                    ->modalDescription(fn (ChangeRefundRequest $record) =>
                        "هل تم تحويل {$record->amount} ₪ إلى {$record->holder_name} عبر {$record->methodLabel()}؟"
                    )
                    ->visible(fn (ChangeRefundRequest $record) => $record->isPending())
                    ->form([
                        // The slip is the evidence the change went back; without
                        // it "completed" is only somebody's word.
                        Forms\Components\FileUpload::make('transfer_receipt')
                            ->label('إشعار التحويل للزبون')
                            ->image()
                            ->disk('public')
                            ->directory('refund-receipts')
                            ->maxSize(4096)
                            ->required(),
                    ])
                    ->action(function (ChangeRefundRequest $record, array $data) {
                        $record->complete($data['transfer_receipt'] ?? null, auth()->id());

                        Notification::make()->title('تم تأكيد التحويل')->success()->send();
                    }),

                Tables\Actions\Action::make('markRejected')
                    ->label('❌ رفض')
                    ->color('danger')
                    ->modalHeading('رفض طلب الاسترداد')
                    ->modalSubmitActionLabel('رفض')
                    ->visible(fn (ChangeRefundRequest $record) => $record->isPending())
                    // "مرفوض" on its own tells the customer who asks nothing,
                    // and tells whoever reads the archive less than that.
                    ->form([
                        Forms\Components\Textarea::make('review_note')
                            ->label('سبب الرفض')
                            ->rows(2)
                            ->required()
                            ->maxLength(500)
                            ->placeholder('مثال: الباقي سُلّم للزبون نقداً في المحل'),
                    ])
                    ->action(function (ChangeRefundRequest $record, array $data) {
                        $record->reject($data['review_note'], auth()->id());

                        Notification::make()->title('تم رفض الطلب')->warning()->send();
                    }),

                // The slip, onto the machine. It was viewable and nothing else,
                // which is no use when it has to be attached to an email or
                // handed to somebody asking where their money went.
                Tables\Actions\Action::make('downloadReceipt')
                    ->label('تحميل الإشعار')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (ChangeRefundRequest $record) => filled($record->transfer_receipt))
                    ->action(fn (ChangeRefundRequest $record) => $record->downloadReceipt()),

                Tables\Actions\ViewAction::make()->label('عرض'),
            ])
            /*
             * No bulk "transfer them all".
             *
             * It closed every selected request with no slip at all, which is
             * the one thing that makes "تم التحويل" mean anything — leaving a
             * row that says money went back with nothing to show that it did.
             * Each transfer is sent one at a time; it is closed one at a time.
             */
            ->emptyStateHeading('لا توجد طلبات استرداد')
            ->emptyStateDescription('ستظهر هنا طلبات الاسترداد عند إنشائها من شاشة الكاشير أو من صفحة الطلبات.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChangeRefundRequests::route('/'),
        ];
    }
}
