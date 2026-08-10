<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MenuCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'label'        => $this->label,
            'icon'         => $this->icon,
            'available'    => $this->available,
            'accentColor'  => $this->accent_color,
            'gradientFrom' => $this->gradient_from,
            'gradientTo'   => $this->gradient_to,
            'sortOrder'    => $this->sort_order,
        ];
    }
}
