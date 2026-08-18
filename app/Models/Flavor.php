<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    /**
     * Inverse of Product::flavors(). Filament's AttachAction guesses this name
     * from the parent model to exclude already-attached rows, so the flavors
     * relation manager 500s without it.
     */
    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_flavor');
    }
}
