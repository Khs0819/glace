<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\ProductItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $title = 'عناصر القائمة (Flat-List)';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('slug')
                    ->label('المعرف (ID)')
                    ->required()
                    ->maxLength(100)
                    ->helperText('ثابت لا يتغير — مثال: nutella · lotus · arabic-coffee')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('label')
                    ->label('اسم العنصر')
                    ->required()
                    ->maxLength(200),
                Forms\Components\TextInput::make('price')
                    ->label('السعر')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->suffix('₪'),
                Forms\Components\Textarea::make('description')
                    ->label('الوصف (اختياري)')
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('image')
                    ->label('صورة العنصر')
                    ->image()
                    ->disk('public')
                    ->directory('items')
                    ->imagePreviewHeight('100')
                    ->maxSize(2048),
                Forms\Components\TextInput::make('sort_order')
                    ->label('الترتيب')
                    ->numeric()
                    ->default(1),
            ])->columns(2),
            Forms\Components\Section::make('الخيارات')->schema([
                Forms\Components\Toggle::make('available')
                    ->label('متوفر')
                    ->default(true)
                    ->onColor('success')
                    ->offColor('danger'),
                Forms\Components\Toggle::make('is_premium_mix_flavor')
                    ->label('نكهة مميزة (Premium Mix)')
                    ->helperText('يُحمَّل بـ premiumFlavorPrice في المكس'),
            ])->columns(2),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('label')
                    ->label('الاسم')
                    ->searchable()
                    ->weight(\Filament\Support\Enums\FontWeight::Medium),
                Tables\Columns\TextColumn::make('price')
                    ->label('السعر')
                    ->suffix(' ₪')
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('available')
                    ->label('متوفر')
                    ->onColor('success')
                    ->offColor('danger'),
                Tables\Columns\IconColumn::make('is_premium_mix_flavor')
                    ->label('Premium')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('إضافة عنصر'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('enable')
                        ->label('تفعيل المحدد')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['available' => true])),
                    Tables\Actions\BulkAction::make('disable')
                        ->label('إيقاف المحدد')
                        ->icon('heroicon-o-eye-slash')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['available' => false])),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('لا توجد عناصر')
            ->emptyStateDescription('هذا القسم للمنتجات من نوع Flat-List (مشروبات، كنافة، بان كيك...).');
    }
}
