<?php

namespace App\Services\JawwalPay;

/**
 * One Service Bus reply.
 *
 * Every endpoint answers with the same envelope — errorCd / desc / ref /
 * statusCode plus a flat `extraData` list of {key, value} pairs. Several of
 * those values are themselves JSON *strings* (get_balance ships the account
 * list as `info`, search_trans ships the rows as `txs`), which is why reading
 * one goes through extraJson() rather than plain array access.
 */
class JawwalPayResponse
{
    /** @param array<string, mixed> $raw */
    public function __construct(public readonly array $raw) {}

    public function errorCode(): string
    {
        return (string) ($this->raw['errorCd'] ?? '');
    }

    /** The vendor's own description — English or Arabic depending on `lang`. */
    public function description(): string
    {
        return (string) ($this->raw['desc'] ?? '');
    }

    /** Provider-side reference for the message; the only handle for support. */
    public function reference(): ?string
    {
        $ref = $this->raw['ref'] ?? null;

        return $ref === null ? null : (string) $ref;
    }

    /** "1" success · "3" failure. Redundant with errorCd, kept for the record. */
    public function statusCode(): ?string
    {
        $status = $this->raw['statusCode'] ?? null;

        return $status === null ? null : (string) $status;
    }

    public function successful(): bool
    {
        return $this->errorCode() === ErrorCode::SUCCESS;
    }

    public function failed(): bool
    {
        return ! $this->successful();
    }

    /** A single extraData value by key, or null when the key is absent. */
    public function extra(string $key): ?string
    {
        foreach ($this->raw['extraData'] ?? [] as $pair) {
            if (($pair['key'] ?? null) === $key) {
                return (string) ($pair['value'] ?? '');
            }
        }

        return null;
    }

    /**
     * An extraData value that carries JSON inside the string. Returns null when
     * the key is missing or the payload does not decode — a malformed `info`
     * blob must not take the whole response down.
     *
     * @return array<mixed>|null
     */
    public function extraJson(string $key): ?array
    {
        $raw = $this->extra($key);

        if ($raw === null || $raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : null;
    }

    /** Arabic wording for this reply, falling back to the vendor's own text. */
    public function message(): string
    {
        return ErrorCode::known($this->errorCode())
            ? ErrorCode::message($this->errorCode())
            : ($this->description() ?: ErrorCode::message($this->errorCode()));
    }

    public function customerMessage(): string
    {
        return ErrorCode::customerMessage($this->errorCode());
    }
}
