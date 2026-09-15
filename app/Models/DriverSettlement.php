<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One delivery a driver completed, with its financial footprint.
 *
 * Created automatically when a delivery order reaches "تم الاستلام". Answers
 * the end-of-day question: "driver X carried Y orders totalling Z shekels,
 * and collected A in cash that should now be in the till."
 */
class DriverSettlement extends Model
{
    protected $fillable = [
        'driver_id', 'order_id', 'shift_id', 'payout_id',
        'order_reference', 'order_total', 'delivery_fee', 'payment_method',
        'cash_collected', 'delivered_at',
    ];

    protected $casts = [
        'order_total'    => 'float',
        'delivery_fee'   => 'float',
        'cash_collected' => 'boolean',
        'delivered_at'   => 'datetime',
    ];

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'shift_id');
    }

    /** The transfer that cleared this fee, once the driver has been paid. */
    public function payout(): BelongsTo
    {
        return $this->belongsTo(DriverPayout::class, 'payout_id');
    }

    public function paidOut(): bool
    {
        return $this->payout_id !== null;
    }
}
