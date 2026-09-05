<?php

namespace App\Services\Storefront;

use App\Services\Checkout\PricedCart;
use App\Services\Checkout\PricedLine;
use Illuminate\Validation\ValidationException;

/**
 * Which products may not be delivered (handoff 12 §3).
 *
 * This logic lives on the frontend today, in src/lib/deliveryRestrictions.ts.
 * It has to exist here too — not instead of, but as well: the frontend copy
 * stops a customer picking an invalid combination, and this copy stops a
 * request that never came from that form. A rule enforced only in the browser
 * is not enforced.
 *
 * The lists are config rather than constants because they are the shop's
 * business rules, not the system's: a product that starts travelling badly
 * should be one config edit, not a deploy.
 */
class DeliveryRestrictions
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config = []) {}

    /**
     * @throws ValidationException when the cart cannot be delivered
     */
    public function assertDeliverable(PricedCart $cart, string $deliveryMethod): void
    {
        if ($deliveryMethod !== 'delivery') {
            return;
        }

        foreach ($cart->lines as $index => $line) {
            if ($reason = $this->reasonFor($line)) {
                // 422 with the line's own path, so the storefront can point at
                // the offending item rather than failing the whole cart blindly.
                throw ValidationException::withMessages([
                    "items.{$index}" => $reason,
                ]);
            }
        }
    }

    /** The Arabic reason this line cannot be delivered, or null if it can. */
    public function reasonFor(PricedLine $line): ?string
    {
        $product = $line->product;
        $name    = $product->name;

        if ($product->in_store_only) {
            return "«{$name}» متاح للاستلام من المحل فقط";
        }

        if (in_array($product->slug, $this->blockedProducts(), true)) {
            return "«{$name}» غير متاح للتوصيل";
        }

        $restrictedSizes = $this->restrictedSizes()[$product->slug] ?? null;

        if ($restrictedSizes === null) {
            return null;
        }

        // Matched on the size's slug, which is stable, with the label as a
        // fallback for a catalog whose slugs have not been tidied.
        $sizeId    = $line->selection['sizeId'] ?? null;
        $sizeLabel = $line->selection['sizeLabel'] ?? null;

        foreach ($restrictedSizes as $restricted) {
            if ($sizeId === $restricted || $sizeLabel === $restricted) {
                return "«{$name}» بحجم «{$sizeLabel}» متاح للاستلام فقط، وغير متاح للتوصيل";
            }
        }

        return null;
    }

    /** @return array<int, string> */
    private function blockedProducts(): array
    {
        return (array) ($this->config['blocked_products'] ?? []);
    }

    /** @return array<string, array<int, string>> */
    private function restrictedSizes(): array
    {
        return (array) ($this->config['restricted_sizes'] ?? []);
    }
}
