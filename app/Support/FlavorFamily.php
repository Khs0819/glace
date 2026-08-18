<?php

namespace App\Support;

use Illuminate\Support\Arr;

/**
 * Single source of truth for flavor families.
 *
 * `classic|special|stevia` are the families a Flavor row actually carries.
 * `mix` is pricing-only — it is what we charge when a customer combines
 * families in one order, so it shows up in price tables while no Flavor row
 * ever carries it. Keeping the two lists apart here is what stops a product
 * from offering a family that has no flavors behind it (and vice versa).
 */
class FlavorFamily
{
    /** Families a Flavor row can belong to. */
    public const FLAVOR = ['classic', 'special', 'stevia'];

    /** Pricing-only tier — never stored on a Flavor. */
    public const MIX = 'mix';

    private const LABELS = [
        'classic' => '🍦 كلاسيك',
        'special' => '⭐ سبيشال',
        'stevia'  => '🌿 ستيفيا',
        'mix'     => '🔀 مكس',
    ];

    private const COLORS = [
        'classic' => 'primary',
        'special' => 'warning',
        'stevia'  => 'success',
        'mix'     => 'info',
    ];

    /**
     * Options for anything that picks the family a Flavor belongs to.
     *
     * @return array<string, string>
     */
    public static function flavorOptions(): array
    {
        return Arr::only(self::LABELS, self::FLAVOR);
    }

    /**
     * Options for price tables and for a product's offered families —
     * the flavor families plus the `mix` tier.
     *
     * @return array<string, string>
     */
    public static function pricingOptions(): array
    {
        return self::LABELS;
    }

    /**
     * The subset of a product's declared families that flavors can be picked
     * from — i.e. everything except the pricing-only `mix` tier.
     *
     * @param  array<int, string>|null  $declared
     * @return array<int, string>
     */
    public static function pickableFrom(?array $declared): array
    {
        $pickable = array_values(array_intersect($declared ?? [], self::FLAVOR));

        // A product that only declares `mix` still needs a usable picker,
        // otherwise the admin is locked out of attaching anything at all.
        return $pickable ?: self::FLAVOR;
    }

    public static function label(?string $family): string
    {
        return self::LABELS[$family] ?? (string) $family;
    }

    public static function color(?string $family): string
    {
        return self::COLORS[$family] ?? 'gray';
    }
}
