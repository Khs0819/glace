<?php

use App\Filament\Resources\CashierShiftResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\DriverPayoutResource;
use App\Filament\Resources\PaymentAccountResource;
use App\Filament\Resources\UserResource;
use App\Models\CashierShift;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverPayout;
use App\Models\User;
use App\Services\Storefront\WalletService;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

/**
 * Two kinds of dashboard account, a way back in when a password is forgotten,
 * and the two lists the shop could not find: driver payout receipts and
 * customers holding a balance.
 */

// ─── manager and accountant ─────────────────────────────────────────────────

it('keeps payment accounts, staff and shift deletion to the manager', function () {
    $accountant = User::factory()->accountant()->create();
    $shift = CashierShift::create(['user_id' => $accountant->id, 'opened_at' => now(), 'opening_float' => 0]);

    $this->actingAs($accountant);

    $this->get(PaymentAccountResource::getUrl('index'))->assertForbidden();
    $this->get(UserResource::getUrl('index'))->assertForbidden();

    expect(CashierShiftResource::canDelete($shift))->toBeFalse();
});

it('lets the manager into all of it', function () {
    $manager = User::factory()->create();
    $shift = CashierShift::create(['user_id' => $manager->id, 'opened_at' => now(), 'opening_float' => 0]);

    $this->actingAs($manager);

    $this->get(PaymentAccountResource::getUrl('index'))->assertSuccessful();
    $this->get(UserResource::getUrl('index'))->assertSuccessful();

    expect(CashierShiftResource::canDelete($shift))->toBeTrue();
});

it('lets the manager create an accountant account', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(UserResource\Pages\CreateUser::class)
        ->fillForm([
            'name'     => 'محاسب المساء',
            'email'    => 'evening@glace.test',
            'role'     => User::ROLE_ACCOUNTANT,
            'password' => 'counter-2026',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'evening@glace.test')->sole();

    expect($user->isAccountant())->toBeTrue()
        ->and(Hash::check('counter-2026', $user->password))->toBeTrue();
});

it('will not let the last manager be demoted', function () {
    $manager = User::factory()->create();
    $this->actingAs($manager);

    Livewire::test(UserResource\Pages\EditUser::class, ['record' => $manager->getKey()])
        ->fillForm(['role' => User::ROLE_ACCOUNTANT])
        ->call('save')
        ->assertHasFormErrors(['role']);

    expect($manager->fresh()->isManager())->toBeTrue();
});

it('keeps the old password when a user is edited without a new one', function () {
    $this->actingAs(User::factory()->create());
    $accountant = User::factory()->accountant()->create(['password' => 'original-pass']);

    Livewire::test(UserResource\Pages\EditUser::class, ['record' => $accountant->getKey()])
        ->fillForm(['name' => 'اسم جديد', 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(Hash::check('original-pass', $accountant->fresh()->password))->toBeTrue();
});

// ─── a forgotten password ───────────────────────────────────────────────────

it('resets a forgotten password from the server', function () {
    $user = User::factory()->create(['email' => 'owner@glace.test']);

    $this->artisan('user:password', ['email' => 'owner@glace.test', '--password' => 'brand-new-pass'])
        ->assertSuccessful();

    expect(Hash::check('brand-new-pass', $user->fresh()->password))->toBeTrue();
});

it('generates a password when none is given, and prints it once', function () {
    User::factory()->create(['email' => 'owner@glace.test']);

    $this->artisan('user:password', ['email' => 'owner@glace.test', '--no-interaction' => true])
        ->expectsOutputToContain('كلمة السر:')
        ->assertSuccessful();
});

it('lists who can sign in when no email is given', function () {
    User::factory()->create(['email' => 'owner@glace.test']);

    $this->artisan('user:password')->expectsOutputToContain('owner@glace.test')->assertSuccessful();
});

it('says so rather than guessing when the email is wrong', function () {
    $this->artisan('user:password', ['email' => 'nobody@glace.test', '--password' => 'whatever-123'])->assertFailed();
});

// ─── driver payout receipts ─────────────────────────────────────────────────

it('keeps every driver payout and its receipt in a list', function () {
    fakePublicDisk();
    $this->actingAs(User::factory()->accountant()->create());

    $driver = Driver::create(['name' => 'محمود', 'phone' => '0599876543']);
    $payout = DriverPayout::create([
        'driver_id' => $driver->id, 'amount' => 70, 'receipt' => 'driver-payouts/slip.png', 'paid_at' => now(),
    ]);

    Livewire::test(DriverPayoutResource\Pages\ListDriverPayouts::class)
        ->assertCanSeeTableRecords([$payout])
        ->assertSee('محمود');

    $this->get(DriverPayoutResource::getUrl('view', ['record' => $payout]))->assertSuccessful();
});

// ─── customers with a balance ───────────────────────────────────────────────

it('filters the customers down to those holding a balance', function () {
    $this->actingAs(User::factory()->create());

    $owed  = Customer::create(['name' => 'له رصيد', 'phone' => '0599000001']);
    $empty = Customer::create(['name' => 'بلا رصيد', 'phone' => '0599000002']);

    app(WalletService::class)->credit($owed, 2500, 'شحن');

    Livewire::test(CustomerResource\Pages\ListCustomers::class)
        ->filterTable('has_balance')
        ->assertCanSeeTableRecords([$owed])
        ->assertCanNotSeeTableRecords([$empty]);
});
