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
 * The two screens the counter account does not open at all.
 *
 * Short on purpose. The line the shop drew is deletion, money and control of
 * the system — not "anything a manager might care about", which only sends the
 * counter looking for a manager mid-shift. These two are where the customers'
 * money is sent, and who may sign in at all.
 */
dataset('manager only', [
    'payment accounts' => [App\Filament\Resources\PaymentAccountResource::class],
    'staff'            => [App\Filament\Resources\UserResource::class],
]);

/** Screens the counter reads all day — and does not rewrite. */
dataset('read only for the counter', [
    'products'     => [App\Filament\Resources\ProductResource::class],
    'categories'   => [App\Filament\Resources\MenuCategoryResource::class],
    'flavors'      => [App\Filament\Resources\FlavorResource::class],
    'addons'       => [App\Filament\Resources\GlobalAddonResource::class],
    'zones'        => [App\Filament\Resources\DeliveryZoneResource::class],
    // Answering "is that code still good?" at the till beats fetching a
    // manager; publishing a new one is a different act.
    'coupons'      => [App\Filament\Resources\CouponResource::class],
    'branches'     => [App\Filament\Resources\BranchResource::class],
    'hero slides'  => [App\Filament\Resources\HeroSlideResource::class],
    'site content' => [App\Filament\Resources\SiteContentResource::class],
    'events'       => [App\Filament\Resources\EventResource::class],
    'faqs'         => [App\Filament\Resources\FaqResource::class],
    'contacts'     => [App\Filament\Resources\ContactResource::class],
    'home about'   => [App\Filament\Resources\HomeAboutResource::class],
    'home why'     => [App\Filament\Resources\HomeWhyGlaceResource::class],
]);

/** Screens the counter needs to get through a shift. */
dataset('the counter works here', [
    'orders'    => [App\Filament\Resources\OrderResource::class],
    'refunds'   => [App\Filament\Resources\ChangeRefundRequestResource::class],
    'top-ups'   => [App\Filament\Resources\TopUpRequestResource::class],
    'customers' => [App\Filament\Resources\CustomerResource::class],
    'payouts'   => [App\Filament\Resources\DriverPayoutResource::class],
    'shifts'    => [App\Filament\Resources\CashierShiftResource::class],
    'drivers'   => [App\Filament\Resources\DriverResource::class],
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

it('lets the counter add a driver, and only the manager remove one', function () {
    $driver = Driver::create(['name' => 'محمود', 'phone' => '0599876543']);

    // A driver turning up at eleven at night is the counter's problem to solve,
    // and there is nobody else there to solve it.
    $this->actingAs(User::factory()->accountant()->create());

    expect(App\Filament\Resources\DriverResource::canCreate())->toBeTrue()
        ->and(App\Filament\Resources\DriverResource::canEdit($driver))->toBeTrue()
        // His name is frozen onto every order he carried and every fee he was
        // paid; removing the row is a question about records.
        ->and(App\Filament\Resources\DriverResource::canDelete($driver))->toBeFalse();

    $this->actingAs(User::factory()->create());

    expect(App\Filament\Resources\DriverResource::canDelete($driver))->toBeTrue();
});

it('lets the counter open and close its own shift from the till screen', function () {
    $cashier = User::factory()->accountant()->create();
    $this->actingAs($cashier);

    // The shifts page is the archive; the shift itself is opened where the
    // drawer is. Both actions are the counter's, and neither asks a manager.
    Livewire::test(App\Filament\Pages\CashierBoard::class)
        ->callAction('openShift', ['opening_float' => 100])
        ->assertHasNoActionErrors();

    $shift = CashierShift::sole();

    expect($shift->user_id)->toBe($cashier->id)
        ->and($shift->closed_at)->toBeNull();

    Livewire::test(App\Filament\Pages\CashierBoard::class)
        ->callAction('closeShift', ['counted_cash' => 100])
        ->assertHasNoActionErrors();

    expect($shift->fresh()->closed_at)->not->toBeNull();
});

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
