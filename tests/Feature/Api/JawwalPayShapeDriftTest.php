<?php

use Illuminate\Support\Facades\Http;

/**
 * Settings left behind from an experiment quietly change what gets signed.
 *
 * Production had `hash_mode=append` and `hash_exclude=lang` still in .env, so
 * the run meant to test the shape Jawwal Pay described was testing "sign
 * everything but lang, and glue the key on the end" instead — and the 1004 it
 * came back with said nothing about their shape at all.
 */

function checkWith(array $overrides): void
{
    fakeJawwalPay([
        JAWWAL_BASE . '/v1/get_balance' => Http::response(jawwalEnvelope()),
    ]);

    config(['services.jawwalpay' => array_merge(config('services.jawwalpay'), $overrides)]);
}

it('names each setting that drifted from the shape the provider described', function () {
    checkWith(['hash_mode' => 'append', 'hash_exclude' => 'lang']);

    $this->artisan('jawwalpay:check')
        ->expectsOutputToContain('not the shape Jawwal Pay described')
        ->expectsOutputToContain('JAWWALPAY_HASH_MODE=hmac')
        ->expectsOutputToContain('JAWWALPAY_HASH_EXCLUDE=');
});

it('says nothing when the settings are the described shape', function () {
    checkWith([]);

    $this->artisan('jawwalpay:check')
        ->doesntExpectOutputToContain('not the shape Jawwal Pay described')
        ->assertSuccessful();
});

it('flags a signature that leaves the token out', function () {
    checkWith(['hash_include_token' => false]);

    $this->artisan('jawwalpay:check')
        ->expectsOutputToContain('JAWWALPAY_HASH_INCLUDE_TOKEN=true');
});
