<?php

namespace App\Services\Checkout;

/**
 * The inverse of CartItemNormalizer: renders a resolved selection back into the
 * `{kind, id, label, qty, unitPrice}` shape the storefront reads.
 *
 * Shared by the quote (which presents a PricedLine) and by an order (which
 * presents a stored OrderItem), so a line looks the same before and after it
 * was bought. Prices here are the server's own — this is the round trip that
 * lets the storefront show what it will actually be charged.
 */
class CartItemPresenter
{
    /**
     * Per-unit selections: the flavours and addons one unit of this line carries.
     *
     * @param  array<string, mixed>  $selection
     * @return array<int, array<string, mixed>>
     */
    public static function selections(array $selection): array
    {
        $out = [];

        foreach ($selection['flavorLabels'] ?? [] as $index => $label) {
            $out[] = [
                'kind'      => 'flavor',
                'id'        => (string) ($selection['flavorIds'][$index] ?? $label),
                'label'     => (string) $label,
                'qty'       => 1,
                // A flavour is priced through the size's grid, not on its own,
                // so it has no separate charge to show.
                'unitPrice' => 0,
            ];
        }

        foreach ($selection['items'] ?? [] as $item) {
            $out[] = [
                'kind'      => 'mixItem',
                'id'        => (string) ($item['id'] ?? ''),
                'label'     => (string) ($item['label'] ?? ''),
                'qty'       => 1,
                'unitPrice' => 0,
            ];
        }

        // Addons are stored per unit; the first unit is representative of what
        // one of these costs, which is what `unitPrice` means here.
        foreach ($selection['units'][0]['addons'] ?? [] as $addon) {
            $out[] = self::addon($addon);
        }

        return $out;
    }

    /**
     * Line-level extras — charged once for the line, not per unit.
     *
     * @param  array<string, mixed>  $selection
     * @return array<int, array<string, mixed>>
     */
    public static function flatSelections(array $selection): array
    {
        return array_map(self::addon(...), $selection['flatAddons'] ?? []);
    }

    /** What one unit's worth of addons adds, in shekels. */
    public static function addonTotal(array $selection): float
    {
        return array_sum(array_map(
            static fn (array $a) => (float) ($a['price'] ?? 0) * (int) ($a['quantity'] ?? 1),
            $selection['units'][0]['addons'] ?? [],
        ));
    }

    public static function flatAddonTotal(array $selection): float
    {
        return array_sum(array_map(
            static fn (array $a) => (float) ($a['price'] ?? 0) * (int) ($a['quantity'] ?? 1),
            $selection['flatAddons'] ?? [],
        ));
    }

    /** The size label the storefront shows as `type`. */
    public static function type(array $selection): ?string
    {
        return $selection['sizeLabel']
            ?? $selection['mixLabel']
            ?? $selection['label']
            ?? null;
    }

    public static function container(array $selection): ?string
    {
        return $selection['containerLabel'] ?? null;
    }

    /** @param array<string, mixed> $addon */
    private static function addon(array $addon): array
    {
        return [
            'kind'      => 'addon',
            'id'        => (string) ($addon['id'] ?? ''),
            'label'     => (string) ($addon['label'] ?? ''),
            'qty'       => (int) ($addon['quantity'] ?? 1),
            'unitPrice' => (float) ($addon['price'] ?? 0),
        ];
    }
}
