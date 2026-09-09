<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DriverResource\Pages;
use App\Models\Driver;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The drivers the counter hands deliveries to.
 *
 * "Busy" is never edited here because it is not stored: it is read off the
 * orders that are on the road right now. A field somebody has to remember to
 * flip is a field that is wrong by the second delivery.
 */
class DriverResource extends Resource
{
    protected static ?string $model = Driver::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationGroup = 'الطلبات';
    protected static ?string $navigationLabel = 'السائقون';
    protected static ?string $modelLabel = 'سائق';
    protected static ?string $pluralModelLabel = 'السائقون';
    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')
                ->label('اسم السائق')
                ->required()
                ->maxLength(120),

            Forms\Components\TextInput::make('phone')
                ->label('رقم الهاتف')
                ->tel()
                ->required()
                ->maxLength(20)
                ->helperText('يظهر للعميل عند خروج طلبه، ويتصل به مباشرة.'),

            Forms\Components\TextInput::make('company')
                ->label('شركة التوصيل')
                ->maxLength(120),

            Forms\Components\Toggle::make('active')
                ->label('مفعّل')
                ->default(true)
                // Deleting would take their name off deliveries they made.
                ->helperText('أوقفه بدل حذفه — الحذف يمحو اسمه من التوصيلات السابقة.'),

            Forms\Components\Textarea::make('notes')
                ->label('ملاحظات')
                ->rows(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withCount([
                'orders as deliveries_count' => fn (Builder $q) => $q->whereIn('status', [
                    Order::FULFILMENT_ON_WAY, Order::FULFILMENT_RECEIVED,
                ]),
                'activeOrders as on_road_count',
            ]))
            ->defaultSort('name')
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('السائق')->searchable()->weight('bold'),
                Tables\Columns\TextColumn::make('company')->label('الشركة')->searchable()->placeholder('—'),
                Tables\Columns\TextColumn::make('phone')->label('الهاتف')->searchable()->copyable(),

                Tables\Columns\TextColumn::make('on_road_count')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn ($state, Driver $record) => match (true) {
                        ! $record->active => 'غير مفعّل',
                        $state > 0        => 'في توصيل (' . $state . ')',
                        default           => 'متاح',
                    })
                    ->color(fn ($state, Driver $record) => match (true) {
                        ! $record->active => 'gray',
                        $state > 0        => 'warning',
                        default           => 'success',
                    }),

                Tables\Columns\TextColumn::make('deliveries_count')
                    ->label('توصيلات')
                    ->badge()
                    ->color('gray'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')->label('مفعّل'),

                Tables\Filters\Filter::make('free')
                    ->label('متاح الآن')
                    ->query(fn (Builder $query) => $query->where('active', true)
                        ->whereDoesntHave('activeOrders')),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('تعديل'),
            ])
            ->emptyStateHeading('لا يوجد سائقون')
            ->emptyStateDescription('أضف السائقين ليتمكن الكاشير من تعيينهم على طلبات التوصيل.');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDrivers::route('/'),
            'create' => Pages\CreateDriver::route('/create'),
            'edit'   => Pages\EditDriver::route('/{record}/edit'),
        ];
    }
}
