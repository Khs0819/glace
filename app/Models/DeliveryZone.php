<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A delivery area and what it costs to reach. Replaces the frontend's
 * hardcoded src/lib/deliveryZones.ts (handoff 10).
 */
class DeliveryZone extends Model
{
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = ['id', 'name', 'description', 'fee', 'sort_order', 'available'];

    protected $casts = [
        'fee'       => 'float',
        'available' => 'boolean',
    ];

    /** Saved addresses pointing here — what makes a zone unsafe to delete. */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'zone_id');
    }
}
