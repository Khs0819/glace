<?php

namespace App\Services\Checkout;

use App\Models\Addon;
use App\Models\Flavor;
use App\Models\Product;
use App\Models\ProductSize;
use App\Models\SizePrice;
use App\Support\FlavorFamily;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Turns what the customer picked into what it costs.
 *
 * The client sends selections, never prices. Everything here is looked up in
 * the catalog as it stands right now, and anything that does not resolve — a
 * product that went unavailable, a size that belongs to another container, a
 * flavour family the size has no price row for — is a 422 rather than a guess.
 * That is the whole point: the amount handed to the payment gateway has to be
 * one this server derived.
 */
class CartPricer
{
    /** @var array<string, Addon>|null Shared addon catalog, loaded once per pricing run. */
    private ?array $sharedAddons = null;

    /**
     * @param  array<int, array<string, mixed>>  $lines
     */
    public function price(array $lines): PricedCart
    {
        $products = $this->loadProducts($lines);

        return new PricedCart(array_values(array_map(
            fn (int $index) => $this->priceLine($index, $lines[$index], $products),
            array_keys($lines),
        )));
    }

    /**
     * @param  array<int, array<string, mixed>>  $lines
     * @return Collection<string, Product>
     */
    private function loadProducts(array $lines): Collection
    {
        $ids = array_values(array_unique(array_filter(array_map(
            fn (array $line) => (string) ($line['productId'] ?? ''),
            $lines,
        ))));

        return Product::query()
            ->with(['containers', 'sizes.prices', 'items', 'mixes', 'addons', 'flavors', 'iceCreamAddonPrices'])
            ->whereIn('id', $ids)
            ->get()
            ->keyBy(fn (Product $product) => (string) $product->getKey());
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  Collection<string, Product>  $products
     */
    private function priceLine(int $index, array $line, Collection $products): PricedLine
    {
        $path    = "items.{$index}";
        $product = $products->get((string) ($line['productId'] ?? ''));

        if (! $product) {
            $this->fail("{$path}.productId", 'هذا المنتج غير موجود');
        }

        // Unavailable products still serve their order page (so a shared link
        // does not 404), but they cannot be bought.
        if (! $product->available) {
            $this->fail("{$path}.productId", "«{$product->name}» غير متوفر حالياً");
        }

        $quantity = (int) ($line['quantity'] ?? 1);

        if ($quantity < 1 || $quantity > 50) {
            $this->fail("{$path}.quantity", 'الكمية يجب أن تكون بين 1 و 50');
        }

        [$selection, $description, $unitPrice] = $product->kind === 'builder'
            ? $this->priceBuilder($path, $line, $product)
            : $this->priceFlatList($path, $line, $product);

        [$units, $addonsTotal, $addonsDescription] = $this->priceAddons($path, $line, $product, $quantity);

        if ($units !== []) {
            $selection['units'] = $units;
        }

        if ($addonsDescription !== '') {
            $description .= ' + ' . $addonsDescription;
        }

        // Line-level extras. "4 x extra biscuit" is four biscuits for the whole
        // line, not four per unit, so this is charged once and never multiplied
        // by quantity (handoff 12, flatSelections/flatAddonTotal).
        [$flatAddons, $flatTotal, $flatDescription] = $this->priceFlatAddons($path, $line, $product);

        if ($flatAddons !== []) {
            $selection['flatAddons'] = $flatAddons;
            $addonsTotal += $flatTotal;
        }

        if ($flatDescription !== '') {
            $description .= ' + ' . $flatDescription;
        }

        if ($notes = trim((string) ($line['notes'] ?? ''))) {
            $selection['notes'] = $notes;
            $description .= ' — ' . $notes;
        }

        return new PricedLine($product, $selection, $description, $unitPrice, $quantity, $addonsTotal);
    }

    // ─── flat-list ──────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $line
     * @return array{0: array<string, mixed>, 1: string, 2: int}
     */
    private function priceFlatList(string $path, array $line, Product $product): array
    {
        if (filled($line['mixId'] ?? null)) {
            return $this->priceMix($path, $line, $product);
        }

        if (filled($line['itemId'] ?? null)) {
            return $this->priceItem($path, $line, $product);
        }

        $this->fail("{$path}.itemId", 'اختر صنفاً من القائمة');
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{0: array<string, mixed>, 1: string, 2: int}
     */
    private function priceItem(string $path, array $line, Product $product): array
    {
        $item = $product->items->firstWhere('slug', (string) $line['itemId']);

        if (! $item) {
            $this->fail("{$path}.itemId", 'هذا الصنف غير موجود ضمن هذا المنتج');
        }

        if (! $item->available) {
            $this->fail("{$path}.itemId", "«{$item->label}» غير متوفر حالياً");
        }

        return [
            ['type' => 'item', 'itemId' => $item->slug, 'label' => $item->label],
            $item->label,
            Money::toAgorot($item->price),
        ];
    }

    /**
     * A mix is priced per chosen flavour, not as basePrice plus extras:
     * across every mix in the catalog basePrice equals pick × flavorPrice, so
     * it is the "from" price the storefront shows, and a premium pick replaces
     * that flavour's price rather than adding to it.
     *
     * @param  array<string, mixed>  $line
     * @return array{0: array<string, mixed>, 1: string, 2: int}
     */
    private function priceMix(string $path, array $line, Product $product): array
    {
        $mix = $product->mixes->firstWhere('slug', (string) $line['mixId']);

        if (! $mix) {
            $this->fail("{$path}.mixId", 'هذا المكس غير موجود ضمن هذا المنتج');
        }

        if (! $mix->available) {
            $this->fail("{$path}.mixId", "«{$mix->label}» غير متاح حالياً");
        }

        $chosen = array_values(array_map('strval', (array) ($line['mixItemIds'] ?? [])));

        if (count($chosen) !== $mix->pick) {
            $this->fail("{$path}.mixItemIds", "«{$mix->label}» يتطلب اختيار {$mix->pick} أصناف بالضبط");
        }

        if (count($chosen) !== count(array_unique($chosen))) {
            $this->fail("{$path}.mixItemIds", 'لا يمكن اختيار نفس الصنف أكثر من مرة في المكس');
        }

        $eligible = array_map('strval', $mix->item_ids ?? []);
        $total    = 0;
        $picked   = [];

        foreach ($chosen as $slug) {
            $item = $product->items->firstWhere('slug', $slug);

            if (! $item || ! in_array($slug, $eligible, true)) {
                $this->fail("{$path}.mixItemIds", "هذا الصنف غير متاح ضمن «{$mix->label}»");
            }

            if (! $item->available) {
                $this->fail("{$path}.mixItemIds", "«{$item->label}» غير متوفر حالياً");
            }

            $total += Money::toAgorot($item->is_premium_mix_flavor
                ? $mix->premium_flavor_price
                : $mix->flavor_price);

            $picked[] = [
                'id'      => $item->slug,
                'label'   => $item->label,
                'premium' => (bool) $item->is_premium_mix_flavor,
            ];
        }

        return [
            ['type' => 'mix', 'mixId' => $mix->slug, 'mixLabel' => $mix->label, 'items' => $picked],
            $mix->label . ': ' . implode(' + ', array_column($picked, 'label')),
            $total,
        ];
    }

    // ─── builder ────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $line
     * @return array{0: array<string, mixed>, 1: string, 2: int}
     */
    private function priceBuilder(string $path, array $line, Product $product): array
    {
        $container = null;

        if ($product->containers->isNotEmpty()) {
            $container = $this->resolve(
                $product->containers,
                $line['containerId'] ?? null,
                $line['containerLabel'] ?? null,
                "{$path}.containerId",
                'اختر النوع',
                'هذا النوع غير موجود ضمن هذا المنتج',
            );

            if (! $container->available) {
                $this->fail("{$path}.containerId", "«{$container->label}» غير متاح حالياً");
            }
        }

        $size = $this->resolve(
            $product->sizes,
            $line['sizeId'] ?? null,
            $line['sizeLabel'] ?? null,
            "{$path}.sizeId",
            'اختر الحجم',
            'هذا الحجم غير موجود ضمن هذا المنتج',
        );

        if (! $size->available) {
            $this->fail("{$path}.sizeId", "«{$size->label}» غير متوفر حالياً");
        }

        // A size scoped to one container must not be bought under another —
        // that is exactly how a cheap size gets paired with a pricier type.
        if ($size->container_slug !== null && $size->container_slug !== $container?->slug) {
            $this->fail("{$path}.sizeId", "«{$size->label}» غير متاح مع هذا النوع");
        }

        $flavors = $this->resolveFlavors($path, $line, $product, $size);
        $family  = $this->deriveFamily($flavors);
        $total   = $this->basePrice($path, $product, $size, $family);

        if ($product->includes_ice_cream_step && $family !== null) {
            $surcharge = $product->iceCreamAddonPrices->firstWhere('flavor_family', $family);

            if (! $surcharge) {
                $this->fail("{$path}.flavorIds", 'لا يوجد سعر معرّف لإضافة البوظة بهذه النكهات');
            }

            $total += Money::toAgorot($surcharge->price);
        }

        $names = array_map(fn (Flavor $flavor) => $flavor->name_ar, $flavors);
        $parts = array_filter([
            $container?->label,
            $size->label,
            $names === [] ? null : ($product->includes_ice_cream_step ? 'بوظة: ' : '') . implode('، ', $names),
        ]);

        return [
            [
                'type'           => 'builder',
                'containerId'    => $container?->slug,
                'containerLabel' => $container?->label,
                'sizeId'         => $size->slug,
                'sizeLabel'      => $size->label,
                'flavorIds'      => array_map(fn (Flavor $flavor) => (string) $flavor->getKey(), $flavors),
                'flavorLabels'   => $names,
                'flavorFamily'   => $family,
            ],
            implode(' · ', $parts),
            $total,
        ];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<int, Flavor>
     */
    private function resolveFlavors(string $path, array $line, Product $product, ProductSize $size): array
    {
        $ids = array_values(array_map('strval', (array) ($line['flavorIds'] ?? [])));
        $max = (int) $size->max_balls;

        if ($max === 0) {
            if ($ids !== []) {
                $this->fail("{$path}.flavorIds", "«{$size->label}» لا يتضمن اختيار نكهات");
            }

            return [];
        }

        if ($ids === []) {
            $this->fail("{$path}.flavorIds", 'اختر نكهة واحدة على الأقل');
        }

        if (count($ids) > $max) {
            $this->fail("{$path}.flavorIds", "«{$size->label}» يتسع لـ {$max} كرات كحد أقصى");
        }

        // repeatable lets the same flavour fill more than one ball; toggle does not.
        if ($product->selection_mode === 'toggle' && count($ids) !== count(array_unique($ids))) {
            $this->fail("{$path}.flavorIds", 'لا يمكن اختيار النكهة نفسها أكثر من مرة في هذا المنتج');
        }

        $offered = $product->flavors->keyBy(fn (Flavor $flavor) => (string) $flavor->getKey());
        $chosen  = [];

        foreach ($ids as $id) {
            $flavor = $offered->get($id);

            if (! $flavor) {
                $this->fail("{$path}.flavorIds", 'هذه النكهة غير متاحة لهذا المنتج');
            }

            if (! $flavor->available) {
                $this->fail("{$path}.flavorIds", "«{$flavor->name_ar}» غير متوفرة حالياً");
            }

            $chosen[] = $flavor;
        }

        return $chosen;
    }

    /**
     * One family means that family's price row; more than one is the `mix`
     * tier, which is a pricing bracket rather than something a flavour is.
     *
     * @param  array<int, Flavor>  $flavors
     */
    private function deriveFamily(array $flavors): ?string
    {
        $families = array_values(array_unique(array_map(
            fn (Flavor $flavor) => (string) $flavor->family,
            $flavors,
        )));

        return match (true) {
            $families === []       => null,
            count($families) === 1 => $families[0],
            default                => FlavorFamily::MIX,
        };
    }

    private function basePrice(string $path, Product $product, ProductSize $size, ?string $family): int
    {
        // The ball picker means two different things depending on the product.
        // On a cup it selects the family the size is priced by. On brad-boza it
        // selects the ice cream scooped on top, while the brad itself has one
        // flat price — so there the family must not be looked up in the grid.
        $allowSoleRow = $family === null || $product->includes_ice_cream_step;
        $row          = $this->priceRow($size, $family, $allowSoleRow);

        if (! $row) {
            $this->fail(
                "{$path}.flavorIds",
                "لا يوجد سعر معرّف لـ«{$size->label}» مع هذه التشكيلة من النكهات",
            );
        }

        return Money::toAgorot($row->price);
    }

    private function priceRow(ProductSize $size, ?string $family, bool $allowSoleRow): ?SizePrice
    {
        if ($family !== null) {
            $row = $size->prices->firstWhere('flavor_family', $family);

            if ($row) {
                return $row;
            }
        }

        return $allowSoleRow && $size->prices->count() === 1 ? $size->prices->first() : null;
    }

    // ─── addons ─────────────────────────────────────────────────────────────

    /**
     * Addons are per unit, not per line: four milkshakes can each carry a
     * different set (MENU_CATALOG "Cart representation"). So `units` must line
     * up one-for-one with the quantity when it is sent at all.
     *
     * @param  array<string, mixed>  $line
     * @return array{0: array<int, array<string, mixed>>, 1: int, 2: string}
     */
    private function priceAddons(string $path, array $line, Product $product, int $quantity): array
    {
        $units = $line['units'] ?? null;

        if (blank($units)) {
            return [[], 0, ''];
        }

        $units = array_values((array) $units);

        if (count($units) !== $quantity) {
            $this->fail("{$path}.units", 'عدد وحدات الإضافات يجب أن يساوي الكمية');
        }

        $catalog  = $this->addonCatalog($product);
        $total    = 0;
        $resolved = [];
        $tally    = [];

        foreach ($units as $u => $unit) {
            $chosen = array_values((array) ($unit['addons'] ?? []));
            $seen   = [];
            $inUnit = [];

            foreach ($chosen as $a => $entry) {
                $key   = "{$path}.units.{$u}.addons.{$a}.id";
                $slug  = (string) ($entry['id'] ?? '');
                $addon = $catalog[$slug] ?? null;

                if (! $addon) {
                    $this->fail($key, 'هذه الإضافة غير معروفة');
                }

                if (! $addon->available) {
                    $this->fail($key, "«{$addon->label}» غير متوفرة حالياً");
                }

                if (isset($seen[$slug])) {
                    $this->fail($key, "«{$addon->label}» مكررة في نفس الوحدة");
                }

                $seen[$slug] = true;

                // A toggle is on or off; only a counter carries a quantity, and
                // never more than the admin allowed.
                $ceiling  = $addon->type === 'counter' ? max(1, (int) ($addon->max_qty ?? 1)) : 1;
                $addonQty = (int) ($entry['quantity'] ?? 1);

                if ($addonQty < 1 || $addonQty > $ceiling) {
                    $this->fail(
                        "{$path}.units.{$u}.addons.{$a}.quantity",
                        "الحد الأقصى لـ«{$addon->label}» هو {$ceiling}",
                    );
                }

                $total += Money::toAgorot($addon->price) * $addonQty;

                $inUnit[] = [
                    'id'       => $addon->slug,
                    'label'    => $addon->label,
                    'quantity' => $addonQty,
                    'price'    => (float) $addon->price,
                ];

                $tally[$addon->label] = ($tally[$addon->label] ?? 0) + $addonQty;
            }

            $resolved[] = ['addons' => $inUnit];
        }

        $description = $tally === [] ? '' : 'إضافات: ' . implode('، ', array_map(
            fn (string $label, int $count) => $count > 1 ? "{$label} ×{$count}" : $label,
            array_keys($tally),
            $tally,
        ));

        return [$resolved, $total, $description];
    }

    /**
     * Find a container or size by slug, falling back to its display label.
     *
     * The storefront sends `type: "صغير"` / `container: "كاسة"` — labels, not
     * slugs (handoff 12). A label is a weak identifier, so it is only consulted
     * when no id was sent, and an ambiguous one is refused rather than guessed:
     * picking the first of two sizes both called "كبير" would silently charge
     * the cheaper one.
     *
     * @param  \Illuminate\Support\Collection<int, \Illuminate\Database\Eloquent\Model>  $options
     */
    private function resolve(
        Collection $options,
        mixed $id,
        mixed $label,
        string $key,
        string $missingMessage,
        string $unknownMessage,
    ): mixed {
        if (filled($id)) {
            $match = $options->firstWhere('slug', (string) $id);

            if (! $match) {
                $this->fail($key, $unknownMessage);
            }

            return $match;
        }

        if (blank($label)) {
            $this->fail($key, $missingMessage);
        }

        $matches = $options->where('label', (string) $label)->values();

        if ($matches->count() > 1) {
            $this->fail($key, 'أكثر من خيار يحمل هذا الاسم — يرجى إعادة اختيار الصنف');
        }

        if ($matches->isEmpty()) {
            $this->fail($key, $unknownMessage);
        }

        return $matches->first();
    }

    /**
     * Extras charged once for the line rather than per unit.
     *
     * Same catalog and the same ceilings as the per-unit addons; the only
     * difference is that the result is not multiplied by quantity.
     *
     * @param  array<string, mixed>  $line
     * @return array{0: array<int, array<string, mixed>>, 1: int, 2: string}
     */
    private function priceFlatAddons(string $path, array $line, Product $product): array
    {
        $chosen = array_values((array) ($line['flatAddons'] ?? []));

        if ($chosen === []) {
            return [[], 0, ''];
        }

        $catalog  = $this->addonCatalog($product);
        $total    = 0;
        $resolved = [];
        $seen     = [];
        $tally    = [];

        foreach ($chosen as $a => $entry) {
            $key   = "{$path}.flatAddons.{$a}.id";
            $slug  = (string) ($entry['id'] ?? '');
            $addon = $catalog[$slug] ?? null;

            if (! $addon) {
                $this->fail($key, 'هذه الإضافة غير معروفة');
            }

            if (! $addon->available) {
                $this->fail($key, "«{$addon->label}» غير متوفرة حالياً");
            }

            if (isset($seen[$slug])) {
                $this->fail($key, "«{$addon->label}» مكررة");
            }

            $seen[$slug] = true;

            $ceiling  = $addon->type === 'counter' ? max(1, (int) ($addon->max_qty ?? 1)) : 1;
            $addonQty = (int) ($entry['quantity'] ?? 1);

            if ($addonQty < 1 || $addonQty > $ceiling) {
                $this->fail(
                    "{$path}.flatAddons.{$a}.quantity",
                    "الحد الأقصى لـ«{$addon->label}» هو {$ceiling}",
                );
            }

            $total += Money::toAgorot($addon->price) * $addonQty;

            $resolved[] = [
                'id'       => $addon->slug,
                'label'    => $addon->label,
                'quantity' => $addonQty,
                'price'    => (float) $addon->price,
            ];

            $tally[$addon->label] = ($tally[$addon->label] ?? 0) + $addonQty;
        }

        $description = 'إضافات على الطلب: ' . implode('، ', array_map(
            fn (string $label, int $count) => $count > 1 ? "{$label} ×{$count}" : $label,
            array_keys($tally),
            $tally,
        ));

        return [$resolved, $total, $description];
    }

    /**
     * The shared catalog, with a product's own addons overriding it by slug —
     * the same precedence GET /menu/products/{slug} publishes.
     *
     * Merged by hand into a plain array on purpose: Eloquent\Collection::merge()
     * re-keys by primary key and array_values()s the result, which would throw
     * the slug keys away and make every lookup here miss.
     *
     * @return array<string, Addon>
     */
    private function addonCatalog(Product $product): array
    {
        $this->sharedAddons ??= Addon::whereNull('product_id')->get()->keyBy('slug')->all();

        $catalog = $this->sharedAddons;

        foreach ($product->addons as $addon) {
            $catalog[$addon->slug] = $addon;
        }

        return $catalog;
    }

    private function fail(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => $message]);
    }
}
