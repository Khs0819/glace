<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IceCreamAddonPrice extends Model
{
    protected $fillable = ['product_id', 'flavor_family', 'price'];

    protected $casts = [
        'price' => 'float',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
