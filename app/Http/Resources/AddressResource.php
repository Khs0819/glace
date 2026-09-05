<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `SavedAddress` shape (handoff 10) — a drop-in replacement for what the
 * storefront used to keep in localStorage, field for field.
 */
class AddressResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'type'      => $this->type,
            'label'     => $this->label,
            'name'      => $this->name,
            'phone'     => $this->phone,
            'city'      => $this->city,
            'zoneId'    => $this->zone_id,
            'street'    => $this->street,
            'landmark'  => $this->landmark,
            'location'  => $this->lat === null || $this->lng === null
                ? null
                : ['lat' => $this->lat, 'lng' => $this->lng],
            'isDefault' => (bool) $this->is_default,
        ];
    }
}
