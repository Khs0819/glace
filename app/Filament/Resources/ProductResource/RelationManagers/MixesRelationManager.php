<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Product;
use App\Models\ProductMix;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class MixesRelationManager extends RelationManager
{
    protected static string $relationship = 'mixes';
    protected static ?string $title = 'قواعد المكس (Flat-List)';

    /** Mixes belong to flat-list products only (swagger: IFlatListProduct.mixes). */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->kind === 'flat-list';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('معلومات المكس')->schema([
                Forms\Components\TextInput::make('slug')
                    ->label('المعرف')
                    ->required()
                    ->maxLength(100)
                    ->alphaDash()
                    // Orders reference the mix by this id — stable after creation (handoff 07).
                    ->disabled(fn (?ProductMix $record) => $record !== null)
                    ->dehydrated()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('product_id', $this->getOwnerRecord()->getKey()))
                    ->helperText('مثال: mix · super-mix — ثابت بعد الإنشاء'),
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
                Forms\Components\Toggle::make('available')
                    ->label('متاح')
                    ->default(true)
                    ->onColor('success')
                    ->offColor('danger'),
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

            Forms\Components\Section::make('عناصر المكس')
                ->description('اختر أصناف هذا المنتج المسموح اختيارها داخل المكس — القائمة مقيّدة بأصناف نفس المنتج فقط (handoff 07)')
                ->schema([
                    Forms\Components\Select::make('item_ids')
                        ->label('')
                        ->multiple()
                        ->required()
                        ->searchable()
                        ->preload()
                        ->options(fn () => $this->itemOptions())
                        // A Select validates nothing per-entry on its own: without this
                        // an id that was renamed or belongs to another product would be
                        // stored and the storefront would render a dead mix entry.
                        ->nestedRecursiveRules([
                            fn () => Rule::in(array_keys($this->itemOptions())),
                        ])
                        ->validationMessages(['*.in' => 'صنف غير موجود في هذا المنتج.'])
                        ->helperText('النجمة ★ = صنف Premium يُسعّر بـ premiumFlavorPrice')
                        ->placeholder('اختر صنفاً أو أكثر'),
                ]),
        ]);
    }

    /**
     * itemIds may only reference items[].id on the SAME product (handoff 07 /
     * swagger IMixRule.itemIds) — never flavor ids and never Arabic labels.
     *
     * @return array<string, string>
     */
    private function itemOptions(): array
    {
        /** @var Product $product */
        $product = $this->getOwnerRecord();

        return $product->items()
            ->orderBy('id')
            ->get()
            ->mapWithKeys(fn ($item) => [
                $item->slug => $item->label . ' — ' . $item->slug . ($item->is_premium_mix_flavor ? ' ★' : ''),
            ])
            ->all();
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
                Tables\Columns\IconColumn::make('available')
                    ->label('متاح')
                    ->boolean(),
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
                Tables\Columns\TextColumn::make('item_ids')
                    ->label('الأصناف')
                    ->badge()
                    ->color('info')
                    ->limitList(3)
                    ->expandableLimitedList()
                    ->placeholder('—'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('available')->label('الحالة'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label('إضافة مكس')])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalDescription('الطلبات التاريخية قد تشير لهذا المكس بمعرّفه. للإيقاف المؤقت استخدم مفتاح «متاح» بدل الحذف.'),
            ])
            ->emptyStateHeading('لا توجد قواعد مكس')
            ->emptyStateDescription('المكس خاص بمنتجات Flat-List مثل: كنافة، لقيمات، بان كيك...');
    }
}
