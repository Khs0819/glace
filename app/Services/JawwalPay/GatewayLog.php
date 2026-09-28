<?php

namespace App\Services\JawwalPay;

use Illuminate\Support\Facades\Log;
use Psr\Log\LoggerInterface;

/**
 * Where a Jawwal Pay line goes, and what is allowed into it.
 *
 * Both halves of a payment write here: the gateway calls themselves, and the
 * checkout gates that can refuse before any call is made. One channel for both
 * is the point — a customer who was texted a code and then could not pay leaves
 * one kind of line or the other, and which kind it is *is* the diagnosis.
 *
 * Never written: the signing secret, the session token, and the code the
 * customer typed — nor its hash, which stands in for it. `secureHash` **is**
 * written: it is derived, cannot be turned back into the secret, its msgId is
 * spent by the time the line exists, and it is the first thing Jawwal Pay's own
 * support asks to see.
 */
class GatewayLog
{
    /** @param array<string, mixed> $context */
    public static function write(string $level, string $message, array $context = []): void
    {
        self::channel()->log($level, $message, $context);
    }

    /**
     * Whole and readable, but bounded: a search_trans reply runs to pages, and
     * a line nobody can scroll past is no more use than no line at all.
     *
     * @param  array<mixed>  $value
     */
    public static function json(array $value, int $limit = 4000): string
    {
        $json = (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $size = mb_strlen($json);

        return $size <= $limit ? $json : mb_substr($json, 0, $limit) . "…[{$size} chars]";
    }

    /**
     * The request as it went out, with the customer's own credentials removed.
     *
     * @param  array<string, mixed>  $body
     * @return array<string, mixed>
     */
    public static function request(array $body): array
    {
        if (isset($body['receiver'])) {
            $body['receiver'] = MobileNumber::mask((string) $body['receiver']);
        }

        // Hashed, but still the customer's code: anyone holding it can pay.
        if (isset($body['otp'])) {
            $body['otp'] = '[otp]';
        }

        return $body;
    }

    /**
     * The reply as it came back. Only a session token is taken out — every
     * other field is something the shop may need to quote back to the provider.
     *
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    public static function response(array $raw): array
    {
        foreach ($raw['extraData'] ?? [] as $index => $pair) {
            if (is_array($pair) && str_contains(strtolower((string) ($pair['key'] ?? '')), 'token')) {
                $raw['extraData'][$index]['value'] = '[token]';
            }
        }

        return $raw;
    }

    /**
     * The default channel unless the shop asked for a file of its own, so the
     * lines stay where whoever is watching the server already looks.
     */
    private static function channel(): LoggerInterface
    {
        $channel = config('services.jawwalpay.log_channel');

        return blank($channel) ? Log::driver() : Log::channel((string) $channel);
    }
}
