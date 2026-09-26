<?php

/**
 * Which signing key the server is using — the question production's 1004
 * turns on, since login proves the username and password are already right.
 */

function jawwalSecret(string $secret, string $baseUrl = 'https://api.jawwalpay.ps'): void
{
    config(['services.jawwalpay' => [
        'base_url' => $baseUrl,
        'username' => 'Admin@gz10781',
        'password' => 'secret',
        'secret'   => $secret,
    ]]);
}

it('names the sandbox key for what it is', function () {
    jawwalSecret('QWER12345QWER12345');

    $this->artisan('jawwalpay:secret')
        ->expectsOutputToContain('مفتاح بيئة التجربة')
        ->assertFailed();
});

it('names the key printed in the guide as an example', function () {
    jawwalSecret('4Ffsk48gHgd49JvddAvNmZ');

    $this->artisan('jawwalpay:secret')
        ->expectsOutputToContain('دليل التاجر')
        ->assertFailed();
});

it('says so when the key is missing entirely', function () {
    jawwalSecret('');

    $this->artisan('jawwalpay:secret')
        ->expectsOutputToContain('فارغ')
        ->assertFailed();
});

it('warns about a key .env will mangle', function () {
    // An unquoted # starts a comment: the key arrives cut in half.
    jawwalSecret('abc#def');

    $this->artisan('jawwalpay:secret')
        ->expectsOutputToContain('#')
        ->assertFailed();
});

it('warns about stray whitespace around the key', function () {
    jawwalSecret(' abcdefghijkl ');

    $this->artisan('jawwalpay:secret')->assertFailed();
});

it('passes a key that looks like a production one, and points at the probe', function () {
    jawwalSecret('Zk93bF9wcm9kX2tleV9leGFtcGxl');

    $this->artisan('jawwalpay:secret')
        ->expectsOutputToContain('jawwalpay:probe')
        ->assertSuccessful();
});

it('never prints the key unless asked', function () {
    jawwalSecret('Zk93bF9wcm9kX2tleV9leGFtcGxl');

    $this->artisan('jawwalpay:secret')
        ->doesntExpectOutputToContain('Zk93bF9wcm9kX2tleV9leGFtcGxl')
        ->assertSuccessful();

    $this->artisan('jawwalpay:secret --show')
        ->expectsOutputToContain('Zk93bF9wcm9kX2tleV9leGFtcGxl')
        ->assertSuccessful();
});
