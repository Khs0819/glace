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

    /**
     * The shape Jawwal Pay themselves described (2026-09-28): the X-Auth-Token
     * sorted in with the parameter values, HMAC-SHA512 with the secret,
     * lowercase hex — and every field signed.
     *
     * @var array<string, array{0: mixed, 1: string}>  setting => [expected, env name]
     */
    private const DESCRIBED_SHAPE = [
        'hash_algo'          => ['sha512', 'JAWWALPAY_HASH_ALGO'],
        'hash_sort'          => ['value', 'JAWWALPAY_HASH_SORT'],
        'hash_mode'          => ['hmac', 'JAWWALPAY_HASH_MODE'],
        'hash_case'          => ['lower', 'JAWWALPAY_HASH_CASE'],
        'hash_exclude'       => ['', 'JAWWALPAY_HASH_EXCLUDE'],
        'hash_layout'        => ['values', 'JAWWALPAY_HASH_LAYOUT'],
        'hash_separator'     => ['', 'JAWWALPAY_HASH_SEPARATOR'],
        'hash_encoding'      => ['hex', 'JAWWALPAY_HASH_ENCODING'],
        'hash_key_form'      => ['raw', 'JAWWALPAY_HASH_KEY_FORM'],
        'hash_include_token' => [true, 'JAWWALPAY_HASH_INCLUDE_TOKEN'],
    ];

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
            // Shown because pinning them is how a 1004 gets fixed: without
            // these on screen there is no way to tell whether the values found
            // by jawwalpay:probe actually reached the app.
            ['hash_mode', $config['hash_mode'] ?? 'hmac'],
            ['hash_case', $config['hash_case'] ?? 'lower'],
            ['hash_exclude', ($config['hash_exclude'] ?? '') ?: '(signs everything)'],
            ['hash_include_token', ($config['hash_include_token'] ?? true) ? 'yes' : 'no'],
        ]);

        $this->reportDeviations($config);

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
            $this->line('  If this reads as an invalid secure hash (1004), the guide\'s §3 is');
            $this->line('  ambiguous and the shape has to be found against the gateway itself:');
            $this->line('    php artisan jawwalpay:probe');
            $this->line('  It tries every combination on get_balance — read-only, nothing is');
            $this->line('  charged — and prints the .env lines for whichever one is accepted.');

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
    /**
     * Say when the settings are not the shape the provider described.
     *
     * A value left behind from an experiment looks like nothing on screen and
     * quietly changes what gets signed — which is how a run that was meant to
     * test their shape ended up testing "sign everything but lang, and glue
     * the key on the end" instead.
     *
     * @param  array<string, mixed>  $config
     */
    private function reportDeviations(array $config): void
    {
        $deviations = [];

        foreach (self::DESCRIBED_SHAPE as $setting => [$expected, $env]) {
            $actual = $config[$setting] ?? $expected;

            if (is_bool($expected)) {
                $actual = (bool) $actual;
            } else {
                $actual = (string) $actual;
            }

            if ($actual !== $expected) {
                $deviations[] = [
                    $env,
                    is_bool($actual) ? ($actual ? 'true' : 'false') : ($actual === '' ? '(empty)' : $actual),
                    is_bool($expected) ? ($expected ? 'true' : 'false') : ($expected === '' ? '(empty)' : $expected),
                ];
            }
        }

        if ($deviations === []) {
            return;
        }

        $this->newLine();
        $this->components->warn('These settings are not the shape Jawwal Pay described.');
        $this->table(['.env', 'now', 'described'], $deviations);
        $this->line('  Their integration lead: the X-Auth-Token is sorted in with the parameter');
        $this->line('  values, then hashed with SHA-512 using the secret key — every field signed.');
        $this->line('  Values left over from an earlier experiment change what gets signed, and');
        $this->line('  the call below is then not the test it looks like. To restore it:');
        $this->newLine();

        foreach ($deviations as [$env, , $described]) {
            $this->line('    ' . $env . '=' . ($described === '(empty)' ? '' : $described));
        }

        $this->newLine();
        $this->line('  Then: php artisan config:clear');
    }

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
