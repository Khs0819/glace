<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Flavor;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
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
                    ->color(fn (string $state): string => match ($state) {
                        'classic' => 'primary',
                        'special' => 'warning',
                        'stevia'  => 'success',
                        default   => 'gray',
                    }),
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
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('إضافة نكهة')
                    ->preloadRecordSelect()
                    ->recordSelectOptionsQuery(fn ($query) => $query->orderBy('family')->orderBy('name_ar'))
                    ->recordTitle(fn (Flavor $record): string => "{$record->name_ar} ({$record->id}) — {$record->family}"),
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
