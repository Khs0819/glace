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

            Forms\Components\Textarea::make('notes')
                ->label('ملاحظات')
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

                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->money('ILS')
                    ->weight('bold')
                    ->color('success'),

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

                Tables\Columns\TextColumn::make('createdBy.name')
                    ->label('الكاشير')
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('الوقت')
                    ->dateTime('H:i - d/m')
                    ->sortable(),

                Tables\Columns\TextColumn::make('notes')
                    ->label('ملاحظات')
                    ->limit(30)
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
                    ->action(function (ChangeRefundRequest $record) {
                        $record->update([
                            'status'      => 'completed',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                        Notification::make()->title('تم تأكيد التحويل')->success()->send();
                    }),

                Tables\Actions\Action::make('markRejected')
                    ->label('❌ رفض')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('رفض طلب الاسترداد')
                    ->visible(fn (ChangeRefundRequest $record) => $record->isPending())
                    ->action(function (ChangeRefundRequest $record) {
                        $record->update([
                            'status'      => 'rejected',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]);
                        Notification::make()->title('تم رفض الطلب')->warning()->send();
                    }),

                Tables\Actions\ViewAction::make()->label('عرض'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('markAllCompleted')
                    ->label('✅ تحويل الكل')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function ($records) {
                        $records->each(fn ($r) => $r->update([
                            'status'      => 'completed',
                            'reviewed_by' => auth()->id(),
                            'reviewed_at' => now(),
                        ]));
                        Notification::make()->title('تم تحويل ' . $records->count() . ' طلبات')->success()->send();
                    }),
            ])
            ->emptyStateHeading('لا توجد طلبات استرداد')
            ->emptyStateDescription('ستظهر هنا طلبات الاسترداد عند إنشائها من شاشة الكاشير.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListChangeRefundRequests::route('/'),
        ];
    }
}
