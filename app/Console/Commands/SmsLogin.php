<?php

namespace App\Console\Commands;

use App\Services\Sms\SmsSender;
use Illuminate\Console\Command;
use Throwable;

/**
 * Obtain the SMS REST API token, interactively.
 *
 * Their login is a two-step flow: credentials first, which texts a PIN to the
 * account owner's phone, then the same credentials plus that PIN. A server
 * cannot do that on its own, which is why it is a command a person runs once
 * rather than something the sender does on demand.
 */
class SmsLogin extends Command
{
    protected $signature = 'sms:login
        {--force : Ask for a brand new token instead of reusing a live one}';

    protected $description = 'Log in to the SMS REST API and print the api_token for SMS_API_TOKEN';

    public function handle(SmsSender $sender): int
    {
        $client = $sender->rest();

        $username = (string) config('services.sms.rest.username');
        $password = (string) config('services.sms.rest.password');
        $baseUrl  = (string) config('services.sms.rest.base_url');

        if ($baseUrl === '') {
            $this->error('SMS_API_URL is not set — the provider gave us SMPP details but no REST host.');
            $this->line('Ask them for the REST v2 base URL, or use SMS_DRIVER=smpp instead.');

            return self::FAILURE;
        }

        if ($username === '' || $password === '') {
            $this->error('SMS_API_USERNAME / SMS_API_PASSWORD are not set.');

            return self::FAILURE;
        }

        $this->info("Logging in to {$baseUrl} as {$username}…");

        try {
            $needsPin = $client->requestLoginPin($username, $password);

            $pin = '';

            if ($needsPin) {
                $this->line('A PIN has been sent by SMS to the account owner.');
                $pin = (string) $this->ask('Enter the PIN');
            } else {
                $this->line('The account already had a live token; no PIN needed.');
            }

            $token = $client->completeLogin($username, $password, $pin, (bool) $this->option('force'));
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('Token obtained. Put this in your .env:');
        $this->newLine();
        $this->line('SMS_API_TOKEN=' . $token);
        $this->newLine();
        // Said plainly: the value is a credential and the console is not a
        // place it should linger.
        $this->comment('Treat it like a password — anything holding it can send SMS on the shop\'s account.');

        return self::SUCCESS;
    }
}
