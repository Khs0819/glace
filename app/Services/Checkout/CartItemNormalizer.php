<?php

namespace App\Services\Checkout;

/**
 * Translates the storefront's cart line into the shape CartPricer prices.
 *
 * The two shapes disagree, and the disagreement matters. The frontend sends
 * what it needs to *render* a line — a flat `selections[]` array of
 * `{kind, id, label, qty, unitPrice}`, plus `type`/`container` as display
 * labels. The pricer needs what it takes to *re-derive the price* from the
 * catalog: a container slug, a size slug, flavour ids, an item or a mix.
 *
 * So this fans `selections[]` out by `kind` into those fields, and nothing here
 * reads a price: `unitPrice`, `addonTotal` and `flatAddonTotal` arrive on the
 * request and are dropped on the floor. Handoff 12 is unambiguous that the
 * server prices from `productId` / selection ids / `quantity` / `couponCode`
 * and from nothing else — that is the single most important rule in the file.
 *
 * Two tiers of addon, because the storefront charges them differently:
 *   `selections[kind=addon]`     — per unit. Four milkshakes may each carry a
 *                                  different set, so these multiply by quantity.
 *   `flatSelections[kind=addon]` — per line. "4 × extra biscuit" is four
 *                                  biscuits for the line, not four per unit.
 *
 * Lines already in the pricer's own shape (the `/checkout/quote` callers, and
 * the existing test suite) pass through untouched — every native key wins over
 * anything derived here.
 */
class CartItemNormalizer
{
    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public static function normalize(array $items): array
    {
        return array_values(array_map(self::line(...), $items));
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>
     */
    private static function line(mixed $item): array
    {
        if (! is_array($item)) {
            return [];
        }

        $quantity = max(1, (int) ($item['quantity'] ?? 1));
        $grouped  = self::groupByKind($item['selections'] ?? []);

        $line = [
            'productId' => $item['productId'] ?? $item['product_id'] ?? null,
            'quantity'  => $quantity,
            'notes'     => $item['notes'] ?? null,
        ];

        // ─── what was chosen ────────────────────────────────────────────────

        $line['containerId'] = $item['containerId'] ?? self::firstId($grouped['container'] ?? []);
        $line['sizeId']      = $item['sizeId'] ?? self::firstId($grouped['size'] ?? []) ?? self::firstId($grouped['type'] ?? []);
        $line['itemId']      = $item['itemId'] ?? self::firstId($grouped['item'] ?? []);
        $line['mixId']       = $item['mixId'] ?? self::firstId($grouped['mix'] ?? []);

        // `type` and `container` are labels ("صغير", "كاسة"), not slugs. They are
        // only a fallback: a label is not a stable identifier — two products can
        // both call a size "كبير" — so the pricer only consults these when no id
        // was sent, and refuses the line if the label is ambiguous.
        $line['sizeLabel']      = self::label($item['type'] ?? null);
        $line['containerLabel'] = self::label($item['container'] ?? null);

        $line['flavorIds'] = $item['flavorIds']
            ?? self::expand($grouped['flavor'] ?? []);

        $line['mixItemIds'] = $item['mixItemIds']
            ?? self::ids($grouped['mixItem'] ?? $grouped['mix-item'] ?? $grouped['flavorItem'] ?? []);

        // ─── extras ─────────────────────────────────────────────────────────

        // The same set on every unit: the frontend's per-line `selections` say
        // what one unit carries, and the line is `quantity` of that unit.
        $line['units'] = $item['units'] ?? self::units($grouped['addon'] ?? [], $quantity);

        $line['flatAddons'] = $item['flatAddons']
            ?? self::addons(self::groupByKind($item['flatSelections'] ?? [])['addon'] ?? []);

        return array_filter($line, static fn ($value) => $value !== null && $value !== []);
    }

    /**
     * @param  mixed  $selections
     * @return array<string, array<int, array<string, mixed>>>
     */
    private static function groupByKind(mixed $selections): array
    {
        $grouped = [];

        foreach ((array) $selections as $selection) {
            if (! is_array($selection)) {
                continue;
            }

            // A selection with no kind is an addon: that is the only kind the
            // storefront sends today, and the example in handoff 12 omits
            // nothing else.
            $kind = (string) ($selection['kind'] ?? 'addon');

            $grouped[$kind][] = $selection;
        }

        return $grouped;
    }

    /** @param array<int, array<string, mixed>> $selections */
    private static function firstId(array $selections): ?string
    {
        $id = $selections[0]['id'] ?? null;

        return is_scalar($id) && (string) $id !== '' ? (string) $id : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $selections
     * @return array<int, string>
     */
    private static function ids(array $selections): array
    {
        return array_values(array_filter(array_map(
            static fn (array $s) => isset($s['id']) && is_scalar($s['id']) ? (string) $s['id'] : null,
            $selections,
        )));
    }

    /**
     * Flavours are sent once with a `qty` (two scoops of pistachio), but the
     * pricer counts balls — one array entry per ball — because that is how a
     * size's capacity is checked.
     *
     * @param  array<int, array<string, mixed>>  $selections
     * @return array<int, string>
     */
    private static function expand(array $selections): array
    {
        $balls = [];

        foreach ($selections as $selection) {
            $id = isset($selection['id']) && is_scalar($selection['id']) ? (string) $selection['id'] : null;

            if ($id === null) {
                continue;
            }

            // Capped well below any real size so a hostile qty cannot make the
            // pricer build a million-element array before rejecting the line.
            $qty = max(1, min(50, (int) ($selection['qty'] ?? $selection['quantity'] ?? 1)));

            $balls = array_merge($balls, array_fill(0, $qty, $id));
        }

        return $balls;
    }

    /**
     * @param  array<int, array<string, mixed>>  $selections
     * @return array<int, array<string, mixed>>
     */
    private static function addons(array $selections): array
    {
        $addons = [];

        foreach ($selections as $selection) {
            $id = isset($selection['id']) && is_scalar($selection['id']) ? (string) $selection['id'] : null;

            if ($id === null) {
                continue;
            }

            $addons[] = [
                'id'       => $id,
                'quantity' => max(1, (int) ($selection['qty'] ?? $selection['quantity'] ?? 1)),
            ];
        }

        return $addons;
    }

    /**
     * @param  array<int, array<string, mixed>>  $selections
     * @return array<int, array<string, mixed>>
     */
    private static function units(array $selections, int $quantity): array
    {
        $addons = self::addons($selections);

        if ($addons === []) {
            return [];
        }

        return array_fill(0, $quantity, ['addons' => $addons]);
    }

    private static function label(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
