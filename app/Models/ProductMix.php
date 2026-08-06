<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMix extends Model
{
    protected $fillable = [
        'product_id', 'slug', 'label', 'pick',
        'base_price', 'flavor_price', 'premium_flavor_price',
        'flavor_option_ids', 'sort_order',
    ];

    protected $casts = [
        'base_price' => 'float',
        'flavor_price' => 'float',
        'premium_flavor_price' => 'float',
        'flavor_option_ids' => 'array',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
