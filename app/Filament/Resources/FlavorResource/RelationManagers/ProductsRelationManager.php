<?php

namespace App\Filament\Resources\FlavorResource\RelationManagers;

use App\Models\Product;
use App\Support\FlavorFamily;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The other side of ProductResource's flavors relation manager: which builders
 * currently serve this flavor. Attaching is limited to builders that declare
 * the flavor's family, so both ends of `product_flavor` stay consistent.
 */
class ProductsRelationManager extends RelationManager
{
    protected static string $relationship = 'products';
    protected static ?string $title = 'المنتجات التي تعرض هذه النكهة';

    /** Builders that declare this flavor's family in their Builder settings. */
    protected function eligibleProductsQuery(Builder $query): Builder
    {
        return $query
            ->where('kind', 'builder')
            ->whereJsonContains('flavor_families', $this->getOwnerRecord()->family)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('')
                    ->disk('public')
                    ->square()
                    ->size(48)
                    ->defaultImageUrl('https://placehold.co/48x48/f59e0b/ffffff?text=🍦'),
                Tables\Columns\TextColumn::make('name')
                    ->label('المنتج')
                    ->weight(\Filament\Support\Enums\FontWeight::Medium)
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->label('Slug')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('flavor_families')
                    ->label('عائلات المنتج')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => FlavorFamily::label($state))
                    ->color(fn (?string $state): string => FlavorFamily::color($state)),
                Tables\Columns\IconColumn::make('available')
                    ->label('متاح')
                    ->boolean(),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('ربط بمنتج')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $this->eligibleProductsQuery($query))
                    ->recordTitle(fn (Product $record): string => "{$record->name} ({$record->slug})"),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()->label('إلغاء الربط'),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make()->label('إلغاء ربط المحدد'),
            ])
            ->emptyStateHeading('هذه النكهة غير مربوطة بأي منتج')
            ->emptyStateDescription('لن تظهر للعميل حتى تُربط بمنتج builder يعرض عائلتها.')
            ->emptyStateIcon('heroicon-o-link-slash');
    }
}
