<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

class IceCreamAddonPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'iceCreamAddonPrices';
    protected static ?string $title = 'أسعار إضافة البوظة (براد مع بوظة)';

    /**
     * Only the brad-boza flow adds ice cream on top of a size
     * (swagger: IBuilderProduct.iceCreamAddonPrices, gated by includesIceCreamStep).
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->kind === 'builder' && (bool) $ownerRecord->includes_ice_cream_step;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('flavor_family')
                ->label('عائلة النكهة')
                ->options(\App\Support\FlavorFamily::pricingOptions())
                ->required()
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('product_id', $this->getOwnerRecord()->getKey()))
                ->helperText('سعر واحد لكل عائلة'),
            Forms\Components\TextInput::make('price')
                ->label('السعر الإضافي (₪)')
                ->numeric()
                ->required()
                ->minValue(0)
                ->suffix('₪')
                ->helperText('يُضاف إلى سعر الحجم الأساسي'),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('flavor_family')
                    ->label('عائلة النكهة')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'classic' => 'primary',
                        'special' => 'warning',
                        'mix'     => 'success',
                        default   => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'classic' => '🍦 كلاسيك',
                        'special' => '⭐ سبيشال',
                        'mix'     => '🔀 مكس',
                        default   => $state,
                    }),
                Tables\Columns\TextColumn::make('price')
                    ->label('السعر الإضافي')
                    ->suffix(' ₪')
                    ->sortable(),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label('إضافة سعر')])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('لا توجد أسعار')
            ->emptyStateDescription('هذا الجدول خاص بمنتج "براد مع بوظة" فقط. أضف سعراً لكل عائلة نكهة.');
    }
}
