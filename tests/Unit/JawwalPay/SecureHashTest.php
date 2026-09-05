<?php

use App\Services\JawwalPay\SecureHash;

/**
 * The guide's worked example (§3) is the only ground truth we have offline, and
 * it is internally inconsistent — see the class docblock. These tests pin the
 * part that *is* verifiable (the canonical string) and the part that is our own
 * choice (determinism, what gets excluded), so a change to either is visible.
 */
it('reproduces the concatenated string printed in the guide', function () {
    $hash = new SecureHash('4Ffsk48gHgd49JvddAvNmZ');

    expect($hash->canonicalize([
        'msgId'    => '44393232930329',
        'receiver' => '00970598251590',
        'amount'   => '500',
        'lang'     => 'EN',
    ]))->toBe('0097059825159044393232930329500EN');
});

it('orders by key instead when configured to', function () {
    $hash = new SecureHash('secret', 'sha512', 'key');

    // amount · lang · msgId · receiver
    expect($hash->canonicalize([
        'msgId'    => '44393232930329',
        'receiver' => '00970598251590',
        'amount'   => '500',
        'lang'     => 'EN',
    ]))->toBe('500EN4439323293032900970598251590');
});

it('never feeds secureHash back into its own input', function () {
    $hash = new SecureHash('secret');

    expect($hash->canonicalize(['amount' => '5', 'secureHash' => 'deadbeef']))
        ->toBe($hash->canonicalize(['amount' => '5']));
});

it('drops omitted optional params so they add no empty slot', function () {
    $hash = new SecureHash('secret');

    expect($hash->canonicalize(['amount' => '5', 'note' => null, 'promoCode' => '']))
        ->toBe('5');
});

it('is a lowercase hex hmac of the canonical string', function () {
    $hash    = new SecureHash('4Ffsk48gHgd49JvddAvNmZ');
    $payload = ['msgId' => '001', 'amount' => '500', 'lang' => 'EN'];

    expect($hash->for($payload))
        ->toBe(hash_hmac('sha512', $hash->canonicalize($payload), '4Ffsk48gHgd49JvddAvNmZ'))
        ->toMatch('/^[0-9a-f]{128}$/');
});

it('changes when any signed value changes', function () {
    $hash = new SecureHash('secret');

    expect($hash->for(['amount' => '500']))->not->toBe($hash->for(['amount' => '501']));
});

/**
 * Documented, not hidden: the digest the guide prints for its own example does
 * not come out of the formula the guide describes. We tried every permutation
 * of the four values against sha512/384/256/1/md5/whirlpool, as HMAC and as a
 * plain digest with the secret prepended, appended and both. None matched.
 *
 * So `hash_algo` / `hash_sort` are config, and the sandbox has the final say.
 */
it('does not match the digest the guide prints for that example', function () {
    $hash = new SecureHash('4Ffsk48gHgd49JvddAvNmZ');

    expect($hash->for([
        'msgId'    => '44393232930329',
        'receiver' => '00970598251590',
        'amount'   => '500',
        'lang'     => 'EN',
    ]))->not->toBe(
        'de37b208e5e2e74e23fc00fc0984c0c2206fa10395c2b74aa6d5be36b379deb5'
        . '2cdb565a88363850fdd3a697c8c0ec017bc900d47ee55852ce2f3afbf1cc1040'
    );
});
