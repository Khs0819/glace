<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlavorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return array_filter([
            'id'                 => $this->id,
            'nameAr'             => $this->name_ar,
            'nameEn'             => $this->name_en,
            'image'              => $this->image,
            'family'             => $this->family,
            'available'          => $this->available,
            'isPremiumMixFlavor' => $this->is_premium_mix_flavor ?: null,
        ], fn ($v) => $v !== null && $v !== false);
    }
}
