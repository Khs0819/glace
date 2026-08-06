<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class MixesRelationManager extends RelationManager
{
    protected static string $relationship = 'mixes';
    protected static ?string $title = 'قواعد المكس (Flat-List)';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('معلومات المكس')->schema([
                Forms\Components\TextInput::make('slug')
                    ->label('المعرف')
                    ->required()
                    ->maxLength(100)
                    ->helperText('مثال: mix · super-mix'),
                Forms\Components\TextInput::make('label')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(200)
                    ->helperText('مثال: مكس (اختر طعمين)'),
                Forms\Components\TextInput::make('pick')
                    ->label('عدد الاختيارات')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->helperText('كم نكهة يختار العميل؟'),
                Forms\Components\TextInput::make('sort_order')
                    ->label('الترتيب')
                    ->numeric()
                    ->default(1),
            ])->columns(2),

            Forms\Components\Section::make('الأسعار')
                ->description('السعر النهائي = basePrice + (flavorPrice × عدد النكهات العادية) + (premiumFlavorPrice × عدد النكهات المميزة)')
                ->schema([
                    Forms\Components\TextInput::make('base_price')
                        ->label('السعر الأساسي')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->suffix('₪'),
                    Forms\Components\TextInput::make('flavor_price')
                        ->label('سعر النكهة العادية')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->suffix('₪'),
                    Forms\Components\TextInput::make('premium_flavor_price')
                        ->label('سعر النكهة المميزة (Premium)')
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->suffix('₪'),
                ])->columns(3),

            Forms\Components\Section::make('النكهات المتاحة')
                ->description('أدخل معرّف كل نكهة واضغط Enter — مثال: nutella · lotus · pistachio')
                ->schema([
                    Forms\Components\TagsInput::make('flavor_option_ids')
                        ->label('')
                        ->separator(',')
                        ->placeholder('اكتب اسم/معرف النكهة ثم اضغط Enter'),
                ]),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('slug')
                    ->label('المعرف')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('label')
                    ->label('الاسم')
                    ->weight(\Filament\Support\Enums\FontWeight::Medium),
                Tables\Columns\TextColumn::make('pick')
                    ->label('عدد الاختيارات')
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('base_price')
                    ->label('الأساسي')
                    ->suffix(' ₪'),
                Tables\Columns\TextColumn::make('flavor_price')
                    ->label('النكهة العادية')
                    ->suffix(' ₪'),
                Tables\Columns\TextColumn::make('premium_flavor_price')
                    ->label('المميزة')
                    ->suffix(' ₪'),
            ])
            ->defaultSort('sort_order')
            ->headerActions([Tables\Actions\CreateAction::make()->label('إضافة مكس')])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('لا توجد قواعد مكس')
            ->emptyStateDescription('المكس خاص بمنتجات Flat-List مثل: كنافة، لقيمات، بان كيك...');
    }
}
