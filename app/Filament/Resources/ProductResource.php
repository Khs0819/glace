<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ManagerEdits;
use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\MenuCategory;
use App\Models\Product;
use App\Support\Staff;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ProductResource extends Resource
{
    use ManagerEdits;

    protected static ?string $model = Product::class;
    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';
    protected static ?string $navigationGroup = 'القائمة';
    protected static ?string $navigationLabel = 'المنتجات';
    protected static ?string $modelLabel = 'منتج';
    protected static ?string $pluralModelLabel = 'المنتجات';
    protected static ?int $navigationSort = 3;
    protected static ?string $recordTitleAttribute = 'name';

    public static function getNavigationBadge(): ?string
    {
        $off = static::getModel()::where('available', false)->count();
        return $off > 0 ? (string) $off . ' مُوقف' : null;
    }

    public static function getNavigationBadgeColor(): ?string { return 'warning'; }

    /**
     * The counter opens a product — it could not before, and it has to.
     *
     * Switching the product off from the list is one thing; the one that runs
     * out mid-evening is usually an *item* inside it — one flavour of waffle,
     * one size — and those live on this page. So the page opens for everyone,
     * and what the counter finds on it is a switch and nothing else: the form
     * below hands the two roles different schemas, and the tabs beside it gate
     * their own writes through ManagerEditsRelation.
     */
    public static function canEdit(Model $record): bool
    {
        return true;
    }

    public static function form(Form $form): Form
    {
        // Not the full form with its fields disabled: Filament resolves a
        // disabled container before a field's own setting, so there is no
        // re-enabling one toggle inside a locked form. A separate schema is
        // also the plainer screen — the counter is handed the one control it
        // came for instead of thirty greyed-out ones.
        if (! Staff::isManager()) {
            return $form->schema(static::counterSchema());
        }

        return $form->schema([
            Forms\Components\Tabs::make('Product')
                ->tabs([
                    // ─── Tab 1: المعلومات الأساسية ──────────────────────────
                    Forms\Components\Tabs\Tab::make('المعلومات الأساسية')
                        ->icon('heroicon-o-information-circle')
                        ->schema([
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('اسم المنتج')
                                    ->required()
                                    ->maxLength(200)
                                    ->columnSpan(2),
                                Forms\Components\TextInput::make('slug')
                                    ->label('Slug (معرف الرابط)')
                                    ->required()
                                    ->maxLength(100)
                                    ->unique(ignoreRecord: true)
                                    ->helperText('ثابت — يُستخدم في URL ولا يتغيّر')
                                    ->columnSpan(1),
                            ]),
                            Forms\Components\Grid::make(3)->schema([
                                Forms\Components\Select::make('category_id')
                                    ->label('الفئة')
                                    ->options(MenuCategory::orderBy('sort_order')->pluck('label', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->preload(),
                                Forms\Components\ToggleButtons::make('kind')
                                    ->label('نوع المنتج')
                                    ->options([
                                        'builder'   => 'Builder (كاسة / براد)',
                                        'flat-list' => 'Flat-List (قائمة)',
                                    ])
                                    ->icons([
                                        'builder'   => 'heroicon-o-wrench',
                                        'flat-list' => 'heroicon-o-list-bullet',
                                    ])
                                    ->required()
                                    ->live()
                                    ->inline(),
                                Forms\Components\TextInput::make('sort_order')
                                    ->label('الترتيب')
                                    ->numeric()
                                    ->default(1)
                                    ->minValue(0),
                            ]),
                            Forms\Components\Textarea::make('description')
                                ->label('الوصف (يظهر في صفحة الطلب)')
                                ->rows(2)
                                ->maxLength(500),
                            Forms\Components\FileUpload::make('image')
                                ->label('صورة المنتج')
                                ->image()
                                ->disk('public')
                                ->directory('products')
                                ->imagePreviewHeight('150')
                                ->maxSize(2048),
                        ]),

                    // ─── Tab 2: إعدادات العرض ───────────────────────────────
                    Forms\Components\Tabs\Tab::make('إعدادات العرض')
                        ->icon('heroicon-o-eye')
                        ->schema([
                            Forms\Components\Section::make('الحالة')->schema([
                                Forms\Components\Toggle::make('available')
                                    ->label('متوفر في القائمة')
                                    ->helperText('إذا أُوقف يظهر للعميل مع علامة "غير متوفر"')
                                    ->default(true)
                                    ->onColor('success')
                                    ->offColor('danger'),
                            ])->aside(),
                            Forms\Components\Section::make('خيارات الواجهة')->description('تحكّم في عناصر الواجهة الظاهرة للعميل')->schema([
                                Forms\Components\Toggle::make('has_notes')
                                    ->label('حقل "أضف ملاحظة"'),
                                Forms\Components\Toggle::make('has_favorites')
                                    ->label('زر المفضلة ♥ على العناصر'),
                                Forms\Components\Toggle::make('has_image_zoom')
                                    ->label('تكبير الصورة عند النقر'),
                                Forms\Components\Toggle::make('in_store_only')
                                    ->label('تحذير "داخل المحل فقط"')
                                    ->helperText('بان كيك، وافل، كريب، بيتزا'),
                                Forms\Components\Toggle::make('has_extra_biscuit_addon')
                                    ->label('إضافة بسكوت إضافي (+1₪)'),
                            ])->columns(2),
                        ]),

                    // ─── Tab 3: إعدادات Builder ─────────────────────────────
                    Forms\Components\Tabs\Tab::make('إعدادات Builder')
                        ->icon('heroicon-o-wrench')
                        ->visible(fn (Forms\Get $get) => $get('kind') === 'builder')
                        ->schema([
                            Forms\Components\Section::make('طريقة اختيار النكهات')->schema([
                                Forms\Components\ToggleButtons::make('selection_mode')
                                    ->label('وضع الاختيار')
                                    ->options([
                                        'repeatable' => 'Repeatable — نفس النكهة أكثر من مرة',
                                        'toggle'     => 'Toggle — كل نكهة مرة واحدة فقط',
                                    ])
                                    ->inline()
                                    ->helperText('كاسة/عائلي = repeatable · براد مع بوظة = toggle'),
                                Forms\Components\CheckboxList::make('flavor_families')
                                    ->label('عائلات النكهات المتاحة')
                                    ->options(\App\Support\FlavorFamily::pricingOptions())
                                    ->columns(4)
                                    ->helperText('تحدّد أي نكهات يمكن ربطها بالمنتج · «مكس» تسعيرة فقط ولا تحمل نكهات · البراد الصادة: لا تحتاج عائلات (لا يوجد picker للنكهات)'),
                            ])->aside(),
                            Forms\Components\Section::make('جدول الأسعار')->schema([
                                Forms\Components\TextInput::make('pricing_label')
                                    ->label('عنوان جدول الأسعار (اختياري)')
                                    ->placeholder('مثال: أسعار البراد')
                                    ->maxLength(100)
                                    ->helperText('يُستخدم عندما تتشارك جميع الأحجام جدولاً واحداً'),
                            ])->aside(),
                            Forms\Components\Section::make('براد مع بوظة فقط')->schema([
                                Forms\Components\Toggle::make('includes_ice_cream_step')
                                    ->label('يتضمن خطوة "أضف بوظة"')
                                    ->helperText('فعّل هذا الخيار لمنتج brad-boza فقط'),
                            ])->aside(),
                        ]),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('')
                    ->disk('public')
                    ->square()
                    ->size(48)
                    ->defaultImageUrl(fn () => 'https://placehold.co/48x48/f59e0b/ffffff?text=🍦'),
                Tables\Columns\TextColumn::make('name')
                    ->label('المنتج')
                    ->searchable()
                    ->sortable()
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold)
                    ->description(fn (Product $r) => $r->slug),
                Tables\Columns\TextColumn::make('category.label')
                    ->label('الفئة')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                Tables\Columns\TextColumn::make('kind')
                    ->label('النوع')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $state === 'builder' ? '⚙ Builder' : '📋 Flat-List')
                    ->color(fn (string $state): string => match ($state) {
                        'builder'   => 'primary',
                        'flat-list' => 'warning',
                        default     => 'gray',
                    }),
                Tables\Columns\ToggleColumn::make('available')
                    ->label('متوفر')
                    ->onColor('success')
                    ->offColor('danger'),
                // items[].image is required on every variant (handoff 01), so
                // expose the remaining upload backlog per product.
                Tables\Columns\TextColumn::make('items_missing_image_count')
                    ->label('أصناف بلا صورة')
                    ->counts(['items as items_missing_image_count' => fn (Builder $query) => $query->whereNull('image')])
                    ->badge()
                    ->alignCenter()
                    ->color(fn ($state) => $state > 0 ? 'warning' : 'success')
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state : '✓'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable()
                    ->alignCenter()
                    ->width(60),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category_id')
                    ->label('الفئة')
                    ->options(MenuCategory::orderBy('sort_order')->pluck('label', 'id')),
                Tables\Filters\SelectFilter::make('kind')
                    ->label('النوع')
                    ->options(['builder' => 'Builder', 'flat-list' => 'Flat List']),
                Tables\Filters\TernaryFilter::make('available')
                    ->label('الحالة'),
                Tables\Filters\Filter::make('items_missing_image')
                    ->label('فيه أصناف بلا صورة')
                    ->query(fn (Builder $query) => $query->whereHas('items', fn (Builder $q) => $q->whereNull('image')))
                    ->toggle(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->icon('heroicon-m-pencil-square'),
                Tables\Actions\DeleteAction::make()->icon('heroicon-m-trash'),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('enable_all')
                        ->label('تفعيل المحدد')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['available' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('disable_all')
                        ->label('إيقاف المحدد')
                        ->icon('heroicon-o-eye-slash')
                        ->color('danger')
                        ->action(fn ($records) => $records->each->update(['available' => false]))
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Filament reads relation managers from getRelations() — a differently named
     * method is silently ignored, which is why the أصناف / مكسات / أنواع / أحجام
     * panels never appeared on the edit screen (handoff tickets 01 · 05 · 07).
     */
    /**
     * What the counter sees when it opens a product.
     *
     * One switch, and the name of the thing it switches, so there is no
     * guessing which product the page is on. Everything else — the price, the
     * slug, the category, the flavour families — is the manager's, and showing
     * it locked would only invite the question of how to unlock it.
     *
     * @return array<int, Forms\Components\Component>
     */
    private static function counterSchema(): array
    {
        return [
            Forms\Components\Section::make('التوفر')
                ->description('أوقف المنتج أو أعده. ولإيقاف صنف بعينه نفد — افتح تبويب «الأصناف» بالأسفل.')
                ->schema([
                    Forms\Components\Placeholder::make('product_name')
                        ->label('المنتج')
                        ->content(fn (?Product $record) => $record?->name ?? '—'),

                    Forms\Components\Toggle::make('available')
                        ->label('متوفر في القائمة')
                        ->helperText('إذا أُوقف يظهر للعميل مع علامة «غير متوفر»')
                        ->onColor('success')
                        ->offColor('danger'),
                ])
                ->columns(2),
        ];
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ItemsRelationManager::class,
            RelationManagers\MixesRelationManager::class,
            RelationManagers\ContainersRelationManager::class,
            RelationManagers\SizesRelationManager::class,
            RelationManagers\FlavorsRelationManager::class,
            RelationManagers\IceCreamAddonPricesRelationManager::class,
            RelationManagers\ProductAddonsRelationManager::class,
            RelationManagers\ExtraScoopRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit'   => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
