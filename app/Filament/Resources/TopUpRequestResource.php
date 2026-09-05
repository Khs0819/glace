<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TopUpRequestResource\Pages;
use App\Models\TopUpRequest;
use App\Services\Storefront\WalletService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Reviewing requests to add store credit (handoff 14).
 *
 * This screen is the *only* thing in the system that can raise a balance. The
 * storefront used to expose `approveTopUpRequest` in the browser, which meant
 * anyone could approve their own top-up from the console — approving is a
 * decision, and decisions belong to a person.
 */
class TopUpRequestResource extends Resource
{
    protected static ?string $model = TopUpRequest::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'الطلبات';
    protected static ?string $navigationLabel = 'طلبات شحن الرصيد';
    protected static ?string $modelLabel = 'طلب شحن';
    protected static ?string $pluralModelLabel = 'طلبات شحن الرصيد';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'topup-requests';

    /** Money waiting on a human is worth a badge. */
    public static function getNavigationBadge(): ?string
    {
        $pending = static::getModel()::where('status', TopUpRequest::STATUS_PENDING)->count();

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function canCreate(): bool
    {
        // A top-up is something a customer asks for; the shop does not invent
        // one. To hand out credit directly, use the customer's wallet screen.
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('الطلب')->schema([
                Infolists\Components\TextEntry::make('customer.name')->label('الزبون'),
                Infolists\Components\TextEntry::make('customer.phone')->label('الهاتف')->copyable(),
                Infolists\Components\TextEntry::make('amount')->label('المبلغ')->suffix(' ₪')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                Infolists\Components\TextEntry::make('method')->label('طريقة التحويل')->badge(),
                Infolists\Components\TextEntry::make('status')->label('الحالة')->badge()
                    ->color(fn (string $state) => match ($state) {
                        TopUpRequest::STATUS_APPROVED => 'success',
                        TopUpRequest::STATUS_REJECTED => 'danger',
                        default                       => 'warning',
                    }),
                Infolists\Components\TextEntry::make('created_at')->label('وقت الطلب')->dateTime('d/m/Y — H:i'),
                Infolists\Components\TextEntry::make('phone')->label('رقم جوال باي')->placeholder('—'),
            ])->columns(3),

            Infolists\Components\Section::make('إثبات التحويل')->schema([
                Infolists\Components\ImageEntry::make('receipt_image')
                    ->label('صورة الإيصال')
                    ->disk('public')
                    ->height(320)
                    ->placeholder('لم تُرفق صورة'),

                Infolists\Components\TextEntry::make('receipt_note')
                    ->label('ملاحظة الزبون')
                    ->placeholder('—')
                    ->columnSpanFull(),
            ]),

            Infolists\Components\Section::make('المراجعة')
                ->schema([
                    Infolists\Components\TextEntry::make('reviewer.name')->label('راجعه')->placeholder('—'),
                    Infolists\Components\TextEntry::make('reviewed_at')->label('وقت المراجعة')
                        ->dateTime('d/m/Y — H:i')->placeholder('—'),
                    Infolists\Components\TextEntry::make('review_note')->label('ملاحظة الإدارة')
                        ->placeholder('—')->columnSpanFull(),
                ])
                ->columns(2)
                ->visible(fn (TopUpRequest $record) => $record->reviewed_at !== null),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('التاريخ')
                    ->dateTime('d/m/Y — H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer.name')
                    ->label('الزبون')
                    ->searchable()
                    ->description(fn (TopUpRequest $record) => $record->customer?->phone),

                Tables\Columns\TextColumn::make('amount')
                    ->label('المبلغ')
                    ->suffix(' ₪')
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold),

                Tables\Columns\TextColumn::make('method')->label('الطريقة')->badge(),

                Tables\Columns\IconColumn::make('receipt_image')
                    ->label('إيصال')
                    ->getStateUsing(fn (TopUpRequest $record) => $record->receipt_image !== null)
                    ->boolean(),

                Tables\Columns\TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        TopUpRequest::STATUS_APPROVED => 'success',
                        TopUpRequest::STATUS_REJECTED => 'danger',
                        default                       => 'warning',
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label('الحالة')
                    ->options([
                        TopUpRequest::STATUS_PENDING  => TopUpRequest::STATUS_PENDING,
                        TopUpRequest::STATUS_APPROVED => TopUpRequest::STATUS_APPROVED,
                        TopUpRequest::STATUS_REJECTED => TopUpRequest::STATUS_REJECTED,
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),

                Tables\Actions\Action::make('approve')
                    ->label('موافقة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('الموافقة على شحن الرصيد')
                    ->modalDescription(fn (TopUpRequest $record) => "سيُضاف {$record->amount} ₪ إلى رصيد «{$record->customer?->name}» فوراً.")
                    ->form([
                        Forms\Components\Textarea::make('note')->label('ملاحظة (اختياري)')->rows(2),
                    ])
                    // Only a request that is still pending and has never
                    // produced a credit. WalletService checks this again under
                    // a lock, so a double-click cannot pay twice.
                    ->visible(fn (TopUpRequest $record) => $record->pending() && ! $record->alreadyCredited())
                    ->action(function (TopUpRequest $record, array $data) {
                        app(WalletService::class)->approveTopUp($record, auth()->user(), $data['note'] ?? null);

                        Notification::make()
                            ->title('تمت الموافقة وأُضيف الرصيد')
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('رفض طلب الشحن')
                    ->form([
                        Forms\Components\Textarea::make('note')
                            ->label('سبب الرفض')
                            ->required()
                            ->rows(2),
                    ])
                    ->visible(fn (TopUpRequest $record) => $record->pending() && ! $record->alreadyCredited())
                    ->action(function (TopUpRequest $record, array $data) {
                        app(WalletService::class)->rejectTopUp($record, auth()->user(), $data['note']);

                        Notification::make()->title('تم رفض الطلب')->warning()->send();
                    }),
            ])
            ->emptyStateHeading('لا توجد طلبات شحن')
            ->emptyStateDescription('ستظهر هنا طلبات شحن الرصيد فور إرسالها من التطبيق.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTopUpRequests::route('/'),
            'view'  => Pages\ViewTopUpRequest::route('/{record}'),
        ];
    }
}
