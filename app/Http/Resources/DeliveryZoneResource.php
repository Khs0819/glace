<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Exactly the shape of src/lib/deliveryZones.ts, so the file can be deleted and
 * this endpoint dropped in its place (handoff 10).
 */
class DeliveryZoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id'          => $this->id,
            'name'        => $this->name,
            'description' => $this->description,
            // What the customer pays: 0 while the zone is on free delivery.
            'fee'          => $this->chargedFee(),
            'freeDelivery' => (bool) $this->free_delivery,
            // The usual fee, so the storefront can show it struck through.
            'regularFee'   => $this->free_delivery ? (float) $this->fee : null,
        ], static fn ($value) => $value !== null);
    }
}
