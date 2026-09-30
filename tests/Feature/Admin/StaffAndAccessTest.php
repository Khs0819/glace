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

// ─── manager and counter ────────────────────────────────────────────────────

/**
 * Every screen the counter account must not open.
 *
 * Grouped by what a mis-tap on each would cost, because that is the reason any
 * of them is on the list:
 *
 *   money     — where customers transfer to, what the shop gives away, and the
 *               figures for any period anyone cares to ask about
 *   the shop  — the menu, its prices, the zones, the drivers
 *   the world — what the website says, and who can sign in at all
 */
dataset('manager only', [
    'payment accounts' => [App\Filament\Resources\PaymentAccountResource::class],
    'staff'            => [App\Filament\Resources\UserResource::class],
    'coupons'          => [App\Filament\Resources\CouponResource::class],
    'branches'         => [App\Filament\Resources\BranchResource::class],
    'hero slides'      => [App\Filament\Resources\HeroSlideResource::class],
    'site content'     => [App\Filament\Resources\SiteContentResource::class],
    'events'           => [App\Filament\Resources\EventResource::class],
    'faqs'             => [App\Filament\Resources\FaqResource::class],
    'contacts'         => [App\Filament\Resources\ContactResource::class],
    'home about'       => [App\Filament\Resources\HomeAboutResource::class],
    'home why'         => [App\Filament\Resources\HomeWhyGlaceResource::class],
]);

/** Screens the counter reads all day but does not rewrite. */
dataset('read only for the counter', [
    'products'   => [App\Filament\Resources\ProductResource::class],
    'categories' => [App\Filament\Resources\MenuCategoryResource::class],
    'flavors'    => [App\Filament\Resources\FlavorResource::class],
    'addons'     => [App\Filament\Resources\GlobalAddonResource::class],
    'zones'      => [App\Filament\Resources\DeliveryZoneResource::class],
    'drivers'    => [App\Filament\Resources\DriverResource::class],
]);

/** Screens the counter needs to get through a shift. */
dataset('the counter works here', [
    'orders'   => [App\Filament\Resources\OrderResource::class],
    'refunds'  => [App\Filament\Resources\ChangeRefundRequestResource::class],
    'top-ups'  => [App\Filament\Resources\TopUpRequestResource::class],
    'customers' => [App\Filament\Resources\CustomerResource::class],
    'payouts'  => [App\Filament\Resources\DriverPayoutResource::class],
    'shifts'   => [App\Filament\Resources\CashierShiftResource::class],
]);

it('closes the sensitive screens to the counter account', function (string $resource) {
    $this->actingAs(User::factory()->accountant()->create());

    // Not merely refused when clicked — gone from the sidebar, so the counter
    // never sees a door it cannot open.
    expect($resource::canViewAny())->toBeFalse();

    $this->get($resource::getUrl('index'))->assertForbidden();
})->with('manager only');

it('opens every screen to the manager', function (string $resource) {
    $this->actingAs(User::factory()->create());

    expect($resource::canViewAny())->toBeTrue();

    $this->get($resource::getUrl('index'))->assertSuccessful();
})->with('manager only');

it('lets the counter read the menu without rewriting it', function (string $resource) {
    $this->actingAs(User::factory()->accountant()->create());

    expect($resource::canViewAny())->toBeTrue()
        ->and($resource::canCreate())->toBeFalse();

    $this->get($resource::getUrl('index'))->assertSuccessful();
})->with('read only for the counter');

it('leaves the counter the screens a shift is run from', function (string $resource) {
    $this->actingAs(User::factory()->accountant()->create());

    expect($resource::canViewAny())->toBeTrue();

    $this->get($resource::getUrl('index'))->assertSuccessful();
})->with('the counter works here');

it('keeps shift deletion and the financial reports to the manager', function () {
    $accountant = User::factory()->accountant()->create();
    $shift = CashierShift::create(['user_id' => $accountant->id, 'opened_at' => now(), 'opening_float' => 0]);

    $this->actingAs($accountant);

    expect(CashierShiftResource::canDelete($shift))->toBeFalse()
        ->and(App\Filament\Pages\FinancialReports::canAccess())->toBeFalse();

    $this->actingAs(User::factory()->create());

    expect(CashierShiftResource::canDelete($shift))->toBeTrue()
        ->and(App\Filament\Pages\FinancialReports::canAccess())->toBeTrue();
});

it('shows a cashier their own drawer counts and nobody else\'s', function () {
    $mine   = User::factory()->accountant()->create();
    $theirs = User::factory()->accountant()->create();

    $ownShift   = CashierShift::create(['user_id' => $mine->id, 'opened_at' => now(), 'opening_float' => 0]);
    $otherShift = CashierShift::create(['user_id' => $theirs->id, 'opened_at' => now(), 'opening_float' => 0]);

    $this->actingAs($mine);

    // A shift row is a cash count, and it is one cashier's record.
    Livewire::test(CashierShiftResource\Pages\ListCashierShifts::class)
        ->assertCanSeeTableRecords([$ownShift])
        ->assertCanNotSeeTableRecords([$otherShift]);

    $this->actingAs(User::factory()->create());

    Livewire::test(CashierShiftResource\Pages\ListCashierShifts::class)
        ->assertCanSeeTableRecords([$ownShift, $otherShift]);
});

it('will not let the counter hand out wallet credit by hand', function () {
    $customer = Customer::create(['name' => 'زبون', 'phone' => '0599000003']);

    // The one button in the dashboard that makes money with no order, no
    // receipt and no transfer behind it.
    $this->actingAs(User::factory()->accountant()->create());

    Livewire::test(CustomerResource\Pages\ListCustomers::class)
        ->assertTableActionHidden('adjustWallet', $customer);

    $this->actingAs(User::factory()->create());

    Livewire::test(CustomerResource\Pages\ListCustomers::class)
        ->assertTableActionVisible('adjustWallet', $customer);
});

it('leaves the settings page open so anyone can change their own password', function () {
    $this->actingAs(User::factory()->accountant()->create());

    // The page stays reachable — it is where a cashier changes their own
    // password — while the shop switches on it stay the manager's, which
    // StoreHoursTest covers by trying to flip one.
    expect(App\Filament\Pages\StoreSettings::canAccess())->toBeTrue();

    $this->get(App\Filament\Pages\StoreSettings::getUrl())->assertSuccessful();
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
