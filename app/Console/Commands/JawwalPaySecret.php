<?php

namespace App\Console\Commands;

use App\Services\JawwalPay\JawwalPayClient;
use Illuminate\Console\Command;

/**
 * What signing key this server is actually using.
 *
 * Every signed call to production is refused with 1004 while login succeeds —
 * and login is the one call that carries no signature. That splits the problem
 * cleanly: the username and password are right, and the key is not being used
 * the way the gateway expects.
 *
 * The guide (§1.1) says the apiKey is issued **per environment**, and that the
 * production one arrives only after the sandbox integration is signed off. A
 * deployment that moved its username and URL to production but kept the
 * sandbox key would behave exactly like this.
 *
 * So this prints everything about the key except the key: its length, its
 * shape, a fingerprint to compare against, and whether it is one of the values
 * we know to be a sandbox or demo key.
 */
class JawwalPaySecret extends Command
{
    protected $signature = 'jawwalpay:secret {--show : Print the key itself}';

    protected $description = 'Check which Jawwal Pay signing key is loaded, without printing it';

    /** Keys that are certainly not production. */
    private const KNOWN_TEST_KEYS = [
        'QWER12345QWER12345'     => 'مفتاح بيئة التجربة الذي سُلّم لنا',
        '4Ffsk48gHgd49JvddAvNmZ' => 'المفتاح المطبوع كمثال في دليل التاجر',
    ];

    public function handle(): int
    {
        $config = config('services.jawwalpay', []);
        $raw    = (string) ($config['secret'] ?? '');
        $client = new JawwalPayClient($config);

        $this->newLine();
        $this->components->info('Jawwal Pay — signing key');

        if ($raw === '') {
            $this->components->error('JAWWALPAY_SECRET فارغ — كل طلب موقّع سيُرفض بـ 1004.');

            return self::FAILURE;
        }

        $trimmed = trim($raw);

        $this->table(['', ''], [
            ['host', $client->baseUrl() . ($client->sandbox() ? '  (sandbox)' : '  (PRODUCTION)')],
            ['username', $config['username'] ?: '(missing)'],
            ['key length', mb_strlen($raw) . ' حرفاً'],
            ['key shape', $this->mask($raw)],
            // Enough to compare two servers, or to compare with the value in
            // the email from Jawwal Pay, without putting the key on screen.
            ['key fingerprint', substr(hash('sha256', $raw), 0, 16)],
            ['hash_algo', $config['hash_algo'] ?? 'sha512'],
            ['hash_sort', $config['hash_sort'] ?? 'value'],
        ]);

        $problems = 0;

        foreach (self::KNOWN_TEST_KEYS as $key => $what) {
            if (hash_equals($key, $trimmed)) {
                $problems++;
                $this->components->error("هذا {$what} — وليس مفتاح الإنتاج.");
                $this->line('  دليل التاجر §1.1: يُصدر مفتاح (apiKey) لكل بيئة على حدة، ومفتاح');
                $this->line('  الإنتاج يُسلَّم بعد اعتماد التكامل على بيئة التجربة. اطلب مفتاح');
                $this->line('  الإنتاج من أحمد عمرو وضعه في JAWWALPAY_SECRET.');
            }
        }

        // .env is not a shell: an unquoted # starts a comment, and a value with
        // spaces or quotes arrives as something other than what was pasted.
        if ($raw !== $trimmed) {
            $problems++;
            $this->components->warn('المفتاح يبدأ أو ينتهي بمسافة — ضعه بين علامتي اقتباس في .env.');
        }

        if (preg_match('/[\s"\'#]/', $trimmed)) {
            $problems++;
            $this->components->warn('المفتاح يحتوي مسافة أو اقتباساً أو #.');
            $this->line('  في .env تبدأ # تعليقاً، فيُقتطع ما بعدها. الصيغة الآمنة:');
            $this->line('    JAWWALPAY_SECRET="' . str_repeat('•', mb_strlen($trimmed)) . '"');
        }

        if (! $client->sandbox() && $problems === 0) {
            $this->components->info('لا شيء مريب في شكل المفتاح. إن بقي 1004، فالمشكلة في صيغة التوقيع:');
            $this->line('    php artisan jawwalpay:probe --wide');
        }

        if ($this->option('show')) {
            $this->newLine();
            $this->line('  JAWWALPAY_SECRET=' . $raw);
        } else {
            $this->line('  لعرض المفتاح نفسه: php artisan jawwalpay:secret --show');
        }

        return $problems > 0 ? self::FAILURE : self::SUCCESS;
    }

    /** First two and last two characters; the middle is nobody's business. */
    private function mask(string $secret): string
    {
        $length = mb_strlen($secret);

        if ($length <= 6) {
            return str_repeat('•', $length);
        }

        return mb_substr($secret, 0, 2) . str_repeat('•', $length - 4) . mb_substr($secret, -2);
    }
}
