<?php

namespace App\Services\Auth;

use App\Models\Customer;
use App\Models\CustomerToken;
use App\Support\PhoneNumber;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Signing in, and knowing who is signed in (handoff 08 · 09).
 *
 * Tokens live in `customer_tokens` as SHA-256 hashes and are presented as a
 * bare `Authorization: <token>` header — no `Bearer` prefix, because that is
 * what the storefront's axios interceptor sends and what swagger.yaml records.
 * That single detail is why this is not Sanctum: its guard would look for the
 * prefix, find none, and reject every request.
 */
class CustomerAuthService
{
    /** How long a session lasts without being used. Null would mean forever. */
    public const TOKEN_TTL_DAYS = 180;

    /**
     * Find the account for a verified number, creating it on first sight.
     *
     * The code has already been checked by the time this runs — this method
     * grants access and must never be reachable from an unverified number.
     */
    public function resolveCustomer(string $phone, ?string $fullName): Customer
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            throw ValidationException::withMessages(['phone' => 'رقم الهاتف غير صحيح']);
        }

        $customer = Customer::where('phone', $normalized)->first();

        if ($customer) {
            $this->assertNotBlocked($customer);

            // handoff 09: an existing account keeps its stored name. A returning
            // customer must not be able to rename themselves — or anyone else —
            // just by typing a name on the login screen.
            $customer->forceFill(['last_login_at' => now()])->save();

            return $customer;
        }

        $name = trim((string) $fullName);

        if ($name === '') {
            throw ValidationException::withMessages([
                'fullName' => 'الاسم مطلوب لإنشاء حساب جديد',
            ]);
        }

        return Customer::create([
            'name'          => $name,
            'phone'         => $normalized,
            'last_login_at' => now(),
        ]);
    }

    /**
     * Mint a session token. The plaintext is returned here and nowhere else —
     * only its hash is kept, so it cannot be recovered from the database later.
     */
    public function issueToken(Customer $customer, ?Request $request = null): string
    {
        $plain = CustomerToken::newPlainToken();

        $customer->tokens()->create([
            'token_hash' => CustomerToken::hash($plain),
            'device'     => CustomerToken::deviceLabel($request?->userAgent()),
            'expires_at' => now()->addDays(self::TOKEN_TTL_DAYS),
        ]);

        $this->pruneExpired($customer);

        return $plain;
    }

    /** The account behind a raw token, or null if it is unknown or stale. */
    public function resolveToken(?string $plain): ?Customer
    {
        if ($plain === null || $plain === '') {
            return null;
        }

        $token = CustomerToken::with('customer')
            ->where('token_hash', CustomerToken::hash($plain))
            ->first();

        if (! $token || $token->expired() || ! $token->customer) {
            return null;
        }

        if ($token->customer->blocked) {
            return null;
        }

        $token->touchUsage();

        return $token->customer;
    }

    /**
     * Read the token out of the request.
     *
     * `Authorization: <token>` is the contract, but a `Bearer ` prefix is
     * accepted too — an HTTP client that adds one on the storefront's behalf
     * should not silently log everybody out.
     */
    public function tokenFromRequest(Request $request): ?string
    {
        $header = trim((string) $request->header('Authorization', ''));

        if ($header === '') {
            return null;
        }

        if (stripos($header, 'bearer ') === 0) {
            return trim(substr($header, 7));
        }

        return $header;
    }

    /** Sign one device out. Logout is client-side (handoff 09), so this is for the dashboard. */
    public function revoke(string $plain): void
    {
        CustomerToken::where('token_hash', CustomerToken::hash($plain))->delete();
    }

    public function revokeAll(Customer $customer): void
    {
        $customer->tokens()->delete();
    }

    private function pruneExpired(Customer $customer): void
    {
        $customer->tokens()->whereNotNull('expires_at')->where('expires_at', '<', now())->delete();
    }

    private function assertNotBlocked(Customer $customer): void
    {
        if ($customer->blocked) {
            throw ValidationException::withMessages([
                'phone' => 'هذا الحساب موقوف — يرجى التواصل معنا',
            ])->status(403);
        }
    }
}
