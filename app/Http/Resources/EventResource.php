<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    /** @var bool Include full gallery images (detail page) */
    public bool $withImages;

    public function __construct(mixed $resource, bool $withImages = true)
    {
        parent::__construct($resource);
        $this->withImages = $withImages;
    }

    public function toArray(Request $request): array
    {
        $data = [
            'id'          => $this->id,
            'title'       => $this->title,
            'date'        => $this->date,
            'description' => $this->description,
            'listImage'   => $this->list_image,
            'images'      => $this->images->pluck('image_url')->values(),
        ];

        return $data;
    }
}
