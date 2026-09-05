<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id', 'product_id', 'product_slug', 'product_name', 'image', 'kind',
        'selection', 'description', 'unit_price', 'quantity', 'addons_total', 'line_total',
    ];

    protected $casts = [
        'selection'    => 'array',
        'unit_price'   => 'float',
        'quantity'     => 'integer',
        'addons_total' => 'float',
        'line_total'   => 'float',
    ];

    /** Absolute URL, per the media contract — never the stored relative path. */
    public function imageUrl(): ?string
    {
        return \App\Support\MediaUrl::resolve($this->image);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Soft pointer: the product may well have been deleted since. */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
