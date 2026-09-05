<?php

namespace App\Console\Commands;

use App\Services\JawwalPay\ErrorCode;
use App\Services\JawwalPay\JawwalPayClient;
use App\Services\JawwalPay\JawwalPayException;
use Illuminate\Console\Command;

/**
 * Verifies the integration against a live Service Bus environment.
 *
 * This exists because of §3 of the merchant guide: it describes the secureHash
 * two contradictory ways, and the digest in its own worked example reproduces
 * under neither. get_balance is the cheapest signed, read-only call there is,
 * so it is what settles the question — and --sort / --algo let the alternatives
 * be tried without touching .env.
 */
class JawwalPayCheck extends Command
{
    protected $signature = 'jawwalpay:check
        {--sort= : Override the secureHash ordering for this run (value|key)}
        {--algo= : Override the secureHash digest for this run (e.g. sha512, sha256)}
        {--otp : Also call send_otp, which texts a real code to the wallet}
        {--wallet= : Wallet to send that code to (default: JAWWALPAY_TEST_WALLET)}
        {--amount=1 : Amount for the send_otp probe}';

    protected $description = 'Check the Jawwal Pay credentials, session and secureHash against the live gateway';

    public function handle(): int
    {
        $config = config('services.jawwalpay', []);

        foreach (['sort' => 'hash_sort', 'algo' => 'hash_algo'] as $option => $key) {
            if ($value = $this->option($option)) {
                $config[$key] = $value;
            }
        }

        $client = new JawwalPayClient($config);

        $this->components->info('Jawwal Pay — connection check');
        $this->newLine();

        $this->table(['setting', 'value'], [
            ['base_url', $client->baseUrl() ?: '(missing)'],
            ['environment', $client->sandbox() ? 'sandbox' : 'PRODUCTION'],
            ['username', $config['username'] ?: '(missing)'],
            ['password', $config['password'] ? str_repeat('•', 8) : '(missing)'],
            ['secret', $config['secret'] ? str_repeat('•', 8) : '(missing)'],
            ['hash_algo', $config['hash_algo'] ?? 'sha512'],
            ['hash_sort', $config['hash_sort'] ?? 'value'],
        ]);

        if (! $client->configured()) {
            $this->components->error('Missing settings: ' . implode(', ', $client->missingConfig()));
            $this->line('  Set them in .env — see .env.example.');

            return self::FAILURE;
        }

        if (! $client->sandbox() && ! $this->confirm('This points at PRODUCTION. Continue?', false)) {
            return self::FAILURE;
        }

        // ── 1. login: credentials only, nothing signed ──────────────────────
        try {
            $client->forgetToken();
            $client->login();
            $this->components->info('login — ok (token received and cached)');
        } catch (JawwalPayException $e) {
            $this->components->error('login — failed');
            $this->line('  ' . $e->getMessage());
            $this->newLine();

            // A transport failure never reached the gateway, so the credentials
            // were never read. Blaming them sends whoever runs this off to
            // re-check a password that was fine all along.
            if ($e->status === null) {
                $this->line('  The request never reached the gateway, so this says nothing about');
                $this->line('  the credentials. Jawwal Pay allowlists by IP: a silent timeout is');
                $this->line('  the signature of an address that is not on the list.');
                $this->line('  Send the OUTBOUND address of this server to Ahmad Amro — not the one');
                $this->line('  the domain resolves to, which may be a CDN.');
            } else {
                $this->line('  The gateway answered and refused: check JAWWALPAY_USERNAME /');
                $this->line('  JAWWALPAY_PASSWORD for this environment. Sandbox and production');
                $this->line('  credentials are not interchangeable.');
            }

            return self::FAILURE;
        }

        // ── 2. get_balance: the first signed call, so it proves secureHash ──
        try {
            $response = $client->balance();
        } catch (JawwalPayException $e) {
            $this->components->error('get_balance — no usable answer');
            $this->line('  ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($response->failed()) {
            $this->components->error(sprintf(
                'get_balance — rejected: %s (%s)',
                $response->errorCode(),
                ErrorCode::label($response->errorCode()),
            ));
            $this->line('  desc: ' . $response->description());
            $this->newLine();
            $this->line('  If this reads as an invalid secure hash, the guide\'s §3 is ambiguous.');
            $this->line('  Try the other ordering, then the other digest:');
            $this->line('    php artisan jawwalpay:check --sort=key');
            $this->line('    php artisan jawwalpay:check --algo=sha256');
            $this->line('  Whichever succeeds, pin it in .env as JAWWALPAY_HASH_SORT / JAWWALPAY_HASH_ALGO.');

            return self::FAILURE;
        }

        $this->components->info('get_balance — ok (secureHash accepted)');

        $info     = $response->extraJson('info') ?? [];
        $accounts = $info['accounts'] ?? [];

        if ($accounts !== []) {
            $this->newLine();
            $this->table(
                ['account', 'type', 'category', 'balance'],
                array_map(fn (array $account) => [
                    $account['accountNumber'] ?? '—',
                    $account['accountType'] ?? '—',
                    $account['accountCategory'] ?? '—',
                    $account['balance'] ?? '—',
                ], $accounts),
            );
        }

        if (! $this->option('otp')) {
            $this->newLine();
            $this->components->info('Integration is live. Add --otp to exercise the payment path too.');

            return self::SUCCESS;
        }

        return $this->probeSendOtp($client, $config);
    }

    /**
     * Step 3, opt-in: send_otp against the sandbox wallet.
     *
     * get_balance proves the credentials and the signature, but it is a
     * read. This is the first call that moves the payment flow, so it is what
     * proves the `receiver` and `amount` formats the guide is vague about.
     *
     * Behind a flag because it texts a live code to a real handset. MFP is
     * deliberately not automated: it needs the code off that handset, and a
     * command that asks for one is a command that charges the wallet.
     *
     * @param  array<string, mixed>  $config
     */
    private function probeSendOtp(JawwalPayClient $client, array $config): int
    {
        $wallet = (string) ($this->option('wallet') ?: ($config['test_wallet'] ?? ''));

        if ($wallet === '') {
            $this->newLine();
            $this->components->error('No wallet to test — pass --wallet= or set JAWWALPAY_TEST_WALLET.');

            return self::FAILURE;
        }

        $amount = (string) $this->option('amount');

        $this->newLine();
        $this->line("  send_otp — {$amount} to {$wallet}");

        try {
            $response = $client->sendOtp($wallet, $amount, JawwalPayClient::newMessageId());
        } catch (JawwalPayException $e) {
            $this->components->error('send_otp — no usable answer');
            $this->line('  ' . $e->getMessage());

            return self::FAILURE;
        }

        if ($response->failed()) {
            $this->components->error(sprintf(
                'send_otp — rejected: %s (%s)',
                $response->errorCode(),
                ErrorCode::label($response->errorCode()),
            ));
            $this->line('  desc: ' . $response->description());
            $this->newLine();
            // The two that actually happen, and they look nothing alike in
            // the guide: a wallet the sandbox does not know, and a number
            // shaped differently from what `receiver` expects.
            $this->line('  A rejection here is about the wallet or its format, not the signature —');
            $this->line('  get_balance already proved the hash is accepted.');

            return self::FAILURE;
        }

        $this->components->info('send_otp — accepted; a code has been texted to that wallet.');
        $this->newLine();
        $this->line('  MFP is not automated: it needs the code off the handset, and');
        $this->line('  completing it charges the wallet. Place a real sandbox order instead.');

        return self::SUCCESS;
    }
}
