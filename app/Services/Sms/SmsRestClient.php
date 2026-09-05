<?php

namespace App\Services\Sms;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * The provider's SMS REST API v2 (SMS_API_v2.postman_collection.json).
 *
 * One thing about this API shapes the whole design: **login is interactive.**
 * It is a two-step flow — credentials first, which texts a PIN to the account
 * owner, then the same credentials plus that PIN to get an `api_token`. A
 * server cannot do that unattended.
 *
 * So the token is obtained once, by a human, through `php artisan sms:login`,
 * and lives in SMS_API_TOKEN from then on. Nothing here ever tries to log in
 * on the fly — it would text a PIN to somebody's phone in the middle of a
 * customer's checkout and then fail anyway.
 */
class SmsRestClient
{
    /** Their success code. Anything else is a failure, whatever the HTTP status. */
    private const CODE_OK = 1001;

    /**
     * 1002 is overloaded: on /auth/login it means "a PIN has been texted", and
     * everywhere else it means "you sent no token". Only the login path may
     * read it as a challenge.
     */
    private const CODE_OTP_REQUIRED = 1002;

    /**
     * What to actually do about a rejection.
     *
     * Their `message` names the fault but not the fix, and the two that come
     * up in practice are both settings the account holder changes in the
     * portal — not something to debug in the application.
     */
    private const REMEDIES = [
        1003 => 'التوكن غير صالح أو أُلغي — أعد تشغيل php artisan sms:login',
        1101 => 'لا يوجد رصيد كافٍ — اشحن الحساب',
        1401 => 'خدمة API غير مفعّلة على الحساب — فعّلها من أدوات الأمان في البورتال',
        1402 => 'هذا العنوان (IP) غير مسموح — أضفه للقائمة في إعدادات الحساب، أو اترك القائمة فارغة',
        1608 => 'اسم المرسل غير مسموح — يجب أن يحتوي حرفاً إنجليزياً وألا يتجاوز 11 خانة',
    ];

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config = []) {}

    public function configured(): bool
    {
        return $this->baseUrl() !== '' && filled($this->config['token'] ?? null);
    }

    /**
     * @return string the provider's reference for the message
     *
     * @throws RuntimeException
     */
    public function send(string $destination, string $message): string
    {
        if (! $this->configured()) {
            throw new RuntimeException(
                'إعدادات SMS REST غير مكتملة — شغّل php artisan sms:login للحصول على التوكن',
            );
        }

        $response = $this->request()->post($this->url('messages/send'), [
            'mobile'  => $destination,
            'message' => $message,
            'sender'  => (string) ($this->config['sender'] ?? ''),
        ]);

        $body = $this->decode($response->json(), 'تعذّر إرسال الرسالة');

        return (string) ($body['data']['provider_response']['sms_refs'][0]
            ?? $body['data']['provider'] ?? 'sent');
    }

    /** What a send would cost, without sending it or spending credit. */
    public function summary(string $destination, string $message): array
    {
        $response = $this->request()->post($this->url('messages/send/summary'), [
            'mobile'  => $destination,
            'message' => $message,
            'sender'  => (string) ($this->config['sender'] ?? ''),
        ]);

        return $this->decode($response->json(), 'تعذّر حساب تكلفة الرسالة')['data'] ?? [];
    }

    public function credits(): ?float
    {
        $response = $this->request()->get($this->url('credits'));

        if ($response->failed()) {
            return null;
        }

        $credits = $response->json('data.credits');

        return $credits === null ? null : (float) $credits;
    }

    /** @return array<int, string> Sender names the account may actually use. */
    public function senders(): array
    {
        $response = $this->request()->get($this->url('senders'));

        if ($response->failed()) {
            return [];
        }

        // A flat list of names under `data.senders` — not a list of objects,
        // and not `data` itself. A newly requested name is absent from here
        // until an admin activates it, which is exactly what makes this the
        // authoritative answer to "what may SMS_SENDER be set to".
        $senders = $response->json('data.senders') ?? [];

        return array_values(array_filter(array_map(
            static fn ($row) => is_array($row) ? ($row['sender'] ?? $row['name'] ?? null) : (string) $row,
            is_array($senders) ? $senders : [],
        )));
    }

    // ─── the interactive login, for the artisan command ─────────────────────

    /**
     * Step 1 — ask for a PIN. The provider texts it to the account owner.
     *
     * @return bool true when a PIN was sent and step 2 is needed
     */
    public function requestLoginPin(string $username, string $password): bool
    {
        $body = Http::asJson()
            ->timeout($this->timeout())
            ->post($this->url('auth/login'), [
                'user_name'       => $username,
                'user_pass'       => $password,
                'pin_code'        => '',
                'force_new_token' => false,
            ])
            ->json();

        $code = (int) ($body['code'] ?? 0);

        // A token straight away means the account already had a live one and
        // did not need a challenge.
        if ($code === self::CODE_OK && filled($body['data']['api_token'] ?? null)) {
            return false;
        }

        if ($code !== self::CODE_OTP_REQUIRED) {
            throw new RuntimeException($this->message($body, 'تعذّر بدء تسجيل الدخول'));
        }

        return true;
    }

    /** Step 2 — exchange the PIN for the long-lived token. */
    public function completeLogin(string $username, string $password, string $pin = '', bool $forceNew = false): string
    {
        $body = Http::asJson()
            ->timeout($this->timeout())
            ->post($this->url('auth/login'), [
                'user_name'       => $username,
                'user_pass'       => $password,
                'pin_code'        => $pin,
                'force_new_token' => $forceNew,
            ])
            ->json();

        $token = $body['data']['api_token'] ?? null;

        if ((int) ($body['code'] ?? 0) !== self::CODE_OK || blank($token)) {
            throw new RuntimeException($this->message($body, 'رمز التحقق غير صحيح أو منتهي'));
        }

        return (string) $token;
    }

    // ─── plumbing ───────────────────────────────────────────────────────────

    private function request()
    {
        // The API accepts three token formats; both headers are sent because
        // proxies have been known to strip Authorization.
        return Http::asJson()
            ->timeout($this->timeout())
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->config['token'],
                'X-API-TOKEN'   => (string) $this->config['token'],
                'Accept'        => 'application/json',
            ]);
    }

    /**
     * @param  mixed  $body
     * @return array<string, mixed>
     */
    private function decode($body, string $context): array
    {
        if (! is_array($body)) {
            throw new RuntimeException($context . ' — رد غير مفهوم من المزوّد');
        }

        // Their envelope carries the real outcome; a 200 with the wrong code is
        // still a failure.
        if ((int) ($body['code'] ?? 0) !== self::CODE_OK) {
            throw new RuntimeException($this->message($body, $context));
        }

        return $body;
    }

    /** @param mixed $body */
    private function message($body, string $fallback): string
    {
        if (! is_array($body)) {
            return $fallback;
        }

        $detail = $body['message'] ?? $body['error'] ?? null;
        $text   = $detail === null
            ? $fallback
            : $fallback . ' — ' . (is_string($detail) ? $detail : json_encode($detail));

        if ($remedy = self::REMEDIES[(int) ($body['code'] ?? 0)] ?? null) {
            $text .= ' — ' . $remedy;
        }

        return $text;
    }

    private function url(string $path): string
    {
        return $this->baseUrl() . '/api/rest/v2/' . ltrim($path, '/');
    }

    private function baseUrl(): string
    {
        return rtrim((string) ($this->config['base_url'] ?? ''), '/');
    }

    private function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 15);
    }
}
