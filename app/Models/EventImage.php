<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventImage extends Model
{
    protected $fillable = ['event_id', 'image_url', 'sort_order'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
