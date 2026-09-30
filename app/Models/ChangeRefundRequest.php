<?php

namespace App\Models;

use App\Support\MediaUrl;
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
    /** The change from a cash payment, sent back. */
    public const KIND_CHANGE = 'change';

    /** The whole of a paid order the customer cancelled. */
    public const KIND_ORDER = 'order';

    public const KINDS = [
        self::KIND_CHANGE => 'باقي نقدي',
        self::KIND_ORDER  => 'استرداد طلب',
    ];

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
        'order_id', 'order_reference', 'kind', 'amount',
        'holder_name', 'holder_phone', 'refund_method',
        'notes', 'review_note', 'transfer_receipt', 'status',
        'created_by', 'reviewed_by', 'reviewed_at',
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

    /** The slip showing the change was sent back, once it has been. */
    public function transferReceiptUrl(): ?string
    {
        return MediaUrl::resolve($this->transfer_receipt);
    }

    public function methodLabel(): string
    {
        return self::REFUND_METHODS[$this->refund_method] ?? $this->refund_method;
    }

    public function kindLabel(): string
    {
        return self::KINDS[$this->kind] ?? self::KINDS[self::KIND_CHANGE];
    }

    public function isOrderRefund(): bool
    {
        return $this->kind === self::KIND_ORDER;
    }

    /** Why it was refused — or, on a completed one, a note beside the slip. */
    public function reject(string $reason, ?int $userId): void
    {
        $this->update([
            'status'      => self::STATUS_REJECTED,
            'review_note' => $reason,
            'reviewed_by' => $userId,
            'reviewed_at' => now(),
        ]);
    }

    /**
     * The transfer has been sent.
     *
     * One method for every screen that closes a request, because closing a
     * whole-order refund does one more thing than closing a change refund: it
     * is the moment the order becomes "مسترد", with the amount and the date
     * the reports read.
     */
    public function complete(?string $receipt, ?int $userId, ?string $note = null): void
    {
        \Illuminate\Support\Facades\DB::transaction(function () use ($receipt, $userId, $note) {
            $this->update([
                'status'           => self::STATUS_COMPLETED,
                'transfer_receipt' => $receipt ?? $this->transfer_receipt,
                'review_note'      => $note ?: $this->review_note,
                'reviewed_by'      => $userId,
                'reviewed_at'      => now(),
            ]);

            if ($this->isOrderRefund() && ($order = $this->order) && ! $order->isRefunded()) {
                $order->update([
                    'status'          => Order::FULFILMENT_REFUNDED,
                    'refunded_amount' => $this->amount,
                    'refunded_at'     => now(),
                    'refund_method'   => Order::REFUND_TRANSFER,
                ]);
            }
        });
    }
}
