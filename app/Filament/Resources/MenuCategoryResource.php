<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuCategoryResource\Pages;
use App\Models\MenuCategory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class MenuCategoryResource extends Resource
{
    protected static ?string $model = MenuCategory::class;
    protected static ?string $navigationIcon = 'heroicon-o-squares-2x2';
    protected static ?string $navigationGroup = 'القائمة';
    protected static ?string $navigationLabel = 'الفئات';
    protected static ?string $modelLabel = 'فئة';
    protected static ?string $pluralModelLabel = 'الفئات';
    protected static ?int $navigationSort = 1;
    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('المعلومات الأساسية')
                ->description('هذه الفئات ثابتة — المعرف (id/slug) لا يتغيّر أبداً')
                ->schema([
                    Forms\Components\TextInput::make('id')
                        ->label('المعرف (Slug)')
                        ->required()
                        ->maxLength(100)
                        ->disabled(fn ($record) => $record !== null)
                        ->dehydrated()
                        ->helperText('مثال: ice-cream · pancake · milkshake'),
                    Forms\Components\TextInput::make('label')
                        ->label('الاسم بالعربي')
                        ->required()
                        ->maxLength(100),
                    Forms\Components\ToggleButtons::make('icon')
                        ->label('الأيقونة')
                        ->options([
                            'ice-cream'   => 'Ice Cream 🍦',
                            'cup-soda'    => 'Cup Soda 🥤',
                            'cake'        => 'Cake 🎂',
                            'glass-water' => 'Glass Water 🥛',
                            'milk'        => 'Milk 🥛',
                            'apple'       => 'Apple 🍎',
                        ])
                        ->required()
                        ->inline()
                        ->columnSpanFull(),
                    Forms\Components\FileUpload::make('image')
                        ->label('صورة الفئة')
                        ->image()
                        ->disk('public')
                        ->directory('categories')
                        ->imagePreviewHeight('120')
                        ->maxSize(2048)
                        ->helperText('اختياري — تظهر بجانب اسم الفئة في القائمة')
                        ->columnSpanFull(),
                    Forms\Components\TextInput::make('sort_order')
                        ->label('الترتيب في القائمة')
                        ->numeric()
                        ->default(1)
                        ->required()
                        ->minValue(1)
                        ->maxValue(20),
                    Forms\Components\Toggle::make('available')
                        ->label('ظاهرة في القائمة')
                        ->default(true)
                        ->onColor('success')
                        ->offColor('danger')
                        ->helperText('إيقاف فئة كاملة (مثلاً مشروبات ساخنة صيفاً)'),
                ])->columns(2),

            Forms\Components\Section::make('الألوان')
                ->description('تُستخدم في واجهة العميل لتميّز كل فئة بصرياً')
                ->schema([
                    Forms\Components\ColorPicker::make('accent_color')
                        ->label('لون التمييز (أزرار، hover)')
                        ->required(),
                    Forms\Components\ColorPicker::make('gradient_from')
                        ->label('لون التدرج — بداية')
                        ->required(),
                    Forms\Components\ColorPicker::make('gradient_to')
                        ->label('لون التدرج — نهاية')
                        ->required(),
                ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('صورة')
                    ->disk('public')
                    ->size(48)
                    ->defaultImageUrl('https://placehold.co/48x48/f59e0b/ffffff?text=🍦'),
                Tables\Columns\TextColumn::make('id')
                    ->label('Slug')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\TextColumn::make('label')
                    ->label('الاسم')
                    ->searchable()
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                Tables\Columns\ColorColumn::make('accent_color')
                    ->label('اللون'),
                Tables\Columns\TextColumn::make('icon')
                    ->label('الأيقونة')
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('الترتيب')
                    ->sortable()
                    ->alignCenter(),
                Tables\Columns\ToggleColumn::make('available')
                    ->label('ظاهرة')
                    ->onColor('success')
                    ->offColor('danger'),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('منتجات')
                    ->counts('products')
                    ->badge()
                    ->color('success')
                    ->alignCenter(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ])]);
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListMenuCategories::route('/'),
            'create' => Pages\CreateMenuCategory::route('/create'),
            'edit'   => Pages\EditMenuCategory::route('/{record}/edit'),
        ];
    }
}
