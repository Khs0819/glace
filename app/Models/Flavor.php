<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Flavor extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'name_ar', 'name_en', 'image',
        'family', 'available', 'is_premium_mix_flavor',
    ];

    protected $casts = [
        'available' => 'boolean',
        'is_premium_mix_flavor' => 'boolean',
    ];
}
