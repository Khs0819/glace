<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeWhyGlace extends Model
{
    protected $fillable = [
        'title', 'description', 'features', 'video_url', 'video_thumbnail',
    ];

    protected $casts = [
        'features' => 'array',
    ];
}
