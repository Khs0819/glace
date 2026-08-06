<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SizePrice extends Model
{
    protected $fillable = ['size_id', 'flavor_family', 'price'];

    protected $casts = [
        'price' => 'float',
    ];

    public function size(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class, 'size_id');
    }
}
