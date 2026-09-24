<?php

namespace App\Services\JawwalPay;

/**
 * The `secureHash` every Service Bus request except /login must carry.
 *
 * Per the merchant guide (§3): take the request parameters minus secureHash,
 * order them, concatenate the *values* (not the keys) into one string, and HMAC
 * it with the merchant secret; the result is lowercase hex.
 *
 * Two things in that section do not line up, so both are configurable:
 *
 *  - Step 1 says "sort the data dictionary by key", but the worked example
 *    concatenates 00970598251590 · 44393232930329 · 500 · EN — that is the
 *    values in lexicographic order, not the values ordered by their keys
 *    (which would be 500 · EN · 44393232930329 · 00970598251590).
 *  - The hash that example prints does not reproduce under HMAC-SHA512 with the
 *    secret it names, nor under any permutation, digest or secret placement we
 *    tried. So the shape below follows the prose, and `services.jawwalpay.hash_*`
 *    exists so the sandbox can settle it without a code change.
 *
 * @see \Tests\Unit\JawwalPay\SecureHashTest
 */
class SecureHash
{
    /** How the secret is combined with the canonical string. */
    public const MODES = ['hmac', 'append', 'prepend'];

    /**
     * @param  array<int, string>  $exclude  keys left out of the signed string
     */
    public function __construct(
        private readonly string $secret,
        private readonly string $algo = 'sha512',
        private readonly string $sort = 'value',
        private readonly string $mode = 'hmac',
        private readonly string $case = 'lower',
        private readonly array $exclude = [],
    ) {}

    /**
     * @param  array<string, scalar|null>  $payload  request body without secureHash
     */
    public function for(array $payload): string
    {
        $canonical = $this->canonicalize($payload);

        // "HMAC secret" in the guide, but gateways in this family are also
        // built on a plain digest of the string with the secret glued to one
        // end. Which one this deployment needs is settled by jawwalpay:probe
        // against the live gateway, then pinned in .env.
        $digest = match ($this->mode) {
            'append'  => hash($this->algo, $canonical . $this->secret),
            'prepend' => hash($this->algo, $this->secret . $canonical),
            default   => hash_hmac($this->algo, $canonical, $this->secret),
        };

        return $this->case === 'upper' ? strtoupper($digest) : $digest;
    }

    /**
     * The exact string that gets hashed. Public because an "invalid secure hash"
     * rejection is otherwise undebuggable from the outside.
     *
     * @param  array<string, scalar|null>  $payload
     */
    public function canonicalize(array $payload): string
    {
        $values = [];

        foreach ($payload as $key => $value) {
            // secureHash is never part of its own input, and omitted optional
            // params must not contribute an empty slot to the string.
            if ($key === 'secureHash' || $value === null || $value === '') {
                continue;
            }

            // Some gateways of this family sign the business fields only and
            // leave the envelope out — `lang` is the usual one. Which is true
            // here is settled by jawwalpay:probe, not by reading.
            if (in_array($key, $this->exclude, true)) {
                continue;
            }

            $values[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        // SORT_STRING, not PHP's default: the values are numeric strings, and a
        // default sort() compares them as numbers — which puts "500" before
        // "00970598251590" and produces a string the gateway will not accept.
        $this->sort === 'key'
            ? ksort($values, SORT_STRING)
            : sort($values, SORT_STRING);

        return implode('', $values);
    }
}
