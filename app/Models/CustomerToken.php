<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * One signed-in device.
 *
 * The plaintext token exists for exactly one response — the one that created
 * it — and only its SHA-256 is stored. Lookup is by that hash, so a stolen
 * database is not a stolen session.
 */
class CustomerToken extends Model
{
    protected $fillable = ['customer_id', 'token_hash', 'device', 'last_used_at', 'expires_at'];

    protected $casts = [
        'last_used_at' => 'datetime',
        'expires_at'   => 'datetime',
    ];

    protected $hidden = ['token_hash'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** 64 hex chars — long enough that guessing is not a strategy. */
    public static function newPlainToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Unsalted SHA-256 on purpose: the lookup has to find the row from the token
     * alone, which a per-row salt would make impossible. Safe here because the
     * input is 256 bits of CSPRNG output, not a guessable secret — there is no
     * dictionary to run against it.
     */
    public static function hash(string $plain): string
    {
        return hash('sha256', $plain);
    }

    public function expired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * Touching this on every authenticated request would be a write per request;
     * a day's resolution is all the dashboard needs from it.
     */
    public function touchUsage(): void
    {
        if ($this->last_used_at === null || $this->last_used_at->isBefore(now()->subDay())) {
            $this->forceFill(['last_used_at' => now()])->saveQuietly();
        }
    }

    public static function deviceLabel(?string $userAgent): ?string
    {
        return $userAgent === null ? null : Str::limit($userAgent, 190, '');
    }
}
