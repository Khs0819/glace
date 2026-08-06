<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeroSlide extends Model
{
    protected $fillable = [
        'man_img', 'piece_img', 'zigzags_img',
        'title_h1', 'title_h2',
        'bg_color', 'header_bg_color', 'h1_bg_color', 'h2_bg_color',
        'sort_order',
    ];
}
