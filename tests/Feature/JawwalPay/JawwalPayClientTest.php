<?php

use App\Services\JawwalPay\ErrorCode;
use App\Services\JawwalPay\JawwalPayClient;
use App\Services\JawwalPay\JawwalPayException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

function jawwalClient(array $overrides = []): JawwalPayClient
{
    return new JawwalPayClient(array_merge([
        'base_url' => JAWWAL_BASE,
        'username' => 'admin@selco',
        'password' => 'secret',
        'secret'   => 'hmac-secret',
        'lang'     => 'AR',
        'log'      => false,
    ], $overrides));
}

// ─── session ────────────────────────────────────────────────────────────────

it('logs in with basic auth and reuses the token for later calls', function () {
    Http::fake([
        JAWWAL_BASE . '/login'                => Http::response(jawwalLoginOk()),
        JAWWAL_BASE . '/v1/business/send_otp' => Http::response(jawwalEnvelope()),
    ]);

    $client = jawwalClient();
    $client->sendOtp('0599002286', 50, '44393232930126');
    $client->sendOtp('0599002286', 50, '44393232930127');

    Http::assertSentCount(3); // one login, two sends

    Http::assertSent(fn (Request $request) => $request->url() === JAWWAL_BASE . '/login'
        && $request->hasHeader('Authorization', 'Basic ' . base64_encode('admin@selco:secret')));
});

it('caches the token only for as long as its own exp claim allows', function () {
    Http::fake([JAWWAL_BASE . '/login' => Http::response(jawwalLoginOk(lifetime: 300))]);

    Cache::shouldReceive('get')->andReturn(null);
    Cache::shouldReceive('put')->once()->withArgs(function ($key, $value, $ttl) {
        // 300s claim minus the 60s safety margin — nowhere near the ceiling.
        return str_starts_with($key, 'jawwalpay:token:') && $ttl > 230 && $ttl <= 240;
    });

    jawwalClient()->token();
});

it('caps a long lived token at the configured ceiling', function () {
    Http::fake([JAWWAL_BASE . '/login' => Http::response(jawwalLoginOk(lifetime: 86400))]);

    Cache::shouldReceive('get')->andReturn(null);
    Cache::shouldReceive('put')->once()->withArgs(fn ($key, $value, $ttl) => $ttl === 900);

    jawwalClient(['token_ttl' => 900])->token();
});

it('raises rather than guesses when the login is rejected', function () {
    Http::fake([JAWWAL_BASE . '/login' => Http::response(
        ['errorCd' => '45', 'desc' => 'Not authorized', 'ref' => '1', 'statusCode' => '3'],
        401,
    )]);

    expect(fn () => jawwalClient()->login())
        ->toThrow(JawwalPayException::class, 'فشل تسجيل الدخول');
});

it('logs in again and replays once when the session expired mid-call', function () {
    Http::fake([
        JAWWAL_BASE . '/login'    => Http::response(jawwalLoginOk()),
        JAWWAL_BASE . '/v1/business/MFP' => Http::sequence()
            ->push(jawwalEnvelope(ErrorCode::SIGN_IN_EXPIRED))
            ->push(jawwalEnvelope()),
    ]);

    $response = jawwalClient()->payWithOtp('0599002286', 50, '123456', '44393232930128');

    expect($response->successful())->toBeTrue();
    Http::assertSentCount(4); // login · MFP(91) · login · MFP(00)

    // The replay must keep the original msgId: the first attempt was rejected
    // before processing, and a fresh id would read as a second payment.
    $ids = collect(Http::recorded())
        ->map(fn ($pair) => $pair[0]->data()['msgId'] ?? null)
        ->filter()
        ->unique()
        ->values();

    expect($ids)->toHaveCount(1)->and($ids->first())->toBe('44393232930128');
});

it('does not loop forever when the session keeps coming back expired', function () {
    Http::fake([
        JAWWAL_BASE . '/login'           => Http::response(jawwalLoginOk()),
        JAWWAL_BASE . '/v1/business/MFP' => Http::response(jawwalEnvelope(ErrorCode::SIGN_IN_EXPIRED)),
    ]);

    $response = jawwalClient()->payWithOtp('0599002286', 50, '123456', '44393232930129');

    expect($response->errorCode())->toBe(ErrorCode::SIGN_IN_EXPIRED);
    Http::assertSentCount(4); // login · MFP · login · MFP — and it stops there
});

// ─── signing ────────────────────────────────────────────────────────────────

it('signs every call and sends the token in x-auth-token', function () {
    Http::fake([
        JAWWAL_BASE . '/login'                => Http::response(jawwalLoginOk()),
        JAWWAL_BASE . '/v1/business/send_otp' => Http::response(jawwalEnvelope()),
    ]);

    $client = jawwalClient();
    $client->sendOtp('0599002286', 50, '44393232930126');

    Http::assertSent(function (Request $request) use ($client) {
        if ($request->url() !== JAWWAL_BASE . '/v1/business/send_otp') {
            return false;
        }

        $body   = $request->data();
        $signed = $body;
        unset($signed['secureHash']);

        return $request->hasHeader('X-Auth-Token')
            && $body['secureHash'] === $client->secureHash()->for($signed)
            && $body['receiver'] === '00970599002286'   // normalised before signing
            && $body['amount'] === '50'
            && $body['lang'] === 'AR';
    });
});

