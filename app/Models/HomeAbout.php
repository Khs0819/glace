<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomeAbout extends Model
{
    protected $fillable = [
        'title', 'paragraphs', 'image', 'cta_label', 'cta_href',
    ];

    protected $casts = [
        'paragraphs' => 'array',
    ];
}
