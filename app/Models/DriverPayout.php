<?php

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One transfer to a driver, clearing the delivery fees they had earned.
 *
 * The amount is frozen from the settlements it cleared at the moment of paying,
 * and the receipt is kept alongside it: "we paid him" is a claim, the transfer
 * slip is the evidence.
 */
class DriverPayout extends Model
{
    protected $fillable = ['driver_id', 'amount', 'receipt', 'notes', 'paid_by', 'paid_at'];

    protected $casts = [
        'amount'  => 'float',
        'paid_at' => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(DriverSettlement::class, 'payout_id');
    }

    public function receiptUrl(): ?string
    {
        return MediaUrl::resolve($this->receipt);
    }
}
