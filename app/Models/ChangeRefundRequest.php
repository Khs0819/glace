<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to refund change from a cash payment.
 *
 * Created by the cashier when a customer pays more than the order total and
 * the shop cannot (or chooses not to) give the difference back in notes. The
 * request sits in "pending" until it is reviewed — typically at shift close —
 * then marked "completed" once the transfer is made, or "rejected" with a note.
 */
class ChangeRefundRequest extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED  = 'rejected';

    public const REFUND_METHODS = [
        'jawwal'  => 'جوال بي',
        'bop'     => 'بنك فلسطين',
        'palpay'  => 'بال بي',
        'cash'    => 'نقداً',
    ];

    protected $fillable = [
        'order_id', 'order_reference', 'amount',
        'holder_name', 'holder_phone', 'refund_method',
        'notes', 'status', 'created_by', 'reviewed_by', 'reviewed_at',
    ];

    protected $casts = [
        'amount'      => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function methodLabel(): string
    {
        return self::REFUND_METHODS[$this->refund_method] ?? $this->refund_method;
    }
}
