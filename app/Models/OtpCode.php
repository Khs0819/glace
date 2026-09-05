<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

/**
 * A one-time code that was sent to a phone.
 *
 * Hashed like a password, short-lived, single-use, and capped on wrong guesses
 * (handoff 08). Keyed by phone rather than by customer because the first code
 * for a number necessarily goes out before an account exists.
 */
class OtpCode extends Model
{
    public const PURPOSE_LOGIN  = 'login';
    public const PURPOSE_JAWWAL = 'jawwal_payment';

    /** Wrong guesses allowed before the code is burned. */
    public const MAX_ATTEMPTS = 5;

    protected $fillable = ['phone', 'purpose', 'code_hash', 'attempts', 'expires_at', 'consumed_at', 'payload'];

    protected $casts = [
        'expires_at'  => 'datetime',
        'consumed_at' => 'datetime',
        'payload'     => 'array',
    ];

    protected $hidden = ['code_hash'];

    public function usable(): bool
    {
        return $this->consumed_at === null
            && $this->attempts < self::MAX_ATTEMPTS
            && $this->expires_at->isFuture();
    }

    public function matches(string $code): bool
    {
        return Hash::check($code, $this->code_hash);
    }

    public function consume(): void
    {
        $this->forceFill(['consumed_at' => now()])->save();
    }

    public function registerFailedAttempt(): void
    {
        $this->increment('attempts');
    }

    /**
     * Six digits, uniformly random. Padded rather than ranged from 100000 so
     * "042913" is as likely as any other code.
     */
    public static function newCode(): string
    {
        return str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }
}
