<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A delivery driver the shop can hand an order to.
 *
 * @property-read bool $busy
 */
class Driver extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'company', 'phone', 'active', 'notes'];

    protected $casts = ['active' => 'boolean'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Deliveries of theirs that are still on the road. */
    public function activeOrders(): HasMany
    {
        return $this->orders()->where('status', Order::FULFILMENT_ON_WAY);
    }

    /**
     * Whether this driver is out on a delivery right now.
     *
     * Derived, never stored: a flag would need setting, clearing and repairing,
     * and would be wrong the first time a tab was closed halfway through an
     * assignment. The orders table already knows.
     */
    public function busy(): bool
    {
        return $this->relationLoaded('activeOrders')
            ? $this->activeOrders->isNotEmpty()
            : $this->activeOrders()->exists();
    }

    public function statusLabel(): string
    {
        if (! $this->active) {
            return 'غير متاح';
        }

        return $this->busy() ? 'في توصيل' : 'متاح';
    }

    /** How the cashier reads one line of the driver list. */
    public function pickerLabel(): string
    {
        return trim(implode(' — ', array_filter([
            $this->name,
            $this->company,
            $this->phone,
            $this->statusLabel(),
        ])));
    }

    /**
     * Frozen onto the order, so a driver later renamed or deleted does not
     * rewrite a delivery that already happened.
     *
     * @return array<string, string|null>
     */
    public function snapshot(): array
    {
        return [
            'name'    => $this->name,
            'phone'   => $this->phone,
            'company' => $this->company,
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('active', true);
    }
}
