<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMix extends Model
{
    protected $fillable = [
        'product_id', 'slug', 'label', 'pick',
        'base_price', 'flavor_price', 'premium_flavor_price',
        'item_ids', 'available', 'sort_order',
    ];

    protected $casts = [
        'base_price'           => 'float',
        'flavor_price'         => 'float',
        'premium_flavor_price' => 'float',
        'item_ids'             => 'array',
        'available'            => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
