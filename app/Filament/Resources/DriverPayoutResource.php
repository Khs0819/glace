<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DriverPayoutResource\Pages;
use App\Models\DriverPayout;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Every transfer made to a driver, with the receipt that was uploaded for it.
 *
 * The receipt used to be stored and then shown nowhere: once "تم التحويل" was
 * pressed, the only way back to it was the database. This is the list to open
 * when a driver says he was paid short, or the books need checking.
 *
 * Read-only: a payout is a record of money that already moved.
 */
class DriverPayoutResource extends Resource
{
    protected static ?string $model = DriverPayout::class;
    protected static ?string $navigationIcon = 'heroicon-o-banknotes';
    protected static ?string $navigationGroup = 'الطلبات';
    protected static ?string $navigationLabel = 'دفعات السائقين';
    protected static ?string $modelLabel = 'دفعة سائق';
    protected static ?string $pluralModelLabel = 'دفعات السائقين';
    protected static ?int $navigationSort = 7;
    protected static ?string $slug = 'driver-payouts';

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
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['driver', 'paidBy'])->withCount('settlements');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('الدفعة')->schema([
                Infolists\Components\TextEntry::make('driver.name')->label('السائق'),
                Infolists\Components\TextEntry::make('driver.phone')->label('الهاتف')->copyable(),
                Infolists\Components\TextEntry::make('amount')->label('المبلغ')->suffix(' ₪')
                    ->weight(\Filament\Support\Enums\FontWeight::Bold)
                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                Infolists\Components\TextEntry::make('paid_at')->label('وقت التحويل')->dateTime('d/m/Y — H:i'),
                Infolists\Components\TextEntry::make('paidBy.name')->label('حوّلها')->placeholder('—'),
                Infolists\Components\TextEntry::make('notes')->label('ملاحظات')->placeholder('—')->columnSpanFull(),
            ])->columns(3),

            Infolists\Components\Section::make('إشعار التحويل')->schema([
                Infolists\Components\ImageEntry::make('receipt')
                    ->label('')
                    ->disk('public')
                    ->height(420)
                    ->placeholder('لم يُرفع إشعار'),
            ]),

            Infolists\Components\Section::make('التوصيلات المشمولة')->schema([
                Infolists\Components\RepeatableEntry::make('settlements')
                    ->label('')
                    ->schema([
                        Infolists\Components\TextEntry::make('order_reference')->label('الطلب'),
                        Infolists\Components\TextEntry::make('delivery_fee')->label('أجرة التوصيل')->suffix(' ₪'),
                        Infolists\Components\TextEntry::make('delivered_at')->label('سُلِّم')->dateTime('d/m H:i')->placeholder('—'),
                    ])
                    ->columns(3),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('paid_at')->label('التاريخ')->dateTime('d/m/Y — H:i')->sortable(),
                Tables\Columns\TextColumn::make('driver.name')->label('السائق')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('amount')->label('المبلغ')->suffix(' ₪')->sortable(),
                Tables\Columns\TextColumn::make('settlements_count')->label('توصيلات')->badge()->color('gray'),
                Tables\Columns\ImageColumn::make('receipt')->label('الإشعار')->disk('public')->square(),
                Tables\Columns\TextColumn::make('paidBy.name')->label('حوّلها')->placeholder('—')->toggleable(),
            ])
            ->defaultSort('paid_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('driver_id')->label('السائق')->relationship('driver', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()->label('عرض الإشعار'),
            ])
            ->emptyStateHeading('لا توجد دفعات بعد')
            ->emptyStateDescription('تظهر هنا كل دفعة تُحوَّل لسائق مع إشعارها.');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDriverPayouts::route('/'),
            'view'  => Pages\ViewDriverPayout::route('/{record}'),
        ];
    }
}
