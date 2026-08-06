<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Addon extends Model
{
    protected $fillable = [
        'product_id', 'slug', 'label', 'price',
        'available', 'type', 'max_qty', 'sort_order',
    ];

    protected $casts = [
        'price' => 'float',
        'available' => 'boolean',
        'max_qty' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
