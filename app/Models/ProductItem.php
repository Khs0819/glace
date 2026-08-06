<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductItem extends Model
{
    protected $fillable = [
        'product_id', 'label', 'price', 'description',
        'image', 'available', 'is_premium_mix_flavor', 'sort_order',
    ];

    protected $casts = [
        'price' => 'float',
        'available' => 'boolean',
        'is_premium_mix_flavor' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
