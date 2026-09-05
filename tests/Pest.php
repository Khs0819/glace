<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(Tests\TestCase::class)
    ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Fake the public disk without losing its `url` setting.
 *
 * A bare Storage::fake('public') drops the configured url, so
 * Storage::disk('public')->url() would return a relative path and upload tests
 * could not verify the absolute-URL contract the handoff requires.
 */
function fakePublicDisk(): void
{
    Illuminate\Support\Facades\Storage::fake('public', [
        'url' => rtrim(config('app.url'), '/') . '/storage',
    ]);
}

/*
|--------------------------------------------------------------------------
| Jawwal Pay
|--------------------------------------------------------------------------
|
| A stand-in for the Service Bus. Never point tests at apitest.jawwalpay.ps:
| every call there is a real call against the merchant's sandbox account.
|
*/

const JAWWAL_BASE = 'https://apitest.jawwalpay.test';

/** A token shaped like theirs: a JWT whose exp decides how long we cache it. */
function jawwalToken(int $lifetime = 7200): string
{
    $segment = fn (array $claims) => rtrim(strtr(base64_encode(json_encode($claims)), '+/', '-_'), '=');

    return $segment(['alg' => 'HS256'])
        . '.' . $segment(['sub' => 'admin@selco', 'iat' => time(), 'exp' => time() + $lifetime])
        . '.signature';
}

/** @return array<string, mixed> */
function jawwalLoginOk(int $lifetime = 7200): array
{
    return [
        'errorCd'    => '00',
        'desc'       => 'Success',
        'extraData'  => [['key' => 'access_token', 'value' => jawwalToken($lifetime)]],
        'ref'        => '123456789',
        'statusCode' => '1',
    ];
}

/** @return array<string, mixed> */
function jawwalEnvelope(string $errorCd = '00', array $extraData = []): array
{
    return [
        'errorCd'    => $errorCd,
        'desc'       => $errorCd === '00' ? 'Success' : App\Services\JawwalPay\ErrorCode::label($errorCd),
        'extraData'  => $extraData,
        'ref'        => '2921218715102',
        'statusCode' => $errorCd === '00' ? '1' : '3',
    ];
}

/**
 * Configure the container's gateway client against the fake host, with /login
 * already answered. Pass endpoint => response for the calls under test.
 *
 * @param  array<string, mixed>  $endpoints
 */
function fakeJawwalPay(array $endpoints = []): void
{
    config(['services.jawwalpay' => [
        'base_url' => JAWWAL_BASE,
        'username' => 'admin@selco',
        'password' => 'secret',
        'secret'   => 'hmac-secret',
        'lang'     => 'AR',
        'log'      => false,
    ]]);

    // The client is a singleton that reads config once, at resolve time.
    app()->forgetInstance(App\Services\JawwalPay\JawwalPayClient::class);

    Illuminate\Support\Facades\Http::fake(array_merge([
        JAWWAL_BASE . '/login' => Illuminate\Support\Facades\Http::response(jawwalLoginOk()),
    ], $endpoints));

    // An endpoint nobody faked should blow up loudly, not quietly answer 200
    // and surface three layers later as a malformed envelope.
    Illuminate\Support\Facades\Http::preventStrayRequests();
}
