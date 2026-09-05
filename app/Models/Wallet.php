<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Store credit.
 *
 * The balance is never written from outside: WalletService holds the row lock,
 * appends the ledger entry and moves the balance in one transaction, so the
 * two can never drift apart.
 */
class Wallet extends Model
{
    protected $fillable = ['customer_id', 'balance'];

    protected $casts = ['balance' => 'float'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->latest('created_at')->latest('id');
    }

    public static function forCustomer(Customer $customer): self
    {
        return static::firstOrCreate(['customer_id' => $customer->getKey()], ['balance' => 0]);
    }
}
