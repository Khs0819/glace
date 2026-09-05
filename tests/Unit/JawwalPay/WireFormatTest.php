<?php

use App\Services\JawwalPay\ErrorCode;
use App\Services\JawwalPay\JawwalPayClient;
use App\Services\JawwalPay\JawwalPayResponse;

it('hashes the otp to the 32-byte shape the guide samples', function () {
    $hashed = JawwalPayClient::hashOtp('123456');

    // The guide's sample otp is 44 base64 chars, i.e. a raw sha256 digest —
    // hex would be 64 bytes and would not be accepted.
    expect($hashed)->toHaveLength(44)
        ->and(strlen((string) base64_decode($hashed, true)))->toBe(32)
        ->and($hashed)->toBe(base64_encode(hash('sha256', '123456', true)));
});

it('ignores whitespace around an otp the customer pasted', function () {
    expect(JawwalPayClient::hashOtp(' 123456 '))->toBe(JawwalPayClient::hashOtp('123456'));
});

it('formats amounts the way the guide writes them', function (float|int|string $input, string $expected) {
    expect(JawwalPayClient::formatAmount($input))->toBe($expected);
})->with([
    [500, '500'],
    ['500', '500'],
    [500.00, '500'],
    [12.5, '12.5'],
    [12.50, '12.5'],
    [12.75, '12.75'],
    [0, '0'],
    [3.999, '4'],
]);

it('mints a unique 14 digit message id per attempt', function () {
    $ids = collect(range(1, 200))->map(fn () => JawwalPayClient::newMessageId());

    expect($ids->unique())->toHaveCount(200)
        ->and($ids->every(fn (string $id) => preg_match('/^\d{14}$/', $id) === 1))->toBeTrue();
});

it('reads the flat extraData list the envelope uses', function () {
    $response = new JawwalPayResponse([
        'errorCd'   => '00',
        'desc'      => 'Success',
        'ref'       => 2921246439222,
        'statusCode' => '1',
        'extraData' => [['key' => 'qr', 'value' => '00020101021226220008JPAYPS']],
    ]);

    expect($response->successful())->toBeTrue()
        ->and($response->extra('qr'))->toBe('00020101021226220008JPAYPS')
        ->and($response->extra('missing'))->toBeNull()
        ->and($response->reference())->toBe('2921246439222');
});

it('decodes the extraData values that carry json inside a string', function () {
    $response = new JawwalPayResponse([
        'errorCd'   => '00',
        'extraData' => [['key' => 'info', 'value' => '{"fullName":"ooo","accounts":[{"balance":835.2}]}']],
    ]);

    expect($response->extraJson('info'))->toMatchArray(['fullName' => 'ooo'])
        ->and($response->extraJson('info')['accounts'][0]['balance'])->toEqual(835.2);
});

it('survives an extraData value that is not the json it claims to be', function () {
    $response = new JawwalPayResponse([
        'errorCd'   => '00',
        'extraData' => [['key' => 'info', 'value' => 'not json']],
    ]);

    expect($response->extraJson('info'))->toBeNull();
});

it('shows the customer only what the customer can act on', function () {
    // Their wallet is empty — that is theirs to fix, so say it.
    expect(ErrorCode::customerMessage('12'))->toBe(ErrorCode::message('12'))
        ->and(ErrorCode::customerMessage('89'))->toBe(ErrorCode::message('89'));

    // Our merchant account or our wire format is broken — do not leak that.
    expect(ErrorCode::customerMessage('37'))->not->toBe(ErrorCode::message('37'))
        ->and(ErrorCode::customerMessage('91'))->not->toBe(ErrorCode::message('91'))
        ->and(ErrorCode::customerMessage('26'))->not->toBe(ErrorCode::message('26'));
});

it('has arabic wording and an english label for every documented code', function () {
    foreach (array_keys(ErrorCode::options()) as $code) {
        expect(ErrorCode::message($code))->not->toBe('حدث خطأ غير متوقع في خدمة الدفع')
            ->and(ErrorCode::label($code))->not->toBe('Unknown Error');
    }

    expect(ErrorCode::options())->toHaveCount(72)
        ->and(ErrorCode::known('00'))->toBeTrue()
        ->and(ErrorCode::known('999'))->toBeFalse();
});
