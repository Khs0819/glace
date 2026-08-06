<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\ProductContainer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ContainersRelationManager extends RelationManager
{
    protected static string $relationship = 'containers';
    protected static ?string $title = 'الحاويات (Builder)';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('slug')
                    ->label('المعرف')
                    ->required()
                    ->maxLength(100)
                    ->helperText('مثال: cup · biscuit · plastic'),
                Forms\Components\TextInput::make('label')
                    ->label('الاسم المختصر')
                    ->required()
                    ->maxLength(100)
                    ->helperText('يظهر على الأزرار'),
                Forms\Components\TextInput::make('name')
                    ->label('اسم المنتج البديل')
                    ->maxLength(200)
                    ->helperText('يستبدل اسم المنتج عند اختيار هذه الحاوية — مثال: بوظة بسكوت'),
                Forms\Components\TextInput::make('pricing_label')
                    ->label('عنوان جدول أسعار هذه الحاوية')
                    ->maxLength(100)
                    ->helperText('مثال: الكاسة · البسكوت'),
                Forms\Components\FileUpload::make('image')
                    ->label('صورة الحاوية')
                    ->image()
                    ->disk('public')
                    ->directory('containers')
                    ->imagePreviewHeight('100')
                    ->maxSize(2048),
                Forms\Components\TextInput::make('sort_order')
                    ->label('الترتيب')
                    ->numeric()
                    ->default(1),
            ])->columns(2),
            Forms\Components\Section::make('الحالة')->schema([
                Forms\Components\Toggle::make('available')
                    ->label('متوفرة')
                    ->default(true)
                    ->onColor('success')
                    ->offColor('danger')
                    ->helperText('الحاوية غير المتوفرة تظهر مع علامة "غير متوفر" للعميل'),
            ])->aside(),
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
                Tables\Columns\TextColumn::make('name')
                    ->label('اسم بديل')
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('pricing_label')
                    ->label('عنوان الأسعار')
                    ->placeholder('—'),
                Tables\Columns\ToggleColumn::make('available')
                    ->label('متوفرة')
                    ->onColor('success')
                    ->offColor('danger'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([Tables\Actions\CreateAction::make()->label('إضافة حاوية')])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('لا توجد حاويات')
            ->emptyStateDescription('الحاويات خاصة بمنتجات Builder مثل: كاسة/بسكوت/تيك اواي، بلاستيك/فلين، ليمون/مانجا/مكس.');
    }
}
