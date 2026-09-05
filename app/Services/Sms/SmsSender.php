<?php

namespace App\Services\Sms;

use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Sends one SMS, through whichever channel the shop has been given.
 *
 * Four drivers, and the choice between them is a deployment fact, not a
 * preference:
 *
 *   `hotsms` — the provider's HTTP v1 API. Username and password on every
 *              call, so nothing has to be provisioned and nothing expires.
 *              The default, because an OTP path that needs a human to
 *              re-authenticate is an OTP path that will be down one morning.
 *   `smpp` — a direct bind to the operator's SMSC. Fewest moving parts between
 *            us and the network, and the credentials we hold.
 *   `rest` — the provider's REST v2 API. Needs a token obtained once by a
 *            human (`php artisan sms:login`), because their login texts a PIN.
 *   `http` — a generic JSON POST, for any other provider.
 *   `log`  — writes the message to the application log. The whole OTP flow
 *            stays exercisable locally without an account.
 *
 * `log` is REFUSED in production and throws at send time. A one-time code that
 * only ever reaches storage/logs is not a login, and failing loudly at the
 * first attempt is far better than a shop discovering on launch day that no
 * customer can sign in.
 */
class SmsSender
{
    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config = []) {}

    /**
     * @throws RuntimeException when the message could not be handed off
     */
    public function send(string $phone, string $message): void
    {
        // Every gateway here wants the international form; the national one is
        // for storage and for showing a customer their own number.
        $number = PhoneNumber::international($phone) ?? $phone;

        match ($this->driver()) {
            'log'    => $this->logOnly($number, $message),
            'hotsms' => $this->viaHotSms($number, $message),
            'smpp' => $this->viaSmpp($number, $message),
            'rest' => $this->viaRest($number, $message),
            'http' => $this->viaHttp($number, $message),
            'null' => null,
            default => throw new RuntimeException("Unknown SMS driver [{$this->driver()}]."),
        };
    }

    /** Whether messages actually leave the building. */
    public function live(): bool
    {
        return ! in_array($this->driver(), ['log', 'null'], true);
    }

    public function driver(): string
    {
        return (string) ($this->config['driver'] ?? 'log');
    }

    /**
     * Whether the active driver has everything it needs.
     *
     * Read by the dashboard so a misconfiguration is visible before a customer
     * finds it, rather than after.
     */
    public function ready(): bool
    {
        return match ($this->driver()) {
            'log', 'null' => ! app()->environment('production'),
            'hotsms'      => $this->hotsms()->configured(),
            'smpp'        => $this->smpp()->configured(),
            'rest'        => $this->rest()->configured(),
            'http'        => filled($this->config['http']['url'] ?? null),
            default       => false,
        };
    }

    public function hotsms(): HotSmsClient
    {
        return new HotSmsClient((array) ($this->config['hotsms'] ?? []));
    }

    public function smpp(): SmppClient
    {
        return new SmppClient((array) ($this->config['smpp'] ?? []));
    }

    public function rest(): SmsRestClient
    {
        return new SmsRestClient((array) ($this->config['rest'] ?? []));
    }

    // ─── drivers ────────────────────────────────────────────────────────────

    private function logOnly(string $number, string $message): void
    {
        if (app()->environment('production')) {
            throw new RuntimeException(
                'SMS_DRIVER is "log" in production — one-time codes would never reach customers. '
                . 'Configure a real gateway (SMS_DRIVER=smpp or rest) before going live.',
            );
        }

        // The code is in the message. That is the point in local development,
        // and exactly why the production guard above exists.
        Log::channel($this->config['log_channel'] ?? null)
            ->info('[sms] to ' . $number . ': ' . $message);
    }

    private function viaHotSms(string $number, string $message): void
    {
        $reference = $this->hotsms()->send($number, $message);

        Log::info('[sms] sent via hotsms', ['to' => PhoneNumber::mask($number), 'ref' => $reference]);
    }

    private function viaSmpp(string $number, string $message): void
    {
        $reference = $this->smpp()->send($number, $message);

        // The reference, never the body: the body is the one-time code.
        Log::info('[sms] sent via smpp', ['to' => PhoneNumber::mask($number), 'ref' => $reference]);
    }

    private function viaRest(string $number, string $message): void
    {
        $reference = $this->rest()->send($number, $message);

        Log::info('[sms] sent via rest', ['to' => PhoneNumber::mask($number), 'ref' => $reference]);
    }

    private function viaHttp(string $number, string $message): void
    {
        $http = (array) ($this->config['http'] ?? []);
        $url  = (string) ($http['url'] ?? '');

        if ($url === '') {
            throw new RuntimeException('SMS_HTTP_URL is not configured.');
        }

        $response = Http::timeout((int) ($http['timeout'] ?? 15))
            ->withHeaders(array_filter([
                'Authorization' => ($token = $http['token'] ?? null) ? 'Bearer ' . $token : null,
            ]))
            ->asJson()
            ->post($url, [
                ($http['to_field'] ?? 'to')        => $number,
                ($http['body_field'] ?? 'message') => $message,
                ...(array) ($http['extra'] ?? []),
            ]);

        if ($response->failed()) {
            // Body deliberately not logged: it echoes the message, and the
            // message is the one-time code.
            throw new RuntimeException('SMS gateway rejected the message (HTTP ' . $response->status() . ').');
        }
    }
}
