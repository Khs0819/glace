<?php

namespace App\Services\JawwalPay;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Client for the Jawwal Pay Service Bus (merchant guide v1.0).
 *
 * Shape of every call: log in once with Basic auth to get a bearer-ish token,
 * then POST JSON to the endpoint with that token in `X-Auth-Token` and a
 * `secureHash` over the body. Replies always use the same envelope, so they
 * come back as a JawwalPayResponse.
 *
 * Failure is split in two on purpose:
 *  - we never reached them, or they answered with something unusable
 *      → JawwalPayException (nothing to record against the payment)
 *  - they answered and said no (errorCd 89, 12, 46 …)
 *      → a failed JawwalPayResponse, which the caller persists
 */
class JawwalPayClient
{
    public const ENDPOINT_LOGIN        = '/login';
    public const ENDPOINT_BALANCE      = '/v1/get_balance';
    public const ENDPOINT_SEND_OTP     = '/v1/business/send_otp';
    public const ENDPOINT_MFP          = '/v1/business/MFP';
    public const ENDPOINT_GENERATE_QR  = '/v1/business/generate_qr';
    public const ENDPOINT_SEARCH_TRANS = '/v1/business/search_trans';

    /** initiationMethod values for generate_qr. */
    public const QR_STATIC  = '11';
    public const QR_DYNAMIC = '12';

    /** Optional merchant-side references the guide allows on MFP. */
    private const MFP_REFS = [
        'additionalBillNo', 'additionalReferenceLabel', 'additionalMobileNumber',
        'note', 'promoCode',
    ];

    /** …and the wider set generate_qr allows. */
    private const QR_REFS = [
        'additionalStoreLabel', 'additionalTerminalLabel', 'additionalBillNo',
        'additionalReferenceLabel', 'additionalMobileNumber', 'note', 'promoCode',
    ];

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    // ─── configuration ──────────────────────────────────────────────────────

    public function configured(): bool
    {
        return $this->missingConfig() === [];
    }

    /** @return array<int, string> */
    public function missingConfig(): array
    {
        return array_values(array_filter(
            ['base_url', 'username', 'password', 'secret'],
            fn (string $key) => blank($this->config[$key] ?? null),
        ));
    }

