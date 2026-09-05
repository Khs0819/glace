<?php

namespace App\Models;

use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\Access\Authorizable;

/**
 * A storefront account.
 *
 * Authenticates by SMS code only — there is no password column and no password
 * flow anywhere in the system (handoff 08). It is Authenticatable so the guard
 * and `$request->user()` work normally, but the credential half of that
 * contract is deliberately inert: nothing here can be signed in to with a
 * secret the customer knows.
 */
class Customer extends Model implements AuthenticatableContract
{
    use Authorizable, HasFactory;

    protected $fillable = ['name', 'email', 'phone', 'blocked', 'last_login_at'];

    protected $casts = [
        'blocked'       => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function tokens(): HasMany
    {
        return $this->hasMany(CustomerToken::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class)->orderByDesc('is_default')->orderBy('created_at');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** Created on first use rather than at signup, so a customer who never tops up has no row. */
    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function topUpRequests(): HasMany
    {
        return $this->hasMany(TopUpRequest::class)->latest();
    }

    public function defaultAddress(): ?Address
    {
        return $this->addresses()->where('is_default', true)->first()
            ?? $this->addresses()->first();
    }

    // ─── Authenticatable ────────────────────────────────────────────────────
    //
    // Tokens are checked by the guard against customer_tokens, so none of the
    // password/remember-me machinery is reachable. These satisfy the interface
    // and nothing more.

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
        //
    }

    public function getRememberTokenName(): ?string
    {
        return null;
    }
}
