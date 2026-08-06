<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AddonResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->slug,
            'label'     => $this->label,
            'price'     => $this->price,
            'available' => $this->available,
            'type'      => $this->type,
            $this->mergeWhen($this->max_qty !== null, [
                'maxQty' => $this->max_qty,
            ]),
        ];
    }
}