    public function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/');
    }

    /** True while pointed at the sandbox — the dashboard says so out loud. */
    public function sandbox(): bool
    {
        return str_contains($this->baseUrl(), 'apitest.');
    }

    public function secureHash(): SecureHash
    {
        return new SecureHash(
            (string) ($this->config['secret'] ?? ''),
            (string) ($this->config['hash_algo'] ?? 'sha512'),
            (string) ($this->config['hash_sort'] ?? 'value'),
        );
    }

    // ─── session ────────────────────────────────────────────────────────────

    /** Cached token, logging in when there is none. */
    public function token(): string
    {
        $cached = Cache::get($this->tokenCacheKey());

        return is_string($cached) && $cached !== '' ? $cached : $this->login();
    }

    public function forgetToken(): void
    {
        Cache::forget($this->tokenCacheKey());
    }

    /** Fetches a fresh token and caches it for as long as it stays valid. */
    public function login(): string
    {
        $this->assertConfigured();

        $response = $this->dispatch(self::ENDPOINT_LOGIN, fn (PendingRequest $request) => $request
            ->withBasicAuth((string) $this->config['username'], (string) $this->config['password'])
            ->post($this->url(self::ENDPOINT_LOGIN)));

        $token = $response->extra('access_token');

        if ($response->failed() || blank($token)) {
            Log::warning('jawwalpay.login.failed', [
                'errorCd' => $response->errorCode(),
                'desc'    => $response->description(),
                'ref'     => $response->reference(),
            ]);

            throw new JawwalPayException(
                'فشل تسجيل الدخول إلى جوال باي: ' . ($response->description() ?: ErrorCode::message($response->errorCode())),
                endpoint: self::ENDPOINT_LOGIN,
            );
        }

        Cache::put($this->tokenCacheKey(), $token, $this->tokenLifetime($token));

        return $token;
    }

    // ─── operations ─────────────────────────────────────────────────────────

    /** Merchant accounts and balances. */
    public function balance(?string $msgId = null): JawwalPayResponse
    {
        return $this->request(self::ENDPOINT_BALANCE, ['msgId' => $msgId ?? self::newMessageId()]);
    }

    /**
     * The merchant profile get_balance ships as a JSON string under `info`.
     *
     * @return array<string, mixed>
     */
    public function accountInfo(): array
    {
        return $this->balance()->extraJson('info') ?? [];
    }

    /** Step 1 of pay-to-merchant: text the customer a one-time code. */
    public function sendOtp(string $receiver, float|int|string $amount, string $msgId): JawwalPayResponse
    {
        return $this->request(self::ENDPOINT_SEND_OTP, [
            'msgId'    => $msgId,
            'receiver' => $this->receiver($receiver),
            'amount'   => self::formatAmount($amount),
        ]);
    }

    /**
     * Step 2: charge the customer with the code they were sent.
     *
     * The plain code the customer typed goes in — hashing it the way the wire
     * format wants is this method's job, so the raw code never has to travel
     * further into the app than here.
     *
     * @param  array<string, scalar|null>  $references  merchant-side labels (bill no, note …)
     */
    public function payWithOtp(
        string $receiver,
        float|int|string $amount,
        string $otp,
        string $msgId,
        array $references = [],
    ): JawwalPayResponse {
        return $this->request(self::ENDPOINT_MFP, array_merge(
            Arr::only($references, self::MFP_REFS),
            [
                'msgId'    => $msgId,
                'receiver' => $this->receiver($receiver),
                'amount'   => self::formatAmount($amount),
                'otp'      => self::hashOtp($otp),
            ],
        ));
    }

    /**
     * A QR the customer scans in the Jawwal Pay app. Dynamic carries the
     * amount; static is the shop's standing code and must not.
     *
     * @param  array<string, scalar|null>  $references
     */
    public function generateQr(
        float|int|string|null $amount = null,
        ?string $msgId = null,
        array $references = [],
    ): JawwalPayResponse {
        $dynamic = $amount !== null;

        return $this->request(self::ENDPOINT_GENERATE_QR, array_merge(
            Arr::only($references, self::QR_REFS),
            [
                'msgId'             => $msgId ?? self::newMessageId(),
                'initiationMethod'  => $dynamic ? self::QR_DYNAMIC : self::QR_STATIC,
                'transactionAmount' => $dynamic ? self::formatAmount($amount) : null,
            ],
        ));
    }

    /**
     * Transaction history. `dateFrom` is mandatory on their side, so a missing
     * one falls back to the last 30 days rather than erroring out.
     *
     * @param  array<string, scalar|null>  $filters
     */
    public function searchTransactions(array $filters = [], ?string $msgId = null): JawwalPayResponse
    {
        $filters['dateFrom'] ??= now()->subDays(30)->format('d/m/Y H:i');

        return $this->request(self::ENDPOINT_SEARCH_TRANS, array_merge($filters, [
            'msgId' => $msgId ?? self::newMessageId(),
        ]));
    }

    // ─── plumbing ───────────────────────────────────────────────────────────

    /**
     * Signs and sends one authenticated call.
     *
     * @param  array<string, scalar|null>  $payload
     */
    public function request(string $endpoint, array $payload, bool $retryExpiredSession = true): JawwalPayResponse
    {
        $this->assertConfigured();

        $body  = $this->body($payload);
        $token = $this->token();

        $response = $this->dispatch($endpoint, fn (PendingRequest $request) => $request
            ->withHeaders(['X-Auth-Token' => $token])
            ->post($this->url($endpoint), $body));

        // Their token lifetime is configurable and can lapse mid-checkout. The
        // call was rejected before processing, so replaying it with the same
        // msgId is safe — and keeps the message from counting as a duplicate.
        if ($retryExpiredSession && $response->errorCode() === ErrorCode::SIGN_IN_EXPIRED) {
            $this->forgetToken();

            return $this->request($endpoint, $payload, retryExpiredSession: false);
        }

        $this->log($endpoint, $body, $response);

        return $response;
    }

    /**
     * Fills in the defaults every request carries, drops the optional params
     * that were left out, and signs what remains.
     *
     * @param  array<string, scalar|null>  $payload
     * @return array<string, scalar>
     */
    public function body(array $payload): array
    {
        $payload['msgId'] ??= self::newMessageId();
        $payload['lang']  ??= (string) ($this->config['lang'] ?? 'AR');

        // An omitted optional param must not contribute an empty slot to the
        // hashed string, so it is removed before the hash is taken.
        $payload = array_filter($payload, static fn ($value) => $value !== null && $value !== '');

        $payload['secureHash'] = $this->secureHash()->for($payload);

        return $payload;
    }

    /**
     * @param  callable(PendingRequest): \Illuminate\Http\Client\Response  $perform
     */
    private function dispatch(string $endpoint, callable $perform): JawwalPayResponse
    {
        try {
            $response = $perform($this->http());
        } catch (ConnectionException $e) {
            throw JawwalPayException::transport($endpoint, $e);
        }

        $decoded = $response->json();

        // A rejected login answers 401 *with* a usable envelope; that is an
        // answer, not a transport failure, so the envelope wins over the status.
        if (is_array($decoded) && array_key_exists('errorCd', $decoded)) {
            return new JawwalPayResponse($decoded);
        }

        if ($response->failed()) {
            throw JawwalPayException::httpStatus($endpoint, $response->status(), $response->body());
        }

        throw JawwalPayException::malformed($endpoint, $response->body());
    }

    private function http(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->timeout((int) ($this->config['timeout'] ?? 30))
            ->connectTimeout((int) ($this->config['connect_timeout'] ?? 10));
    }

    private function url(string $endpoint): string
    {
        return $this->baseUrl() . $endpoint;
    }

    private function assertConfigured(): void
    {
        if ($missing = $this->missingConfig()) {
            throw JawwalPayException::notConfigured(implode(', ', $missing));
        }
    }

    private function receiver(string $number): string
    {
        return MobileNumber::normalize($number)
            ?? throw JawwalPayException::invalidReceiver($number);
    }

    /** Scoped to the account and environment, so switching either re-logs in. */
    private function tokenCacheKey(): string
    {
        return 'jawwalpay:token:' . sha1($this->baseUrl() . '|' . (string) ($this->config['username'] ?? ''));
    }

    /**
     * The token carries its own `exp`; honour it (minus a safety margin) and
     * fall back to the configured ceiling when it cannot be read.
     */
    private function tokenLifetime(string $token): int
    {
        $ceiling = max(60, (int) ($this->config['token_ttl'] ?? 3600));
        $parts   = explode('.', $token);

        if (count($parts) !== 3) {
            return $ceiling;
        }

        $claims = json_decode((string) base64_decode(strtr($parts[1], '-_', '+/'), false), true);
        $expiry = is_array($claims) ? (int) ($claims['exp'] ?? 0) : 0;

        if ($expiry <= 0) {
            return $ceiling;
        }

        return max(60, min($ceiling, $expiry - time() - 60));
    }

    /**
     * @param  array<string, scalar>  $body
     */
    private function log(string $endpoint, array $body, JawwalPayResponse $response): void
    {
        if (! ($this->config['log'] ?? true)) {
            return;
        }

        $level = $response->successful() ? 'info' : 'warning';

        // Everything sensitive is dropped here rather than at the call sites:
        // the OTP hash is still a credential, and secureHash plus the token
        // would let anyone reading logs mint their own requests.
        Log::log($level, 'jawwalpay.' . trim($endpoint, '/'), [
            'msgId'    => $body['msgId'] ?? null,
            'receiver' => isset($body['receiver']) ? MobileNumber::mask((string) $body['receiver']) : null,
            'amount'   => $body['amount'] ?? $body['transactionAmount'] ?? null,
            'errorCd'  => $response->errorCode(),
            'desc'     => $response->description(),
            'ref'      => $response->reference(),
        ]);
    }

    // ─── wire-format helpers ────────────────────────────────────────────────

    /** Rolls per call so two ids minted in the same microsecond still differ. */
    private static ?int $sequence = null;

    /**
     * Unique per attempt — the gateway rejects a reused msgId with code 46.
     * 14 digits, matching the shape of every id in the guide: 11 of
     * microsecond clock plus a 3-digit counter.
     *
     * The counter is what makes this safe. The clock alone is not: several
     * calls can land in one microsecond, and a purely random tail collides
     * about once in a thousand of those. Seeding the counter randomly keeps
     * concurrent workers from marching in step.
     */
    public static function newMessageId(): string
    {
        self::$sequence = self::$sequence === null
            ? random_int(0, 999)
            : (self::$sequence + 1) % 1000;

        $microseconds = (string) (int) (microtime(true) * 1000000);

        return substr($microseconds, -11) . str_pad((string) self::$sequence, 3, '0', STR_PAD_LEFT);
    }

    /**
     * "hashed in SHA-256 and encoded in base64" — the raw 32-byte digest, not
     * its hex form: the guide's sample decodes to exactly 32 bytes.
     */
    public static function hashOtp(string $otp): string
    {
        return base64_encode(hash('sha256', trim($otp), true));
    }

    /**
     * Amounts travel as strings and are part of the hashed input, so the shape
     * has to be stable. The guide only ever shows whole numbers ("500", "50",
     * "100"), so trailing zeros come off rather than being padded to 2dp.
     */
    public static function formatAmount(float|int|string $amount): string
    {
        $formatted = number_format((float) $amount, 2, '.', '');

        return str_contains($formatted, '.')
            ? rtrim(rtrim($formatted, '0'), '.')
            : $formatted;
    }
}
