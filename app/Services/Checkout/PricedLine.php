<?php

namespace App\Services\Checkout;

use App\Models\Product;

/**
 * One priced cart line, ready to become an order_items row.
 *
 * Money is carried in agorot (integer ₪/100) all the way through pricing and
 * only rendered as a decimal at the edges — adding floats line by line is how
 * a total ends up a fraction of an agora away from what the customer was shown
 * and, worse, away from what the gateway is asked to charge.
 */
class PricedLine
{
    /**
     * @param  array<string, mixed>  $selection  what was picked, resolved and normalised
     */
    public function __construct(
        public readonly Product $product,
        public readonly array $selection,
        public readonly string $description,
        public readonly int $unitPriceAgorot,
        public readonly int $quantity,
        public readonly int $addonsTotalAgorot,
    ) {}

    public function totalAgorot(): int
    {
        return $this->unitPriceAgorot * $this->quantity + $this->addonsTotalAgorot;
    }

    /** @return array<string, mixed> attributes for an OrderItem */
    public function toOrderItemAttributes(): array
    {
        return [
            'product_id'   => $this->product->getKey(),
            'product_slug' => $this->product->slug,
            'product_name' => $this->product->name,
            'image'        => $this->product->image,
            'kind'         => $this->product->kind,
            'selection'    => $this->selection,
            'description'  => $this->description,
            'unit_price'   => Money::toDecimal($this->unitPriceAgorot),
            'quantity'     => $this->quantity,
            'addons_total' => Money::toDecimal($this->addonsTotalAgorot),
            'line_total'   => Money::toDecimal($this->totalAgorot()),
        ];
    }

    /** @return array<string, mixed> the shape the storefront gets back */
    public function toArray(): array
    {
        return [
            'productId'   => (string) $this->product->getKey(),
            'productSlug' => $this->product->slug,
            'name'        => $this->product->name,
            'image'       => \App\Support\MediaUrl::resolve($this->product->image),
            'description' => $this->description,
            'selection'   => $this->selection,
            'unitPrice'   => Money::toDecimal($this->unitPriceAgorot),
            'quantity'    => $this->quantity,
            'addonsTotal' => Money::toDecimal($this->addonsTotalAgorot),
            'lineTotal'   => Money::toDecimal($this->totalAgorot()),
        ];
    }
}
