<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A request to add credit, waiting on a human.
 *
 * Submitting one adds nothing to the balance. Only an admin approving it from
 * the dashboard does — handoff 14 calls out that the frontend used to let a
 * customer approve their own top-up from the browser console.
 */
class TopUpRequest extends Model
{
    use HasUlids;

    // Laravel would derive "top_up_requests" from the class name; the table is
    // one word.
    protected $table = 'topup_requests';

    public const STATUS_PENDING  = 'قيد المراجعة';
    public const STATUS_APPROVED = 'مكتمل';
    public const STATUS_REJECTED = 'مرفوض';

    /** What the customer may say they paid with. */
    public const METHODS = ['bop', 'paypal', 'jawwal', 'jawwal-manual'];

    protected $fillable = [
        'customer_id', 'amount', 'method', 'status', 'receipt_image',
        'receipt_note', 'phone', 'reviewed_by', 'reviewed_at', 'review_note',
        'transaction_id',
    ];

    protected $casts = [
        'amount'      => 'float',
        'reviewed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class, 'transaction_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function pending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Approval is idempotent by construction: the credit is only ever created
     * for a row that still has no transaction_id.
     */
    public function alreadyCredited(): bool
    {
        return $this->transaction_id !== null;
    }
}
