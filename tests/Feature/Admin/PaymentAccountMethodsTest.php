<?php

use App\Filament\Resources\PaymentAccountResource;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\User;
use Livewire\Livewire;

/**
 * The methods the shop can configure, and the line between a destination the
 * customer transfers to and a method taken at the counter.
 */

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('offers every payment method the storefront can show', function () {
    expect(array_keys(PaymentAccount::METHODS))
        ->toContain('bop', 'jawwal-manual', 'jawwal', 'palpay', 'visa', 'cash');
});

it('calls the Palestinian service by its own name', function () {
    // PalPay is not PayPal. Showing "PayPal" beside a Palestinian account
    // number tells the customer to pay through the wrong company entirely.
    expect(PaymentAccount::METHODS)->not->toHaveKey('paypal')
        ->and(PaymentAccount::METHODS['palpay'])->toBe('PalPay')
        ->and(Order::PAYMENT_METHODS)->toContain('palpay')
        ->and(Order::PAYMENT_METHODS)->not->toContain('paypal');
});

it('saves a counter method with no account number', function () {
    Livewire::test(PaymentAccountResource\Pages\CreatePaymentAccount::class)
        ->fillForm([
            'method'      => 'visa',
            'holder_name' => 'جلاسيه الأمير',
            'active'      => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    // Nothing is transferred to a card reader; demanding an account number
    // invites somebody to invent one, and an invented number is one a customer
    // could be told to pay into.
    expect(PaymentAccount::where('method', 'visa')->firstOrFail()->primary_value)->toBeNull();
});

it('still demands an account number for a real transfer destination', function () {
    Livewire::test(PaymentAccountResource\Pages\CreatePaymentAccount::class)
        ->fillForm([
            'method'      => 'bop',
            'holder_name' => 'جلاسيه الأمير',
            'active'      => true,
        ])
        ->call('create')
        ->assertHasFormErrors(['primary_value']);
});

it('knows which methods a customer actually transfers to', function () {
    $bank = PaymentAccount::create([
        'method' => 'bop', 'holder_name' => 'x',
        'primary_label' => 'رقم الحساب', 'primary_value' => '123',
    ]);

    $card = PaymentAccount::create(['method' => 'visa', 'holder_name' => 'x']);

    expect($bank->isTransferDestination())->toBeTrue()
        ->and($card->isTransferDestination())->toBeFalse();
});
