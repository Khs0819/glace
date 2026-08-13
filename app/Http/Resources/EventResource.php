<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Matches TypeScript `IEvent` — required: id, title, date, description,
 * listImage, images. `images` is always an array of absolute URLs (never null
 * entries, `[]` when the gallery is empty).
 */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'date'        => $this->date,
            'description' => $this->description,
            'listImage'   => $this->cardImageUrl(),
            'images'      => $this->galleryUrls(),
        ];
    }
}
