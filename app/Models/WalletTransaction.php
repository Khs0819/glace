<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of the customer's statement. Append-only: a correction is another
 * row, never an edit to this one.
 */
class WalletTransaction extends Model
{
    use HasUlids;

    public const TYPE_CREDIT = 'credit';
    public const TYPE_DEBIT  = 'debit';

    protected $fillable = [
        'wallet_id', 'amount', 'type', 'label', 'method',
        'receipt_image', 'order_id', 'balance_after',
    ];

    protected $casts = [
        'amount'        => 'float',
        'balance_after' => 'float',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }
}