it('sends the hashed otp and never the code the customer typed', function () {
    Http::fake([
        JAWWAL_BASE . '/login'           => Http::response(jawwalLoginOk()),
        JAWWAL_BASE . '/v1/business/MFP' => Http::response(jawwalEnvelope()),
    ]);

    jawwalClient()->payWithOtp('0599002286', 50, '123456', '44393232930128', [
        'note'    => 'طلب من الموقع',
        'ignored' => 'not a documented reference',
    ]);

    Http::assertSent(function (Request $request) {
        if ($request->url() !== JAWWAL_BASE . '/v1/business/MFP') {
            return false;
        }

        $body = $request->data();

        return $body['otp'] === JawwalPayClient::hashOtp('123456')
            && $body['otp'] !== '123456'
            && $body['note'] === 'طلب من الموقع'
            && ! array_key_exists('ignored', $body);   // undocumented keys dropped
    });
});

it('refuses a receiver the gateway would reject with code 56', function () {
    Http::fake([JAWWAL_BASE . '/login' => Http::response(jawwalLoginOk())]);

    expect(fn () => jawwalClient()->sendOtp('022960000', 50, '44393232930126'))
        ->toThrow(JawwalPayException::class, 'رقم محفظة غير صالح');

    Http::assertNothingSent();
});

// ─── qr ─────────────────────────────────────────────────────────────────────

it('marks a qr dynamic only when it carries an amount', function () {
    Http::fake([
        JAWWAL_BASE . '/login' => Http::response(jawwalLoginOk()),
        JAWWAL_BASE . '/v1/business/generate_qr' => Http::response(
            jawwalEnvelope('00', [['key' => 'qr', 'value' => '0002010102122622']]),
        ),
    ]);

    $client = jawwalClient();
    $client->generateQr(100);
    $client->generateQr();

    $bodies = collect(Http::recorded())
        ->map(fn ($pair) => $pair[0])
        ->filter(fn (Request $request) => $request->url() === JAWWAL_BASE . '/v1/business/generate_qr')
        ->map(fn (Request $request) => $request->data())
        ->values();

    expect($bodies[0]['initiationMethod'])->toBe(JawwalPayClient::QR_DYNAMIC)
        ->and($bodies[0]['transactionAmount'])->toBe('100')
        ->and($bodies[1]['initiationMethod'])->toBe(JawwalPayClient::QR_STATIC)
        ->and($bodies[1])->not->toHaveKey('transactionAmount');
});

// ─── failure modes ──────────────────────────────────────────────────────────

it('returns a business rejection instead of throwing, so it can be recorded', function () {
    Http::fake([
        JAWWAL_BASE . '/login'           => Http::response(jawwalLoginOk()),
        JAWWAL_BASE . '/v1/business/MFP' => Http::response(jawwalEnvelope(ErrorCode::INVALID_OTP)),
    ]);

    $response = jawwalClient()->payWithOtp('0599002286', 50, '000000', '44393232930128');

    expect($response->failed())->toBeTrue()
        ->and($response->errorCode())->toBe('89')
        ->and($response->message())->toBe(ErrorCode::message('89'))
        ->and($response->reference())->toBe('2921218715102');
});

it('throws when the gateway cannot be reached at all', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timeout'));

    expect(fn () => jawwalClient()->login())
        ->toThrow(JawwalPayException::class, 'تعذّر الاتصال بخدمة جوال باي');
});

it('throws on a server error that carries no envelope', function () {
    Http::fake([JAWWAL_BASE . '/login' => Http::response('<html>502 Bad Gateway</html>', 502)]);

    expect(fn () => jawwalClient()->login())->toThrow(JawwalPayException::class, '502');
});

it('throws on a 200 whose body is not the envelope', function () {
    Http::fake([JAWWAL_BASE . '/login' => Http::response('OK', 200)]);

    expect(fn () => jawwalClient()->login())
        ->toThrow(JawwalPayException::class, 'رد غير مفهوم');
});

it('names the missing settings instead of failing obscurely', function () {
    $client = jawwalClient(['username' => null, 'secret' => '']);

    expect($client->configured())->toBeFalse()
        ->and($client->missingConfig())->toBe(['username', 'secret'])
        ->and(fn () => $client->balance())
        ->toThrow(JawwalPayException::class, 'username, secret');
});

it('knows when it is still pointed at the sandbox', function () {
    expect(jawwalClient(['base_url' => 'https://apitest.jawwalpay.ps'])->sandbox())->toBeTrue()
        ->and(jawwalClient(['base_url' => 'https://api.jawwalpay.ps'])->sandbox())->toBeFalse();
});
