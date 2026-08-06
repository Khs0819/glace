<?php

namespace App\Models;

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
}
