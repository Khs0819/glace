<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class IceCreamAddonPricesRelationManager extends RelationManager
{
    protected static string $relationship = 'iceCreamAddonPrices';
    protected static ?string $title = 'أسعار إضافة البوظة (براد مع بوظة)';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('flavor_family')
                ->label('عائلة النكهة')
                ->options([
                    'classic' => '🍦 كلاسيك',
                    'special' => '⭐ سبيشال',
                    'mix'     => '🔀 مكس',
                ])
                ->required(),
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
