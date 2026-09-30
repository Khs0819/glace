<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use App\Filament\Resources\FlavorResource;
use App\Models\Addon;
use App\Models\Flavor;
use App\Support\FlavorFamily;
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
 * The flavours are **the shop's flavours** — the same rows as القائمة ← النكهات,
 * picked here rather than typed again. That is what makes one switch enough:
 * closing pistachio when it runs out closes it on every product that offers it,
 * and the cashier does it in the one place they already close flavours.
 *
 * What stays on this product is what belongs to this product: the price, since
 * a scoop on a crepe need not cost what it costs on a waffle, and the order the
 * two lists are drawn in.
 *
 * There is no "offer this addon" switch of its own: a product with no flavours
 * here shows nothing, which is why «تعطيل الكل» exists — it takes the control
 * off the storefront without throwing the prices away.
 *
 * Only for flat-list products. A builder product already has an ice cream step.
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
            Forms\Components\Select::make('flavor_id')
                ->label('النكهة')
                ->options(fn () => $this->flavorOptions())
                ->searchable()
                ->required()
                ->native(false)
                ->helperText('من «القائمة ← النكهات». التوفر يُدار هناك ويسري على الموقع كله.')
                // The same flavour twice on one product is two rows for one
                // choice, and the storefront would draw it twice.
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule) => $rule->where('product_id', $this->getOwnerRecord()->getKey()),
                ),

            Forms\Components\TextInput::make('price')
                ->label('السعر (₪)')
                ->numeric()
                ->required()
                ->helperText('سعر هذه النكهة على هذا المنتج — النكهات داخل العائلة الواحدة ليست بالضرورة بنفس السعر.'),

            Forms\Components\TextInput::make('sort_order')
                ->label('الترتيب')
                ->numeric()
                ->default(1),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->whereNotNull('scoop_family')->with('flavor'))
            ->description('التوفر يأتي من «القائمة ← النكهات» — إطفاء نكهة هنا يُطفئها في كل الموقع.')
            ->columns([
                Tables\Columns\TextColumn::make('flavor.family')
                    ->label('العائلة')
                    ->badge()
                    // Drawn exactly as النكهات draws it, because it is the same
                    // value read from the same row.
                    ->formatStateUsing(fn (?string $state) => FlavorFamily::label($state))
                    ->color(fn (?string $state) => FlavorFamily::color($state)),

                Tables\Columns\TextColumn::make('flavor.name_ar')
                    ->label('النكهة')
                    ->searchable()
                    ->weight(\Filament\Support\Enums\FontWeight::SemiBold)
                    ->placeholder('— نكهة محذوفة —')
                    ->description(fn (Addon $record) => $record->flavor
                        ? null
                        : 'كانت: ' . $record->label . ' — اختر نكهة بديلة أو احذف السطر'),

                Tables\Columns\TextColumn::make('price')->label('السعر')->suffix(' ₪')->sortable(),

                Tables\Columns\ToggleColumn::make('flavor.available')
                    ->label('متوفرة')
                    ->onColor('success')
                    ->offColor('danger')
                    ->tooltip('نفس مفتاح النكهة في «القائمة ← النكهات» — يسري على كل المنتجات')
                    ->disabled(fn (Addon $record) => $record->flavor === null)
                    ->getStateUsing(fn (Addon $record) => (bool) $record->flavor?->available)
                    // Written to the flavour, not to this row: one switch, and
                    // it is the one the rest of the menu already obeys.
                    ->updateStateUsing(fn (Addon $record, bool $state) => $record->flavor?->update(['available' => $state])),
            ])
            ->defaultSort('sort_order')
            ->filters([
                Tables\Filters\SelectFilter::make('scoop_family')->label('العائلة')->options(Addon::SCOOP_FAMILIES),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('إضافة نكهة')
                    ->mutateFormDataUsing(fn (array $data) => $this->fromFlavor($data, mintSlug: true)),

                Tables\Actions\Action::make('manageFlavors')
                    ->label('إدارة النكهات')
                    ->icon('heroicon-o-swatch')
                    ->color('gray')
                    ->url(fn () => FlavorResource::getUrl('index'))
                    ->openUrlInNewTab(),

                Tables\Actions\Action::make('disableAll')
                    ->label('تعطيل الكل')
                    ->icon('heroicon-o-eye-slash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('يختفي خيار البوظة الإضافية من صفحة هذا المنتج وحده، وتبقى النكهات وأسعارها محفوظة. لإيقاف نكهة في كل الموقع استخدم مفتاح «متوفرة».')
                    ->visible(fn () => $this->scoops()->where('available', true)->exists())
                    ->action(function () {
                        $this->scoops()->update(['available' => false]);

                        Notification::make()->title('تم إخفاء البوظة الإضافية من هذا المنتج')->success()->send();
                    }),

                Tables\Actions\Action::make('enableAll')
                    ->label('تفعيل الكل')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->visible(fn () => $this->scoops()->exists() && ! $this->scoops()->where('available', true)->exists())
                    ->action(function () {
                        $this->scoops()->update(['available' => true]);

                        Notification::make()->title('عادت البوظة الإضافية لهذا المنتج')->success()->send();
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->mutateFormDataUsing(fn (array $data) => $this->fromFlavor($data, mintSlug: false)),

                Tables\Actions\DeleteAction::make(),
            ])
            ->emptyStateHeading('لا توجد نكهات بوظة لهذا المنتج')
            ->emptyStateDescription('أضف نكهة من قائمة النكهات ليظهر خيار «أضف بوظة» للزبون على صفحة المنتج.');
    }

    /**
     * The flavours a scoop can be, labelled the way النكهات labels them.
     *
     * Only the two families the storefront draws a list for; a ستيفيا flavour
     * has nowhere to appear on the product page.
     *
     * @return array<string, string>
     */
    private function flavorOptions(): array
    {
        return Flavor::query()
            ->whereIn('family', array_keys(Addon::SCOOP_FAMILIES))
            ->orderBy('family')
            ->orderBy('name_ar')
            ->get()
            ->mapWithKeys(fn (Flavor $flavor) => [
                $flavor->id => FlavorFamily::label($flavor->family) . ' · ' . $flavor->name_ar,
            ])
            ->all();
    }

    /**
     * What the row keeps of the flavour it points at.
     *
     * `label` and `scoop_family` are copies, kept in step so the dashboard's
     * own tables and the printed receipt read without a join — and so a scoop
     * whose flavour is later deleted still says what it was.
     *
     * `slug` is different: it is the id the storefront sends back when the
     * scoop is ordered, so it is minted once, on creation, and never again.
     * Re-deriving it on an edit would change the id of a scoop already sitting
     * in somebody's cart.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function fromFlavor(array $data, bool $mintSlug): array
    {
        $flavor = Flavor::find($data['flavor_id'] ?? null);

        // A scoop is one or none, never four: the same shape as every other
        // toggle addon, so the order code already refuses a quantity above one.
        $data['type']    = 'toggle';
        $data['max_qty'] = null;

        if ($flavor) {
            $data['label']        = $flavor->name_ar;
            $data['scoop_family'] = isset(Addon::SCOOP_FAMILIES[$flavor->family])
                ? $flavor->family
                : Addon::SCOOP_CLASSIC;

            if ($mintSlug) {
                $data['slug'] = 'scoop-' . $flavor->id;
            }
        }

        // Offered on this product unless «تعطيل الكل» says otherwise; whether
        // there is any left today is the flavour's answer, not this row's.
        $data['available'] ??= true;

        return $data;
    }

    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<Addon> */
    private function scoops()
    {
        return $this->getOwnerRecord()->addons()->whereNotNull('scoop_family');
    }
}
