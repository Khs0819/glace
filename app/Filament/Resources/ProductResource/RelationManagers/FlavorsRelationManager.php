<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Flavor;
use App\Support\FlavorFamily;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FlavorsRelationManager extends RelationManager
{
    protected static string $relationship = 'flavors';
    protected static ?string $title = 'نكهات المنتج (Builder)';

    /**
     * Only builders with a flavor step serve flavors[] (handoff 02).
     * `brad` has no flavor picker, so it declares no flavor families.
     */
    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->kind === 'builder' && ! empty($ownerRecord->flavor_families);
    }

    /**
     * The families this product actually offers, minus the pricing-only `mix`
     * tier. Attaching outside this list is what produced flavors whose family
     * the storefront has no tab or price row for.
     *
     * @return array<int, string>
     */
    protected function offeredFamilies(): array
    {
        return FlavorFamily::pickableFrom($this->getOwnerRecord()->flavor_families);
    }

    protected function offersFamily(?string $family): bool
    {
        return in_array($family, $this->offeredFamilies(), true);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('id')
                ->label('المعرف')
                ->disabled(),
            Forms\Components\TextInput::make('name_ar')
                ->label('الاسم العربي')
                ->disabled(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('')
                    ->disk('public')
                    ->size(48)
                    ->defaultImageUrl('https://placehold.co/48x48/f59e0b/ffffff?text=🍦'),
                Tables\Columns\TextColumn::make('id')
                    ->label('المعرف')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('name_ar')
                    ->label('الاسم')
                    ->weight(\Filament\Support\Enums\FontWeight::Medium),
                Tables\Columns\TextColumn::make('family')
                    ->label('العائلة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => FlavorFamily::label($state))
                    ->color(fn (Flavor $record): string => $this->offersFamily($record->family)
                        ? FlavorFamily::color($record->family)
                        : 'danger')
                    ->icon(fn (Flavor $record): ?string => $this->offersFamily($record->family)
                        ? null
                        : 'heroicon-o-exclamation-triangle')
                    ->tooltip(fn (Flavor $record): ?string => $this->offersFamily($record->family)
                        ? null
                        : 'هذه العائلة غير مفعّلة في إعدادات Builder — فعّلها أو أزل النكهة'),
                Tables\Columns\ToggleColumn::make('available')
                    ->label('متوفر')
                    ->onColor('success')
                    ->offColor('danger'),
                Tables\Columns\IconColumn::make('is_premium_mix_flavor')
                    ->label('Premium')
                    ->boolean()
                    ->trueIcon('heroicon-o-star')
                    ->falseIcon('heroicon-o-minus'),
            ])
            ->defaultSort('family')
            ->filters([
                Tables\Filters\SelectFilter::make('family')
                    ->label('العائلة')
                    ->options(FlavorFamily::flavorOptions()),
                Tables\Filters\Filter::make('family_not_offered')
                    ->label('عائلة غير مفعّلة للمنتج')
                    ->query(fn (Builder $query): Builder => $query->whereNotIn(
                        'flavors.family',
                        $this->offeredFamilies(),
                    )),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('إضافة نكهة')
                    ->preloadRecordSelect()
                    // Only offer flavors from families this product declares, so a
                    // flavor can never land on a product that has no tab or price
                    // row for its family.
                    ->recordSelectOptionsQuery(fn (Builder $query): Builder => $query
                        ->whereIn('family', $this->offeredFamilies())
                        ->orderBy('family')
                        ->orderBy('name_ar'))
                    ->recordTitle(fn (Flavor $record): string => "{$record->name_ar} ({$record->id}) — " . FlavorFamily::label($record->family)),
            ])
            ->actions([
                Tables\Actions\DetachAction::make()->label('إزالة'),
            ])
            ->bulkActions([
                Tables\Actions\DetachBulkAction::make()->label('إزالة المحدد'),
            ])
            ->emptyStateHeading('لا توجد نكهات مرتبطة')
            ->emptyStateDescription('هذا المنتج هو builder — أضف النكهات التي يعرضها لاختيار الكرات.')
            ->emptyStateIcon('heroicon-o-squares-plus');
    }
}
