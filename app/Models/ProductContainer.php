<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductContainer extends Model
{
    protected $fillable = [
        'product_id', 'slug', 'label', 'available',
        'name', 'image', 'pricing_label', 'sort_order',
    ];

    protected $casts = [
        'available' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
