<?php

use App\Models\PaymentAccount;

/**
 * The account a customer transfers into.
 *
 * Entered on its own field in the dashboard, and until now never sent: the
 * storefront showed a bank's phone number and IBAN but not its account number.
 */

beforeEach(fn () => fakePublicDisk());

it('returns the account number with the payment account', function () {
    PaymentAccount::create([
        'method'          => 'bop',
        'holder_name'     => 'محل جلاسيه الأمير',
        'bank_name'       => 'بنك فلسطين',
        'account_number'  => '1234-5678-9012',
        'primary_label'   => 'رقم الجوال',
        'primary_value'   => '0599999978',
        'secondary_label' => 'IBAN',
        'secondary_value' => 'PS00PALS000000000000123456789',
        'active'          => true,
    ]);

    test()->getJson('/api/payment-accounts')
        ->assertOk()
        ->assertJsonPath('0.accountNumber', '1234-5678-9012')
        ->assertJsonPath('0.primaryValue', '0599999978');
});

it('leaves the account number out when the dashboard field is empty', function () {
    PaymentAccount::create([
        'method'        => 'jawwal-manual',
        'holder_name'   => 'جلاسيه الأمير',
        'primary_label' => 'رقم جوال باي',
        'primary_value' => '0599123456',
        'active'        => true,
    ]);

    // Empty fields are omitted rather than sent as blank strings.
    expect(test()->getJson('/api/payment-accounts')->json('0'))->not->toHaveKey('accountNumber');
});
