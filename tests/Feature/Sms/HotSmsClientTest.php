<?php

use App\Services\Sms\HotSmsClient;
use Illuminate\Support\Facades\Http;

/**
 * The HTTP v1 gateway. Bare-text responses, and two things that silently ruin
 * an OTP if they are wrong: the transport verb and the message type.
 */

function hotsms(array $overrides = []): HotSmsClient
{
    return new HotSmsClient(array_merge([
        'base_url' => 'https://hotsms.ps',
        'username' => 'glace',
        'password' => 'secret',
        'sender'   => 'Glace',
    ], $overrides));
}

it('sends Arabic as UTF-8, never as the GSM alphabet', function () {
    Http::fake(['*' => Http::response('1001_5e9257a242406')]);

    hotsms()->send('970599123456', 'رمزك هو 123456');

    // type 0 is GSM 03.38, which has no Arabic in it: the customer would
    // receive noise and never be able to log in.
    Http::assertSent(fn ($request) => $request['type'] === 2);
});

it('sends plain Latin text on the cheaper GSM type', function () {
    Http::fake(['*' => Http::response('1001')]);

    hotsms()->send('970599123456', 'Your code is 123456');

    // 160 characters per part instead of 70.
    Http::assertSent(fn ($request) => $request['type'] === 0);
});

it('never puts the account password in a URL', function () {
    Http::fake(['*' => Http::response('1001')]);

    hotsms()->send('970599123456', 'test');

    // The provider accepts GET, but a GET writes the password into their
    // access logs and every proxy in between.
    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && ! str_contains($request->url(), 'secret'));
});

it('returns the provider message id when one is given', function () {
    Http::fake(['*' => Http::response('1001_5e9257a242406')]);

    expect(hotsms()->send('970599123456', 'test'))->toBe('5e9257a242406');
});

it('explains a numeric rejection instead of repeating the number', function () {
    Http::fake(['*' => Http::response('10000')]);

    expect(fn () => hotsms()->send('970599123456', 'test'))
        ->toThrow(RuntimeException::class, 'غير مفوّض');
});

it('names the fix for a disabled API rather than just the fault', function () {
    Http::fake(['*' => Http::response('15000')]);

    expect(fn () => hotsms()->send('970599123456', 'test'))
        ->toThrow(RuntimeException::class, 'أدوات الأمان');
});

it('does not mistake an unknown response for success', function () {
    Http::fake(['*' => Http::response('')]);

    expect(fn () => hotsms()->send('970599123456', 'test'))
        ->toThrow(RuntimeException::class);
});

it('reads a balance that collides with an error code as a balance', function () {
    // 1000 is both a plausible balance and the code for "no credit". The
    // endpoint answers -1 when it refuses, so a non-negative number is money.
    Http::fake(['*' => Http::response('1000')]);

    expect(hotsms()->credits())->toBe(1000.0);
});

it('reports no balance when the credentials are refused', function () {
    Http::fake(['*' => Http::response('-1')]);

    expect(hotsms()->credits())->toBeNull();
});

it('knows it is not configured without a sender name', function () {
    expect(hotsms(['sender' => ''])->configured())->toBeFalse()
        ->and(hotsms()->configured())->toBeTrue();
});
