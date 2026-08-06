<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id', 'label', 'map_src', 'address',
        'phone', 'whatsapp', 'weekday_hours', 'friday_hours',
        'border_radius', 'sort_order',
    ];
}
