<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasUuids;

    protected $fillable = [
        'slug', 'category_id', 'kind', 'name', 'description', 'image',
        'sort_order', 'available', 'has_addons', 'has_notes', 'has_favorites',
        'has_image_zoom', 'in_store_only', 'selection_mode', 'pricing_label',
        'has_extra_biscuit_addon', 'includes_ice_cream_step', 'flavor_families',
    ];

    protected $casts = [
        'available' => 'boolean',
        'has_addons' => 'boolean',
        'has_notes' => 'boolean',
        'has_favorites' => 'boolean',
        'has_image_zoom' => 'boolean',
        'in_store_only' => 'boolean',
        'has_extra_biscuit_addon' => 'boolean',
        'includes_ice_cream_step' => 'boolean',
        'flavor_families' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    public function containers(): HasMany
    {
        return $this->hasMany(ProductContainer::class)->orderBy('sort_order');
    }

    public function sizes(): HasMany
    {
        return $this->hasMany(ProductSize::class)->orderBy('sort_order');
    }

    public function iceCreamAddonPrices(): HasMany
    {
        return $this->hasMany(IceCreamAddonPrice::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ProductItem::class)->orderBy('sort_order');
    }

    public function mixes(): HasMany
    {
        return $this->hasMany(ProductMix::class)->orderBy('sort_order');
    }

    public function addons(): HasMany
    {
        return $this->hasMany(Addon::class)->orderBy('sort_order');
    }

    public function flavors(): BelongsToMany
    {
        return $this->belongsToMany(Flavor::class, 'product_flavor');
    }
}
