<?php

namespace App\Http\Resources;

use App\Support\MediaUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EventResource extends JsonResource
{
    public bool $withImages;

    public function __construct(mixed $resource, bool $withImages = true)
    {
        parent::__construct($resource);
        $this->withImages = $withImages;
    }

    public function toArray(Request $request): array
    {
        $images = $this->images
            ->pluck('image_url')
            ->map(fn ($url) => MediaUrl::resolve($url))
            ->filter()
            ->values()
            ->all();

        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'date'        => $this->date,
            'description' => $this->description,
            'listImage'   => MediaUrl::resolve($this->list_image),
            'images'      => $images,
        ];
    }
}
