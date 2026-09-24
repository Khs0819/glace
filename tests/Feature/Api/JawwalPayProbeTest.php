<?php

use App\Services\JawwalPay\SecureHash;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Production refused every signed call with 1004 "Bad SecureHash" while login
 * succeeded. The shape of that hash cannot be read out of the guide, so it is
 * found by asking the gateway — which is what this command does.
 */

const BALANCE_URL = JAWWAL_BASE . '/v1/get_balance';

/** A gateway that accepts exactly one shape and refuses the rest. */
function gatewayAccepting(string $algo, string $sort, string $mode, string $case, array $exclude = []): void
{
    fakeJawwalPay([
        BALANCE_URL => function (Request $request) use ($algo, $sort, $mode, $case, $exclude) {
            $body     = $request->data();
            $expected = (new SecureHash('hmac-secret', $algo, $sort, $mode, $case, $exclude))->for($body);

            return Http::response(jawwalEnvelope(
                hash_equals($expected, (string) ($body['secureHash'] ?? '')) ? '00' : '1004',
            ));
        },
    ]);
}

it('finds the shape the gateway accepts and prints the settings for it', function () {
    gatewayAccepting('sha256', 'key', 'append', 'upper');

    $this->artisan('jawwalpay:probe --algos=sha256')
        ->expectsOutputToContain('sort=key algo=sha256 mode=append case=upper')
        ->expectsOutputToContain('JAWWALPAY_HASH_MODE=append')
        ->assertSuccessful();
});

it('finds the shape the guide describes, when that is the one', function () {
    gatewayAccepting('sha512', 'value', 'hmac', 'lower');

    $this->artisan('jawwalpay:probe --algos=sha512')
        ->expectsOutputToContain('sort=value algo=sha512 mode=hmac case=lower')
        ->assertSuccessful();
});

it('says so, rather than guessing, when nothing is accepted', function () {
    // Wrong secret: no shape can be right, and the answer is not a shape.
    gatewayAccepting('sha512', 'value', 'hmac', 'lower');
    config(['services.jawwalpay.secret' => 'a-different-secret']);

    $this->artisan('jawwalpay:probe --algos=sha512')
        ->expectsOutputToContain('No shape was accepted')
        ->assertFailed();
});

it('never asks the gateway for anything but a balance', function () {
    gatewayAccepting('sha512', 'value', 'hmac', 'lower');

    $this->artisan('jawwalpay:probe --algos=sha512');

    // Signed and read-only: a probe must not be able to move money.
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'MFP')
        || str_contains($request->url(), 'send_otp'));
});

it('stops without a session rather than blaming the signature', function () {
    fakeJawwalPay([JAWWAL_BASE . '/login' => Http::response(jawwalEnvelope('1002'), 401)]);

    $this->artisan('jawwalpay:probe')
        ->expectsOutputToContain('login — failed')
        ->assertFailed();
});

it('finds a gateway that signs the business fields and leaves lang out', function () {
    // `lang` travels in every request but need not be part of the signature —
    // a difference of one field, and every call is refused with 1004.
    gatewayAccepting('sha512', 'value', 'hmac', 'lower', ['lang']);

    $this->artisan('jawwalpay:probe --algos=sha512')
        ->expectsOutputToContain('not signing: lang')
        ->expectsOutputToContain('JAWWALPAY_HASH_EXCLUDE=lang')
        ->assertSuccessful();
});
