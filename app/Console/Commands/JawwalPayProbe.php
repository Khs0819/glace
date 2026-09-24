<?php

namespace App\Console\Commands;

use App\Services\JawwalPay\JawwalPayClient;
use App\Services\JawwalPay\JawwalPayException;
use App\Services\JawwalPay\SecureHash;
use Illuminate\Console\Command;

/**
 * Find the secureHash shape this gateway actually accepts.
 *
 * Production answered every signed call with 1004 "Bad SecureHash" while login
 * succeeded — so the network, the IP allowlist and the credentials are fine and
 * only the signature is wrong. The merchant guide contradicts itself about how
 * that signature is built (see SecureHash), and its worked example reproduces
 * under none of the readings, so the shape cannot be settled by reading.
 *
 * This asks the gateway instead: every combination is tried against
 * get_balance — signed, read-only, and it moves no money — and the first one
 * accepted is the answer. It prints the .env lines to pin it with.
 */
class JawwalPayProbe extends Command
{
    protected $signature = 'jawwalpay:probe
        {--algos=sha512,sha256,sha1,md5 : Digests to try}
        {--all : Keep going after the first accepted shape}';

    protected $description = 'Try every secureHash shape against the gateway and report which one it accepts';

    public function handle(): int
    {
        $config = config('services.jawwalpay', []);
        $client = new JawwalPayClient($config);

        $this->newLine();
        $this->components->info('Jawwal Pay — secureHash probe');
        $this->line('  host: ' . ($client->baseUrl() ?: '(missing)'));
        $this->line('  ' . ($client->sandbox() ? 'sandbox' : 'PRODUCTION') . ' — get_balance only, nothing is charged.');
        $this->newLine();

        if (! $client->configured()) {
            $this->components->error('Missing settings: ' . implode(', ', $client->missingConfig()));

            return self::FAILURE;
        }

        // One login for the whole run: the token is cached and every shape
        // reuses it, so a rejection is the signature and nothing else.
        try {
            $client->forgetToken();
            $client->login();
            $this->components->info('login — ok');
        } catch (JawwalPayException $e) {
            $this->components->error('login — failed: ' . $e->getMessage());
            $this->line('  Nothing below would mean anything without a session.');

            return self::FAILURE;
        }

        $algos = array_filter(array_map('trim', explode(',', (string) $this->option('algos'))));
        $rows  = [];
        $found = [];

        // Signing everything sent, and signing the business fields only: `lang`
        // travels in every request but need not be part of the signature.
        foreach (['', 'lang'] as $exclude) {
            foreach (['value', 'key'] as $sort) {
                foreach ($algos as $algo) {
                    foreach (SecureHash::MODES as $mode) {
                        foreach (['lower', 'upper'] as $case) {
                            $attempt = new JawwalPayClient(array_merge($config, [
                                'hash_sort'    => $sort,
                                'hash_algo'    => $algo,
                                'hash_mode'    => $mode,
                                'hash_case'    => $case,
                                'hash_exclude' => $exclude,
                            ]));

                            try {
                                $response = $attempt->balance();
                                $outcome  = $response->successful()
                                    ? 'ACCEPTED'
                                    : $response->errorCode() . ' — ' . $response->description();
                                $ok = $response->successful();
                            } catch (JawwalPayException $e) {
                                $outcome = 'no answer: ' . $e->getMessage();
                                $ok      = false;
                            }

                            $rows[] = [$sort, $algo, $mode, $case, $exclude === '' ? 'all' : "-{$exclude}", $outcome];

                            if ($ok) {
                                $found[] = compact('sort', 'algo', 'mode', 'case', 'exclude');

                                if (! $this->option('all')) {
                                    return $this->report($rows, $found, $client);
                                }
                            }

                            // The gateway is somebody else's service; a probe should
                            // not read as a flood. Nothing to pace against a fake.
                            if (! app()->environment('testing')) {
                                usleep(300_000);
                            }
                        }
                    }
                }
            }
        }

        return $this->report($rows, $found, $client);
    }

    /**
     * @param  array<int, array<int, string>>  $rows
     * @param  array<int, array<string, string>>  $found
     */
    private function report(array $rows, array $found, ?JawwalPayClient $client = null): int
    {
        $this->newLine();
        $this->table(['sort', 'algo', 'mode', 'case', 'signs', 'gateway answer'], $rows);

        if ($found === []) {
            $this->newLine();
            $this->components->error('No shape was accepted.');
            $this->line('  Every combination was refused, which points past the shape itself:');
            $this->line('   · the secret may be the wrong one for this environment (sandbox keys');
            $this->line('     do not work in production);');
            $this->line('   · they may sign a fixed field order rather than a sorted one, or');
            $this->line('     include fields we drop when empty.');
            $this->line('  Send Ahmad Amro exactly this, and ask which half differs on their side:');

            if ($client) {
                // get_balance carries no customer data — msgId and lang only —
                // so this is safe to print and to paste into an email.
                $body = $client->body(['msgId' => '11112222333344']);

                $this->newLine();
                $this->line('    request   : ' . json_encode(
                    array_diff_key($body, ['secureHash' => null]),
                    JSON_UNESCAPED_UNICODE,
                ));
                $this->line('    signed    : ' . $client->secureHash()->canonicalize($body));
                $this->line('    secureHash: ' . $body['secureHash']);
            }

            return self::FAILURE;
        }

        $first = $found[0];

        $this->newLine();
        $this->components->info('Accepted: sort=' . $first['sort'] . ' algo=' . $first['algo']
            . ' mode=' . $first['mode'] . ' case=' . $first['case']
            . ($first['exclude'] === '' ? '' : ' (not signing: ' . $first['exclude'] . ')'));
        $this->line('  Pin it in .env, then `php artisan config:clear`:');
        $this->newLine();
        $this->line('    JAWWALPAY_HASH_SORT=' . $first['sort']);
        $this->line('    JAWWALPAY_HASH_ALGO=' . $first['algo']);
        $this->line('    JAWWALPAY_HASH_MODE=' . $first['mode']);
        $this->line('    JAWWALPAY_HASH_CASE=' . $first['case']);
        $this->line('    JAWWALPAY_HASH_EXCLUDE=' . $first['exclude']);
        $this->newLine();
        $this->line('  Then: php artisan jawwalpay:check');

        return self::SUCCESS;
    }
}
