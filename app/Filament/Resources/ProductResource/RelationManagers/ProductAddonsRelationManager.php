<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Addon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ProductAddonsRelationManager extends RelationManager
{
    protected static string $relationship = 'addons';
    protected static ?string $title = 'الإضافات الخاصة بالمنتج';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('slug')
                ->label('المعرف')
                ->required()
                ->maxLength(100),
            Forms\Components\TextInput::make('label')
                ->label('الاسم')
                ->required()
                ->maxLength(200),
            Forms\Components\TextInput::make('price')
                ->label('السعر (₪)')
                ->numeric()
                ->required(),
            Forms\Components\Select::make('type')
                ->label('النوع')
                ->options(['toggle' => 'Toggle', 'counter' => 'Counter'])
                ->default('toggle')
                ->required(),
            Forms\Components\TextInput::make('max_qty')
                ->label('الحد الأقصى (للـ counter)')
                ->numeric()
                ->nullable(),
            Forms\Components\TextInput::make('sort_order')
                ->label('الترتيب')
                ->numeric()
                ->default(1),
            Forms\Components\Toggle::make('available')
                ->label('متوفر')
                ->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')->label('المعرف'),
                Tables\Columns\TextColumn::make('label')->label('الاسم'),
                Tables\Columns\TextColumn::make('price')->label('السعر'),
                Tables\Columns\TextColumn::make('type')->label('النوع')->badge(),
                Tables\Columns\IconColumn::make('available')->label('متوفر')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([Tables\Actions\CreateAction::make()])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\Action::make('toggle')
                    ->label(fn (Addon $r) => $r->available ? 'إيقاف' : 'تفعيل')
                    ->color(fn (Addon $r) => $r->available ? 'danger' : 'success')
                    ->action(fn (Addon $r) => $r->update(['available' => !$r->available])),
                Tables\Actions\DeleteAction::make(),
            ]);
    }
}
