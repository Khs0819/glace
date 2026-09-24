<?php

/*
|--------------------------------------------------------------------------
| Jawwal Pay secureHash probe — runs on whatever is already deployed
|--------------------------------------------------------------------------
|
| The artisan command `jawwalpay:probe` does this properly, but it only exists
| once this branch is deployed. This file needs nothing but the code already on
| the server: it signs get_balance itself, every plausible way, and reports the
| shape the gateway accepts.
|
| On the server:
|
|   1. paste this file to /tmp/jp-probe.php
|   2. php artisan tinker /tmp/jp-probe.php
|
| get_balance is signed and read-only. Nothing here can move money.
|
*/

use Illuminate\Support\Facades\Http;

$config = config('services.jawwalpay');
$client = app(App\Services\JawwalPay\JawwalPayClient::class);
$base   = rtrim((string) $config['base_url'], '/');
$secret = (string) $config['secret'];
$lang   = (string) ($config['lang'] ?? 'AR');

echo 'host: ', $base, PHP_EOL;

$token = $client->token();   // one session for the whole run
echo 'login: ok', PHP_EOL, PHP_EOL;

/** The string that gets hashed, under one ordering. */
$canonical = function (array $payload, string $sort): string {
    $values = [];

    foreach ($payload as $key => $value) {
        if ($key === 'secureHash' || $value === null || $value === '') {
            continue;
        }

        $values[$key] = (string) $value;
    }

    $sort === 'key' ? ksort($values, SORT_STRING) : sort($values, SORT_STRING);

    return implode('', $values);
};

$accepted = null;

// fields signed × ordering × digest × where the secret goes × hex case
foreach ([['msgId', 'lang'], ['msgId']] as $fields) {
    foreach (['value', 'key'] as $sort) {
        foreach (['sha512', 'sha256', 'sha1', 'md5'] as $algo) {
            foreach (['hmac', 'append', 'prepend'] as $mode) {
                foreach (['lower', 'upper'] as $case) {
                    $body = [
                        'msgId' => (string) random_int(10000000000000, 99999999999999),
                        'lang'  => $lang,
                    ];

                    $string = $canonical(array_intersect_key($body, array_flip($fields)), $sort);

                    $digest = match ($mode) {
                        'append'  => hash($algo, $string . $secret),
                        'prepend' => hash($algo, $secret . $string),
                        default   => hash_hmac($algo, $string, $secret),
                    };

                    $body['secureHash'] = $case === 'upper' ? strtoupper($digest) : $digest;

                    $response = Http::acceptJson()->asJson()->timeout(30)
                        ->withHeaders(['X-Auth-Token' => $token])
                        ->post($base . '/v1/get_balance', $body);

                    $code = (string) $response->json('errorCd');
                    $shape = sprintf(
                        'signs=%-11s sort=%-5s algo=%-6s mode=%-7s case=%s',
                        implode('+', $fields), $sort, $algo, $mode, $case,
                    );

                    echo $shape, '  →  ', $code, ' ', (string) $response->json('desc'), PHP_EOL;

                    if ($code === '00') {
                        $accepted = [$fields, $sort, $algo, $mode, $case, $string, $body['secureHash']];

                        break 5;
                    }

                    usleep(300000);
                }
            }
        }
    }
}

echo PHP_EOL;

if ($accepted === null) {
    echo 'No shape was accepted.', PHP_EOL;
    echo 'Send Ahmad Amro one refused attempt — the request, the string signed and', PHP_EOL;
    echo 'the hash — and ask which of the two differs on their side. The other', PHP_EOL;
    echo 'possibility is that this secret is not the production one.', PHP_EOL;

    return;
}

[$fields, $sort, $algo, $mode, $case, $string, $hash] = $accepted;

echo 'ACCEPTED.', PHP_EOL;
echo '  signed string: ', $string, PHP_EOL;
echo '  secureHash   : ', $hash, PHP_EOL, PHP_EOL;
echo 'Put these in .env, then `php artisan config:clear`:', PHP_EOL, PHP_EOL;
echo '  JAWWALPAY_HASH_SORT=', $sort, PHP_EOL;
echo '  JAWWALPAY_HASH_ALGO=', $algo, PHP_EOL;
echo '  JAWWALPAY_HASH_MODE=', $mode, PHP_EOL;
echo '  JAWWALPAY_HASH_CASE=', $case, PHP_EOL;
echo '  JAWWALPAY_HASH_EXCLUDE=', in_array('lang', $fields, true) ? '' : 'lang', PHP_EOL, PHP_EOL;
echo 'The last three need this branch deployed; the first two work today.', PHP_EOL;
