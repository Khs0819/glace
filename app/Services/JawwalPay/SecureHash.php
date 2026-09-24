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

    /** Values alone, or key=value pairs — both are common in this family. */
    public const LAYOUTS = ['values', 'pairs'];

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
        private readonly string $layout = 'values',
        private readonly string $separator = '',
        private readonly string $encoding = 'hex',
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
        $raw = match ($this->mode) {
            'append'  => hash($this->algo, $canonical . $this->secret, true),
            'prepend' => hash($this->algo, $this->secret . $canonical, true),
            default   => hash_hmac($this->algo, $canonical, $this->secret, true),
        };

        // Hex is what the guide prints; base64 is what several gateways on the
        // same platform expect, and the two are the same digest either way.
        $digest = $this->encoding === 'base64' ? base64_encode($raw) : bin2hex($raw);

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
        if ($this->sort === 'key') {
            ksort($values, SORT_STRING);
        } else {
            // Sorting by value loses the keys, so it is done on a copy when the
            // layout needs them back.
            $sorted = $values;
            sort($sorted, SORT_STRING);
            $values = $this->layout === 'pairs'
                ? $this->reorderByValue($values, $sorted)
                : $sorted;
        }

        $parts = $this->layout === 'pairs'
            ? array_map(static fn ($key, $value) => "{$key}={$value}", array_keys($values), $values)
            : array_values($values);

        return implode($this->separator, $parts);
    }

    /**
     * Put the key => value map back in the order its values sorted into.
     *
     * @param  array<string, string>  $values
     * @param  array<int, string>  $sorted
     * @return array<string, string>
     */
    private function reorderByValue(array $values, array $sorted): array
    {
        $ordered = [];

        foreach ($sorted as $value) {
            $key = array_search($value, $values, true);

            if ($key !== false) {
                $ordered[$key] = $value;
                unset($values[$key]);
            }
        }

        return $ordered;
    }
}
