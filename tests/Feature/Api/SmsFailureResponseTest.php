<?php

use App\Exceptions\SmsDeliveryException;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Http;

/**
 * What a customer sees when the SMS provider will not take the message.
 *
 * The provider refusing us is not a fault in this application, and 500 says it
 * is — misfiling the incident and telling the customer to expect a fix rather
 * than to wait or phone the shop.
 */

beforeEach(function () {
    config(['services.sms' => [
        'driver' => 'hotsms',
        'hotsms' => [
            'base_url' => 'https://hotsms.test',
            'username' => 'glace',
            'password' => 'secret',
            'sender'   => 'Glace',
        ],
    ]]);
});

it('answers 503, not 500, when the gateway refuses the message', function () {
    // 15000: API sending switched off on the account — a setting in their
    // portal, nothing here is broken.
    Http::fake(['*' => Http::response('15000')]);

    test()->postJson('/api/auth/otp/send', ['phone' => '0599123456'])
        ->assertStatus(503)
        ->assertJsonStructure(['message']);
});

it('keeps provider internals out of the response a customer sees', function () {
    Http::fake(['*' => Http::response('15000')]);

    $response = test()->postJson('/api/auth/otp/send', ['phone' => '0599123456']);

    // Account internals belong in the log, not on a customer's screen.
    expect($response->json('message'))
        ->not->toContain('15000')
        ->not->toContain('API');
});

it('answers 503 on the jawwal code path too', function () {
    Http::fake(['*' => Http::response('1000')]);

    test()->postJson('/api/orders/jawwal/send-code', ['phone' => '0599123456', 'amount' => 25])
        ->assertStatus(503);
});

it('raises a delivery failure rather than a bare runtime error', function () {
    Http::fake(fn () => throw new Illuminate\Http\Client\ConnectionException('down'));

    expect(fn () => app(SmsSender::class)->send('0599123456', 'test'))
        ->toThrow(SmsDeliveryException::class);
});
