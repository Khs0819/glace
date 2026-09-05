<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DeliveryZoneResource\Pages;
use App\Models\DeliveryZone;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * Delivery areas and their fees (handoff 10), replacing the storefront's
 * hardcoded deliveryZones.ts.
 */
class DeliveryZoneResource extends Resource
{
    protected static ?string $model = DeliveryZone::class;
    protected static ?string $navigationIcon = 'heroicon-o-map-pin';
    protected static ?string $navigationGroup = 'الطلبات';
    protected static ?string $navigationLabel = 'مناطق التوصيل';
    protected static ?string $modelLabel = 'منطقة توصيل';
    protected static ?string $pluralModelLabel = 'مناطق التوصيل';
    protected static ?int $navigationSort = 6;
    protected static ?string $slug = 'delivery-zones';
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('المنطقة')->schema([
                Forms\Components\TextInput::make('id')
                    ->label('المعرف')
                    ->required()
                    ->maxLength(60)
                    ->alphaDash()
                    ->unique(ignoreRecord: true)
                    ->disabledOn('edit')
                    // Saved addresses point at this string. Changing it would
                    // orphan every address already stored against the zone.
                    ->helperText('لا يتغيّر بعد الإنشاء — العناوين المحفوظة مرتبطة به. مثال: rimal'),

                Forms\Components\TextInput::make('name')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(190)
                    ->placeholder('الرمال'),

                Forms\Components\TextInput::make('description')
                    ->label('الوصف')
                    ->maxLength(190)
                    ->placeholder('حي الرمال'),

                Forms\Components\TextInput::make('fee')
                    ->label('رسوم التوصيل')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->default(0)
                    ->suffix('₪'),

                Forms\Components\TextInput::make('sort_order')->label('الترتيب')->numeric()->default(0),

                Forms\Components\Toggle::make('available')
                    ->label('متاحة للتوصيل')
                    ->default(true)
                    ->helperText('إيقافها يخفيها من شاشة الدفع دون حذف العناوين المرتبطة بها'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('المنطقة')->searchable()
                    ->description(fn (DeliveryZone $record) => $record->description),

                Tables\Columns\TextColumn::make('id')->label('المعرف')->badge()->color('gray')->searchable(),

                Tables\Columns\TextColumn::make('fee')->label('الرسوم')->suffix(' ₪')->sortable(),

                Tables\Columns\TextColumn::make('addresses_count')
                    ->label('عناوين')
                    ->counts('addresses')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\IconColumn::make('available')->label('متاحة')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([Tables\Filters\TernaryFilter::make('available')->label('متاحة')])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('لا توجد مناطق توصيل')
            ->emptyStateDescription('أضف مناطق التوصيل ورسومها لتظهر في شاشة الدفع.');
    }

    public static function canDelete(Model $record): bool
    {
        // Addresses keep pointing here. Switch it off instead — deleting is
        // only reasonable once nothing references it.
        return ! $record->addresses()->exists();
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDeliveryZones::route('/'),
            'create' => Pages\CreateDeliveryZone::route('/create'),
            'edit'   => Pages\EditDeliveryZone::route('/{record}/edit'),
        ];
    }
}
