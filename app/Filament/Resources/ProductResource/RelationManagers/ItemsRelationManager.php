<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\ProductItem;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';
    protected static ?string $title = 'الأصناف (Flat-List)';

    /** Items belong to flat-list products only (swagger: IFlatListProduct.items). */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->kind === 'flat-list';
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $missing = $ownerRecord->items()->whereNull('image')->count();

        return $missing > 0 ? $missing . ' بلا صورة' : null;
    }

    public static function getBadgeColor(Model $ownerRecord, string $pageClass): ?string
    {
        return 'warning';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make()->schema([
                Forms\Components\TextInput::make('slug')
                    ->label('المعرف (ID)')
                    ->required()
                    ->maxLength(100)
                    ->alphaDash()
                    // Mix rules reference items by this id, so it must survive a
                    // label rename (swagger: IProductVariant.id).
                    ->disabled(fn (?ProductItem $record) => $record !== null)
                    ->dehydrated()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('product_id', $this->getOwnerRecord()->getKey()))
                    ->helperText('ثابت لا يتغير بعد الإنشاء — مثال: nutella · lotus · arabic-coffee')
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
                    ->label('صورة الصنف')
                    ->image()
                    ->disk('public')
                    ->directory('items')
                    ->imagePreviewHeight('100')
                    ->maxSize(2048)
                    ->helperText('مطلوبة — تظهر بجانب الاسم في صفحة الطلب وداخل مودال المكس'),
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
                Tables\Columns\ImageColumn::make('image')
                    ->label('الصورة')
                    ->disk('public')
                    ->square()
                    ->size(48)
                    ->defaultImageUrl('https://placehold.co/48x48/e2e8f0/64748b?text=%E2%80%94'),
                Tables\Columns\TextColumn::make('slug')
                    ->label('المعرف')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
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
            ->filters([
                Tables\Filters\TernaryFilter::make('available')->label('التوفّر'),
                Tables\Filters\TernaryFilter::make('is_premium_mix_flavor')->label('Premium'),
                Tables\Filters\Filter::make('missing_image')
                    ->label('بدون صورة فقط')
                    ->query(fn (Builder $query) => $query->whereNull('image'))
                    ->toggle(),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()->label('إضافة صنف'),
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
