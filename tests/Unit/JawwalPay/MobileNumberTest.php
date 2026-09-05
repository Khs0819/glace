<?php

use App\Services\JawwalPay\MobileNumber;

it('normalises every shape a customer might type', function (string $input) {
    expect(MobileNumber::normalize($input))->toBe('00970599002286');
})->with([
    '00970599002286',
    '+970599002286',
    '970599002286',
    '0599002286',
    '599002286',
    '0599-002-286',
    '  0599 002 286  ',
]);

it('keeps the 972 country code rather than rewriting it', function () {
    expect(MobileNumber::normalize('00972599002286'))->toBe('00972599002286');
});

it('rejects anything that is not a palestinian mobile line', function (?string $input) {
    expect(MobileNumber::normalize($input))->toBeNull();
})->with([
    null,
    '',
    'not a number',
    '022960000',       // landline
    '0499002286',      // subscriber does not start with 5
    '05990022',        // too short
    '05990022860',     // too long
    '00971599002286',  // wrong country
]);

it('reports validity without the caller having to normalise first', function () {
    expect(MobileNumber::valid('0599002286'))->toBeTrue()
        ->and(MobileNumber::valid('022960000'))->toBeFalse();
});

it('masks the middle of a number for logs and the dashboard', function () {
    expect(MobileNumber::mask('0599002286'))
        ->toStartWith('009705')
        ->toEndWith('2286')
        ->not->toContain('9900');
});

it('masks defensively when the number never normalised', function () {
    expect(MobileNumber::mask('123'))->toBe('••••');
});
