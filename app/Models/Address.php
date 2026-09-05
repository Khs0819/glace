<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * A saved delivery address.
 *
 * Exactly one per customer carries is_default; that invariant is enforced in
 * AddressService, which is the only thing allowed to move the flag.
 */
class Address extends Model
{
    public const TYPES = ['home' => 'المنزل', 'work' => 'العمل', 'other' => 'أخرى'];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'customer_id', 'type', 'label', 'name', 'phone', 'city',
        'zone_id', 'street', 'landmark', 'lat', 'lng', 'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
        'lat'        => 'float',
        'lng'        => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (Address $address) {
            $address->id ??= 'addr_' . strtolower((string) Str::ulid());
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(DeliveryZone::class, 'zone_id');
    }

    /** The default label for a type; `other` has none, so the customer must supply one. */
    public static function defaultLabel(string $type): ?string
    {
        return $type === 'other' ? null : (self::TYPES[$type] ?? null);
    }

    /**
     * What gets frozen onto an order. The zone is resolved to its *name* here
     * and now: the order has to keep reading correctly after the zone is
     * renamed or the address deleted (handoff 12).
     */
    public function toOrderSnapshot(): array
    {
        return [
            'name'     => $this->name,
            'phone'    => $this->phone,
            'city'     => $this->city,
            'area'     => $this->zone?->name,
            'zoneId'   => $this->zone_id,
            'street'   => $this->street,
            'landmark' => $this->landmark,
            'note'     => null,
            'location' => $this->lat === null || $this->lng === null
                ? null
                : ['lat' => $this->lat, 'lng' => $this->lng],
        ];
    }
}
