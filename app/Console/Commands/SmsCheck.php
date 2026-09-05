<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsSender;
use App\Support\PhoneNumber;
use Illuminate\Console\Command;
use Throwable;

/**
 * Prove the SMS path end to end, before a customer does it for us.
 *
 * Without `--to` nothing is sent: it only reports what is configured and
 * whether the active driver has what it needs. Sending a real message is an
 * explicit act, because it costs credit and rings somebody's phone.
 */
class SmsCheck extends Command
{
    protected $signature = 'sms:check
        {--to= : Send a real test message to this number}
        {--message= : Override the test message text}';

    protected $description = 'Report the SMS configuration and optionally send a real test message';

    public function handle(SmsSender $sender): int
    {
        $driver = $sender->driver();

        $this->newLine();
        $this->line('  Driver   : ' . $driver);
        $this->line('  Live     : ' . ($sender->live() ? 'yes' : 'no — messages do not leave the building'));
        $this->line('  Ready    : ' . ($sender->ready() ? 'yes' : 'NO'));

        match ($driver) {
            'hotsms' => $this->reportHotSms($sender),
            'smpp'   => $this->reportSmpp(),
            'rest'   => $this->reportRest($sender),
            default  => null,
        };

        if (! $sender->ready()) {
            $this->newLine();
            $this->error('The active driver is not fully configured — see the settings above.');

            return self::FAILURE;
        }

        $to = $this->option('to');

        if (! $to) {
            $this->newLine();
            $this->comment('Pass --to=05XXXXXXXX to send a real test message.');

            return self::SUCCESS;
        }

        $number = PhoneNumber::normalize($to);

        if ($number === null) {
            $this->error("«{$to}» is not a Palestinian mobile number.");

            return self::FAILURE;
        }

        // Arabic on purpose: it is the encoding path that actually breaks, and
        // a test that only proves ASCII works proves nothing about OTP text.
        $message = (string) ($this->option('message')
            ?? 'رسالة اختبار من جلاسيه الأمير — Glace test ' . now()->format('H:i'));

        $this->newLine();
        $this->info("Sending to {$number}…");

        try {
            $sender->send($number, $message);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Sent. Check the handset — confirm the Arabic reads correctly and is not reversed.');

        return self::SUCCESS;
    }

    private function reportHotSms(SmsSender $sender): void
    {
        $config = (array) config('services.sms.hotsms');

        $this->line('  Base URL : ' . ($config['base_url'] ?: 'NOT SET'));
        $this->line('  Username : ' . ($config['username'] ?: 'NOT SET'));
        $this->line('  Password : ' . ($config['password'] ? '(set)' : 'NOT SET'));
        $this->line('  Sender   : ' . ($config['sender'] ?: 'NOT SET'));

        if (! $sender->hotsms()->configured()) {
            return;
        }

        // This one round trip proves four things at once: the host resolves,
        // this IP is allowed to reach the account, API access is switched on,
        // and the credentials are right. Nothing here sends a message.
        $credits = $sender->hotsms()->credits();

        $this->line('  Credits  : ' . ($credits === null
            ? 'unavailable — credentials rejected, or this IP is not on the allowlist'
            : (int) $credits));

        $this->newLine();
        $this->comment('  The sender name must be one the account has approved, or the send is refused with 6000.');
    }

    private function reportSmpp(): void
    {
        $config = (array) config('services.sms.smpp');

        $this->line('  Host     : ' . ($config['host'] ?? '—') . ':' . ($config['port'] ?? '—'));
        $this->line('  Username : ' . ($config['username'] ?: 'NOT SET'));
        $this->line('  Password : ' . ($config['password'] ? '(set)' : 'NOT SET'));
        $this->line('  Sender   : ' . ($config['sender'] ?: 'NOT SET'));
        $this->newLine();
        // The one that bites: an unapproved sender name is rejected at submit
        // time with a code that reads like a bad phone number.
        $this->comment('  The sender name must be approved by the operator, or submit_sm returns ESME_RINVSRCADR.');
    }

    private function reportRest(SmsSender $sender): void
    {
        $config = (array) config('services.sms.rest');

        $this->line('  Base URL : ' . ($config['base_url'] ?: 'NOT SET'));
        $this->line('  Token    : ' . ($config['token'] ? '(set)' : 'NOT SET — run php artisan sms:login'));
        $this->line('  Sender   : ' . ($config['sender'] ?: 'NOT SET'));

        if (! $sender->rest()->configured()) {
            return;
        }

        $credits = $sender->rest()->credits();

        $this->line('  Credits  : ' . ($credits === null ? 'unavailable' : $credits));

        if ($senders = $sender->rest()->senders()) {
            $this->line('  Approved : ' . implode(', ', $senders));
        }
    }
}
