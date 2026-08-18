<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FlavorResource\Pages;
use App\Filament\Resources\FlavorResource\RelationManagers;
use App\Models\Flavor;
use App\Support\FlavorFamily;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FlavorResource extends Resource
{
    protected static ?string $model = Flavor::class;
    protected static ?string $navigationIcon = 'heroicon-o-swatch';
    protected static ?string $navigationGroup = 'القائمة';
    protected static ?string $navigationLabel = 'النكهات';
    protected static ?string $modelLabel = 'نكهة';
    protected static ?string $pluralModelLabel = 'النكهات';
    protected static ?int $navigationSort = 2;
    protected static ?string $recordTitleAttribute = 'name_ar';

    public static function getNavigationBadge(): ?string
    {
        $off = static::getModel()::where('available', false)->count();
        return $off > 0 ? (string) $off . ' مُوقف' : null;
    }

    public static function getNavigationBadgeColor(): ?string { return 'danger'; }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('products');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('معلومات النكهة')->schema([
                Forms\Components\TextInput::make('id')
                    ->label('المعرف (Slug)')
                    ->required()
                    ->maxLength(100)
                    ->disabled(fn ($record) => $record !== null)
                    ->dehydrated()
                    ->helperText('ثابت لا يتغيّر — مثال: chocolate · pistachio'),
                Forms\Components\TextInput::make('name_ar')
                    ->label('الاسم بالعربي')
                    ->required()
                    ->maxLength(100),
                Forms\Components\TextInput::make('name_en')
                    ->label('الاسم بالإنجليزي')
                    ->maxLength(100),
                Forms\Components\ToggleButtons::make('family')
                    ->label('العائلة')
                    ->options(FlavorFamily::flavorOptions())
                    ->colors(collect(FlavorFamily::flavorOptions())
                        ->map(fn ($label, $family) => FlavorFamily::color($family))
                        ->all())
                    ->required()
                    ->inline()
                    ->helperText('تظهر النكهة فقط في المنتجات التي فعّلت هذه العائلة في إعدادات Builder'),
                Forms\Components\FileUpload::make('image')
                    ->label('صورة النكهة')
                    ->image()
                    ->disk('public')
                    ->directory('flavors')
                    ->imagePreviewHeight('150')
                    ->maxSize(2048)
                    ->columnSpanFull(),
            ])->columns(2),

            Forms\Components\Section::make('الخيارات')->schema([
                Forms\Components\Toggle::make('available')
                    ->label('متوفرة')
                    ->default(true)
                    ->onColor('success')
                    ->offColor('danger')
                    ->helperText('غير المتوفرة تظهر بتأثير greyed-out للعميل'),
                Forms\Components\Toggle::make('is_premium_mix_flavor')
                    ->label('نكهة مميزة Premium')
                    ->helperText('يُحمَّل بـ premiumFlavorPrice عوضاً عن flavorPrice في المكس'),
            ])->columns(2)->aside(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Slug')
                    ->badge()
                    ->color('gray')
                    ->searchable(),
                Tables\Columns\TextColumn::make('name_ar')
                    ->label('الاسم')
                    ->searchable()
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold),
                Tables\Columns\TextColumn::make('name_en')
                    ->label('English')
                    ->searchable()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('family')
                    ->label('العائلة')
                    ->badge()
                    ->color(fn (?string $state): string => FlavorFamily::color($state))
                    ->formatStateUsing(fn (?string $state): string => FlavorFamily::label($state)),
                Tables\Columns\TextColumn::make('products_count')
                    ->label('المنتجات')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'danger')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? "{$state} منتج" : 'غير مربوطة')
                    ->tooltip('عدد منتجات builder التي تعرض هذه النكهة')
                    ->sortable(),
                Tables\Columns\ToggleColumn::make('available')
                    ->label('متوفرة')
                    ->onColor('success')
                    ->offColor('danger'),
                Tables\Columns\IconColumn::make('is_premium_mix_flavor')
                    ->label('Premium')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->trueColor('warning')
                    ->falseIcon('heroicon-o-minus')
                    ->falseColor('gray'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('family')
                    ->label('العائلة')
                    ->options(FlavorFamily::flavorOptions()),
                Tables\Filters\TernaryFilter::make('available')
                    ->label('الحالة')
                    ->trueLabel('متوفرة فقط')
                    ->falseLabel('غير المتوفرة فقط')
                    ->placeholder('الكل'),
                Tables\Filters\Filter::make('unlinked')
                    ->label('غير مربوطة بأي منتج')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('products')),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('enable')
                        ->label('تفعيل المحدد')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->action(fn ($records) => $records->each->update(['available' => true]))
                        ->deselectRecordsAfterCompletion(),
                    Tables\Actions\BulkAction::make('disable')
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

    public static function getRelations(): array
    {
        return [
            RelationManagers\ProductsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListFlavors::route('/'),
            'create' => Pages\CreateFlavor::route('/create'),
            'edit'   => Pages\EditFlavor::route('/{record}/edit'),
        ];
    }
}
