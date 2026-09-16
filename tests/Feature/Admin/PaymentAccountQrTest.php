<?php

use App\Filament\Resources\PaymentAccountResource;
use App\Models\Customer;
use App\Models\PaymentAccount;
use App\Models\User;
use App\Services\Auth\CustomerAuthService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

/**
 * The QR code a customer scans to pay, from the dashboard upload to the API.
 */

beforeEach(function () {
    fakePublicDisk();
    $this->actingAs(User::factory()->create());
});

function bankAccount(array $attributes = []): PaymentAccount
{
    return PaymentAccount::create(array_merge([
        'method'          => 'bop',
        'holder_name'     => 'محل جلاسيه الأمير',
        'bank_name'       => 'بنك فلسطين',
        'primary_label'   => 'رقم الجوال',
        'primary_value'   => '0599999978',
        'active'          => true,
    ], $attributes));
}

it('returns the QR image uploaded from the dashboard', function () {
    $account = bankAccount();

    Livewire::test(PaymentAccountResource\Pages\EditPaymentAccount::class, ['record' => $account->getKey()])
        ->fillForm([
            'qr_image'       => UploadedFile::fake()->image('qr.png', 300, 300),
            'account_number' => '1234-5678-9012',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $account = $account->fresh();

    expect($account->qr_image)->toStartWith('payment-accounts/');
    Storage::disk('public')->assertExists($account->qr_image);

    test()->getJson('/api/payment-accounts')
        ->assertOk()
        ->assertJsonPath('0.qrImage', Storage::disk('public')->url($account->qr_image))
        ->assertJsonPath('0.accountNumber', '1234-5678-9012');
});

it('does not send a QR link whose file is gone', function () {
    // The row still names a file, but the file was lost: a customer must not be
    // shown a code that leads nowhere.
    bankAccount(['qr_image' => 'payment-accounts/lost.png']);

    expect(test()->getJson('/api/payment-accounts')->json('0'))->not->toHaveKey('qrImage');
});

it('reports accounts without a working QR image', function () {
    bankAccount();

    $this->artisan('payment-accounts:check')
        ->expectsOutputToContain('NOT UPLOADED')
        ->assertExitCode(1);
});

it('reports every active transfer account as ready once its QR is uploaded', function () {
    Storage::disk('public')->put('payment-accounts/qr.png', 'png');
    bankAccount(['qr_image' => 'payment-accounts/qr.png', 'account_number' => '123']);

    // PHP's own upload limit is part of the report and may be low on this
    // machine, so the account row is asserted rather than the exit code: the
    // last column carries a URL only when the storefront would receive one.
    $this->artisan('payment-accounts:check')
        ->expectsOutputToContain('/storage/payment-accounts/qr.png')
        ->expectsOutputToContain('Every active transfer account has a working QR image');
});

// ─── the old "paypal" name ──────────────────────────────────────────────────

it('accepts "paypal" as PalPay for a wallet top-up', function () {
    $customer = Customer::create(['name' => 'أحمد', 'phone' => '0599123456']);
    $headers  = ['Authorization' => app(CustomerAuthService::class)->issueToken($customer)];

    test()->postJson('/api/wallet/topup-requests', [
        'amount'            => 50,
        'method'            => 'paypal',
        'receiptNote'       => 'حوّلت',
        'senderAccountName' => 'أحمد علي',
    ], $headers)->assertCreated();

    expect($customer->topUpRequests()->sole()->method)->toBe('palpay');
});
