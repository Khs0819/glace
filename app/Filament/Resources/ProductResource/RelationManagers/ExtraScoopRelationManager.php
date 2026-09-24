<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Models\Addon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * The scoop of ice cream a waffle or a crepe can carry.
 *
 * Each flavour is priced on its own — pistachio is not lotus — and belongs to
 * one of two families the storefront draws as two lists. There is no separate
 * "offer this addon" switch: a product with no flavours here shows nothing,
 * which is why «تعطيل الكل» exists — it takes the addon off the storefront
 * without throwing the prices away.
 *
 * Only for flat-list products. A builder product already has an ice cream step
 * of its own.
 */
class ExtraScoopRelationManager extends RelationManager
{
    protected static string $relationship = 'addons';
    protected static ?string $title = 'بوظة إضافية';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord->kind === 'flat-list';
    }

    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $count = $ownerRecord->addons()->whereNotNull('scoop_family')->count();

        return $count > 0 ? (string) $count : null;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('scoop_family')
                ->label('العائلة')
                ->options(Addon::SCOOP_FAMILIES)
                ->default(Addon::SCOOP_CLASSIC)
                ->required()
                ->native(false),

            Forms\Components\TextInput::make('slug')
                ->label('المعرف')
                ->required()
                ->maxLength(100)
                ->alphaDash()
                ->default('scoop-')
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule) => $rule->where('product_id', $this->getOwnerRecord()->getKey()))
                ->helperText('فريد داخل هذا المنتج — مثال: scoop-special-pistachio'),

            Forms\Components\TextInput::make('label')
                ->label('اسم النكهة')
                ->required()
                ->maxLength(200)
                ->placeholder('بيستاشيو'),

            Forms\Components\TextInput::make('price')
                ->label('السعر (₪)')
                ->numeric()
                ->required()
                ->helperText('لكل نكهة سعرها — النكهات داخل العائلة الواحدة ليست بالضرورة بنفس السعر.'),

            Forms\Components\TextInput::make('sort_order')
                ->label('الترتيب')
                ->numeric()
                ->default(1),

            Forms\Components\Toggle::make('available')
                ->label('متوفرة')
                ->default(true)
                ->helperText('إطفاؤها يُبقيها ظاهرة للزبون كغير متوفرة. لإخفاء الخيار كله استخدم «تعطيل الكل».'),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('scoop_family'))
            ->columns([
                Tables\Columns\TextColumn::make('scoop_family')
                    ->label('العائلة')
                    ->badge()
                    ->formatStateUsing(fn (?string $state) => Addon::SCOOP_FAMILIES[$state] ?? $state)
                    ->color(fn (?string $state) => $state === Addon::SCOOP_SPECIAL ? 'warning' : 'gray'),

                Tables\Columns\TextColumn::make('label')->label('النكهة')->searchable(),
                Tables\Columns\TextColumn::make('slug')->label('المعرف')->copyable()->toggleable(),
                Tables\Columns\TextColumn::make('price')->label('السعر')->suffix(' ₪')->sortable(),
                Tables\Columns\IconColumn::make('available')->label('متوفرة')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('scoop_family')->label('العائلة')->options(Addon::SCOOP_FAMILIES),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('إضافة نكهة')
                    // A scoop is one or none, never four: the same shape as
                    // every other toggle addon, so the order code already
                    // refuses a quantity above one.
                    ->mutateFormDataUsing(fn (array $data) => $data + ['type' => 'toggle', 'max_qty' => null]),

                Tables\Actions\Action::make('disableAll')
                    ->label('تعطيل الكل')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('يختفي خيار البوظة الإضافية من صفحة المنتج، وتبقى النكهات وأسعارها محفوظة.')
                    ->visible(fn () => $this->scoops()->where('available', true)->exists())
                    ->action(function () {
                        $this->scoops()->update(['available' => false]);

                        Notification::make()->title('تم إخفاء البوظة الإضافية من المتجر')->success()->send();
                    }),

                Tables\Actions\Action::make('enableAll')
                    ->label('تفعيل الكل')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn () => $this->scoops()->exists() && ! $this->scoops()->where('available', true)->exists())
                    ->action(function () {
                        $this->scoops()->update(['available' => true]);

                        Notification::make()->title('عادت البوظة الإضافية للمتجر')->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(fn (array $data) => $data + ['type' => 'toggle', 'max_qty' => null]),

                Tables\Actions\Action::make('toggle')
                    ->label(fn (Addon $record) => $record->available ? 'إيقاف' : 'تفعيل')
                    ->color(fn (Addon $record) => $record->available ? 'danger' : 'success')
                    ->action(fn (Addon $record) => $record->update(['available' => ! $record->available])),

                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('لا توجد نكهات بوظة لهذا المنتج')
            ->emptyStateDescription('أضف نكهة ليظهر خيار «أضف بوظة» للزبون على صفحة المنتج.');
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<Addon> */
    private function scoops()
    {
        return $this->getOwnerRecord()->addons()->whereNotNull('scoop_family');
    }
}
