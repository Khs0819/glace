<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GlobalAddonResource\Pages;
use App\Models\Addon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class GlobalAddonResource extends Resource
{
    protected static ?string $model = Addon::class;
    protected static ?string $navigationIcon = 'heroicon-o-plus-circle';
    protected static ?string $navigationGroup = 'القائمة';
    protected static ?string $navigationLabel = 'الإضافات المشتركة';
    protected static ?string $modelLabel = 'إضافة مشتركة';
    protected static ?string $pluralModelLabel = 'الإضافات المشتركة';
    protected static ?int $navigationSort = 4;
    protected static ?string $slug = 'global-addons';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->whereNull('product_id');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('معلومات الإضافة')->schema([
                Forms\Components\TextInput::make('slug')
                    ->label('المعرف')
                    ->required()
                    ->maxLength(100)
                    ->helperText('مثال: extra-caramel · extra-biscuit'),
                Forms\Components\TextInput::make('label')
                    ->label('الاسم')
                    ->required()
                    ->maxLength(200)
                    ->placeholder('صوص كراميل إضافي'),
                Forms\Components\TextInput::make('price')
                    ->label('السعر')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->suffix('₪'),
                Forms\Components\TextInput::make('sort_order')
                    ->label('الترتيب')
                    ->numeric()
                    ->default(1),
            ])->columns(2),

            Forms\Components\Section::make('نوع الإضافة')
                ->description('يحدد كيف يختار العميل هذه الإضافة في سلة التسوق')
                ->schema([
                    Forms\Components\ToggleButtons::make('type')
                        ->label('النوع')
                        ->options([
                            'toggle'  => 'Toggle — تشغيل/إيقاف',
                            'counter' => 'Counter — عداد (+/−)',
                        ])
                        ->icons([
                            'toggle'  => 'heroicon-o-check-circle',
                            'counter' => 'heroicon-o-plus-circle',
                        ])
                        ->default('toggle')
                        ->required()
                        ->live()
                        ->inline(),
                    Forms\Components\TextInput::make('max_qty')
                        ->label('الحد الأقصى للعداد')
                        ->numeric()
                        ->minValue(1)
                        ->nullable()
                        ->helperText('مثال: 10 — يُترك فارغاً إذا لا يوجد حد')
                        ->visible(fn (Forms\Get $get) => $get('type') === 'counter'),
                ])->columns(2)->aside(),

            Forms\Components\Section::make('الحالة')->schema([
                Forms\Components\Toggle::make('available')
                    ->label('متوفرة')
                    ->default(true)
                    ->onColor('success')
                    ->offColor('danger')
                    ->helperText('الإضافات غير المتوفرة مخفية من اختيار العميل'),
            ])->aside(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
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
                Tables\Columns\TextColumn::make('type')
                    ->label('النوع')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'counter' ? 'warning' : 'primary')
                    ->formatStateUsing(fn (string $state): string => $state === 'counter' ? '🔢 Counter' : '✅ Toggle'),
                Tables\Columns\TextColumn::make('max_qty')
                    ->label('الحد الأقصى')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),
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
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([Tables\Actions\BulkActionGroup::make([
                Tables\Actions\DeleteBulkAction::make(),
            ])])
            ->emptyStateHeading('لا توجد إضافات مشتركة')
            ->emptyStateDescription('هذه الإضافات تُعرض للعميل في كل المنتجات عبر GET /api/menu/addons');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListGlobalAddons::route('/'),
            'create' => Pages\CreateGlobalAddon::route('/create'),
            'edit'   => Pages\EditGlobalAddon::route('/{record}/edit'),
        ];
    }
}
