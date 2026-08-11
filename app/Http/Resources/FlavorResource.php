<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FlavorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = [
            'id'        => $this->id,
            'nameAr'    => $this->name_ar,
            'nameEn'    => $this->name_en,
            'image'     => MediaUrl::resolve($this->image),
            'family'    => $this->family,
            'available' => (bool) $this->available,
        ];

        if ($this->is_premium_mix_flavor) {
            $data['isPremiumMixFlavor'] = true;
        }

        return $data;
    }
}
