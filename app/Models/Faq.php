<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One question on the help page (handoff 15). The id is the slug the frontend
 * uses as the accordion anchor, so it is the primary key.
 */
class Faq extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'question', 'answer', 'link_href', 'link_label', 'sort_order', 'active',
    ];

    protected $casts = ['active' => 'boolean'];
}
