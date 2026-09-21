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

    protected $fillable = ['id', 'name', 'description', 'fee', 'free_delivery', 'sort_order', 'available'];

    protected $casts = [
        'fee'           => 'float',
        'free_delivery' => 'boolean',
        'available'     => 'boolean',
    ];

    /**
     * What an order to this zone is actually charged.
     *
     * Free delivery is a switch, not a fee of zero: the normal fee stays on the
     * zone so switching the offer off puts it straight back.
     */
    public function chargedFee(): float
    {
        return $this->free_delivery ? 0.0 : (float) $this->fee;
    }

    /** Saved addresses pointing here — what makes a zone unsafe to delete. */
    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class, 'zone_id');
    }
}
