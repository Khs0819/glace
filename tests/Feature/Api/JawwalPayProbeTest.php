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
function gatewayAccepting(
    string $algo,
    string $sort,
    string $mode,
    string $case,
    array $exclude = [],
    string $layout = 'values',
    string $separator = '',
    string $encoding = 'hex',
): void {
    fakeJawwalPay([
        BALANCE_URL => function (Request $request) use ($algo, $sort, $mode, $case, $exclude, $layout, $separator, $encoding) {
            $body     = $request->data();
            $expected = (new SecureHash('hmac-secret', $algo, $sort, $mode, $case, $exclude, $layout, $separator, $encoding))->for($body);

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

it('finds a gateway that signs key=value pairs in base64', function () {
    gatewayAccepting('sha256', 'key', 'hmac', 'lower', [], 'pairs', '&', 'base64');

    $this->artisan('jawwalpay:probe --wide --algos=sha256')
        ->expectsOutputToContain('JAWWALPAY_HASH_LAYOUT=pairs')
        ->expectsOutputToContain('JAWWALPAY_HASH_SEPARATOR=&')
        ->expectsOutputToContain('JAWWALPAY_HASH_ENCODING=base64')
        ->assertSuccessful();
});

it('leaves the wider layouts alone unless asked for them', function () {
    gatewayAccepting('sha512', 'key', 'hmac', 'lower', [], 'pairs', '&');

    // The narrow run cannot find a pairs layout, and says so rather than
    // reporting a shape that was never tried.
    $this->artisan('jawwalpay:probe --algos=sha512')
        ->expectsOutputToContain('No shape was accepted')
        ->expectsOutputToContain('--wide')
        ->assertFailed();
});

it('sends one request per distinct signature, not per combination', function () {
    gatewayAccepting('sha512', 'value', 'hmac', 'lower', ['lang']);

    $this->artisan('jawwalpay:probe --wide --all');

    // 2 exclusions × 2 orderings × 2 digests × 3 modes × 2 cases × 2 layouts
    // × 3 separators × 2 encodings is 576 combinations, but a two-field
    // payload cannot produce anything like that many different strings — and
    // each repeat would be one more call to somebody else's gateway.
    $balanceCalls = collect(Http::recorded())
        ->filter(fn (array $pair) => str_contains($pair[0]->url(), 'get_balance'))
        ->count();

    expect($balanceCalls)->toBeLessThan(300)->toBeGreaterThan(10);
});
