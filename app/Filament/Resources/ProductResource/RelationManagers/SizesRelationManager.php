<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\ProductSize;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;

class SizesRelationManager extends RelationManager
{
    protected static string $relationship = 'sizes';
    protected static ?string $title = 'الأحجام والأسعار (Builder)';

    /** Sizes belong to builder products only (swagger: IBuilderProduct.sizes). */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->kind === 'builder';
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('معلومات الحجم')->schema([
                Forms\Components\TextInput::make('slug')
                    ->label('المعرف')
                    ->required()
                    ->maxLength(100)
                    ->alphaDash()
                    ->disabled(fn (?ProductSize $record) => $record !== null)
                    ->dehydrated()
                    ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('product_id', $this->getOwnerRecord()->getKey()))
                    ->helperText('ثابت بعد الإنشاء — مثال: cup-small · plastic-half'),
                Forms\Components\TextInput::make('label')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(100)
                    ->helperText('مثال: صغير · وسط · كبير'),
                Forms\Components\TextInput::make('max_balls')
                    ->label('أقصى عدد كرات')
                    ->numeric()
                    ->default(0)
                    ->minValue(0)
                    ->helperText('0 = لا يوجد picker للنكهات (كالبراد)'),
                // containerId must reference a container of the SAME product
                // (swagger: ISizeOption.containerId) — a free-text value here
                // silently detaches the size from its price table.
                Forms\Components\Select::make('container_slug')
                    ->label('مخصص لنوع (containerId)')
                    ->options(fn () => $this->containerOptions())
                    ->searchable()
                    ->native(false)
                    // A Select adds no `in` rule of its own; a stale containerId
                    // silently detaches the size from its price table.
                    ->rules([fn () => Rule::in(array_keys($this->containerOptions()))])
                    ->validationMessages(['in' => 'هذا النوع غير موجود في هذا المنتج.'])
                    ->placeholder('حجم مشترك — ينطبق على جميع الأنواع')
                    ->helperText('اتركه فارغاً للأحجام المشتركة (مثل البراد مع بوظة)'),
                Forms\Components\FileUpload::make('image')
                    ->label('صورة الحجم')
                    ->image()
                    ->disk('public')
                    ->directory('sizes')
                    ->imagePreviewHeight('100')
                    ->maxSize(2048)
                    ->helperText('اختياري — يظهر بجانب صف الحجم في العائلي'),
                Forms\Components\Toggle::make('available')
                    ->label('متوفر')
                    ->default(true)
                    ->onColor('success')
                    ->offColor('danger')
                    ->helperText('غير المتوفر يظهر بشارة "غير متوفر" ولا يختفي'),
                Forms\Components\TextInput::make('sort_order')
                    ->label('الترتيب')
                    ->numeric()
                    ->default(1),
            ])->columns(2),

            Forms\Components\Section::make('شبكة الأسعار')
                ->description('أضف سعراً لكل عائلة نكهة متاحة في هذا الحجم')
                ->schema([
                    Forms\Components\Repeater::make('prices')
                        ->relationship()
                        ->label('')
                        ->schema([
                            Forms\Components\Select::make('flavor_family')
                                ->label('عائلة النكهة')
                                ->options([
                                    'classic' => '🍦 كلاسيك',
                                    'special' => '⭐ سبيشال',
                                    'mix'     => '🔀 مكس',
                                ])
                                ->required()
                                ->distinct(),
                            Forms\Components\TextInput::make('price')
                                ->label('السعر')
                                ->numeric()
                                ->required()
                                ->minValue(0)
                                ->suffix('₪'),
                        ])
                        ->columns(2)
                        ->addActionLabel('إضافة سعر')
                        ->reorderable(false)
                        ->defaultItems(0),
                ]),
        ]);
    }

    /** @return array<string, string> */
    private function containerOptions(): array
    {
        return $this->getOwnerRecord()
            ->containers()
            ->orderBy('sort_order')
            ->get()
            ->mapWithKeys(fn ($container) => [
                $container->slug => $container->label . ' — ' . $container->slug,
            ])
            ->all();
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
                    ->color('gray'),
                Tables\Columns\TextColumn::make('label')
                    ->label('الاسم')
                    ->weight(\Filament\Support\Enums\FontWeight::Medium),
                Tables\Columns\TextColumn::make('max_balls')
                    ->label('الكرات')
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'primary' : 'gray'),
                Tables\Columns\TextColumn::make('container_slug')
                    ->label('الحاوية')
                    ->badge()
                    ->color('info')
                    ->placeholder('الكل'),
                Tables\Columns\ToggleColumn::make('available')
                    ->label('متوفر')
                    ->onColor('success')
                    ->offColor('danger'),
                Tables\Columns\TextColumn::make('prices')
                    ->label('الأسعار')
                    ->badge()
                    ->color('success')
                    ->getStateUsing(fn (ProductSize $record) => $record->prices
                        ->map(fn ($price) => $price->flavor_family . ': ' . rtrim(rtrim(number_format($price->price, 2, '.', ''), '0'), '.') . '₪')
                        ->all())
                    ->placeholder('لا أسعار'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->alignCenter(),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->filters([
                Tables\Filters\TernaryFilter::make('available')->label('التوفّر'),
            ])
            ->headerActions([Tables\Actions\CreateAction::make()->label('إضافة حجم')])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('لا توجد أحجام')
            ->emptyStateDescription('أضف الأحجام مع أسعار عائلات النكهات.');
    }
}
