<?php

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'title', 'date', 'description', 'list_image',
    ];

    public function images(): HasMany
    {
        return $this->hasMany(EventImage::class)->orderBy('sort_order');
    }

    /**
     * Absolute card thumbnail used by both `IEvent.listImage` and
     * `IHomeEvent.image` — the two must agree (handoff 04 §3).
     *
     * Falls back to the first gallery image so an event whose gallery is filled
     * but whose card image is not still renders a real thumbnail instead of the
     * broken placeholder the contract forbids.
     */
    public function cardImageUrl(): ?string
    {
        return MediaUrl::resolve($this->list_image)
            ?? MediaUrl::resolve($this->images->first()?->image_url);
    }

    /** Gallery URLs for `IEvent.images` — absolute strings only, never null entries. */
    public function galleryUrls(): array
    {
        return $this->images
            ->map(fn (EventImage $image) => MediaUrl::resolve($image->image_url))
            ->filter()
            ->values()
            ->all();
    }
}
