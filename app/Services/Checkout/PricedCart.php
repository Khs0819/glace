<?php

namespace App\Services\Checkout;

/**
 * The whole cart, priced. This — not anything the client sent — is what an
 * order and a payment are built from.
 */
class PricedCart
{
    /** @param array<int, PricedLine> $lines */
    public function __construct(public readonly array $lines) {}

    public function subtotalAgorot(): int
    {
        return array_sum(array_map(fn (PricedLine $line) => $line->totalAgorot(), $this->lines));
    }

    /** No discounts or delivery fees exist yet, so the total is the subtotal. */
    public function totalAgorot(): int
    {
        return $this->subtotalAgorot();
    }

    public function subtotal(): float
    {
        return Money::toDecimal($this->subtotalAgorot());
    }

    public function total(): float
    {
        return Money::toDecimal($this->totalAgorot());
    }

    public function itemCount(): int
    {
        return array_sum(array_map(fn (PricedLine $line) => $line->quantity, $this->lines));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'items'    => array_map(fn (PricedLine $line) => $line->toArray(), $this->lines),
            'subtotal' => $this->subtotal(),
            'total'    => $this->total(),
            'currency' => 'ILS',
        ];
    }
}
