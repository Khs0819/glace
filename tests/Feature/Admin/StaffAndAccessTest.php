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

it('keeps customer balances off the counter\'s screen entirely', function () {
    $customer = Customer::create(['name' => 'زبون', 'phone' => '0599000003']);
    app(WalletService::class)->credit($customer, 7350, 'شحن');

    $this->actingAs(User::factory()->accountant()->create());

    // Not merely un-editable: the figure is not shown, because seeing it is
    // the first step to being asked to change it.
    Livewire::test(CustomerResource\Pages\ListCustomers::class)
        ->assertCanSeeTableRecords([$customer])
        ->assertDontSee('73.5');

    $this->get(CustomerResource::getUrl('view', ['record' => $customer]))
        ->assertSuccessful()
        ->assertDontSee('73.5');

    // The manager sees it in both places.
    $this->actingAs(User::factory()->create());

    Livewire::test(CustomerResource\Pages\ListCustomers::class)->assertSee('73.5');
    $this->get(CustomerResource::getUrl('view', ['record' => $customer]))->assertSee('73.5');
});

it('lets the counter open a product to switch it off', function () {
    $product = Tests\Support\CatalogFactory::flatList('waffle', ['name' => 'وافل']);

    $this->actingAs(User::factory()->accountant()->create());

    // The product that runs out mid-evening is reached through this page —
    // the counter could not open it at all before.
    expect(App\Filament\Resources\ProductResource::canEdit($product))->toBeTrue();

    Livewire::test(App\Filament\Resources\ProductResource\Pages\EditProduct::class, ['record' => $product->getKey()])
        ->fillForm(['available' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($product->fresh()->available)->toBeFalse();
});

it('hands the counter a switch, not the price list', function () {
    $product = Tests\Support\CatalogFactory::flatList('waffle', ['name' => 'وافل', 'slug' => 'waffle']);

    $this->actingAs(User::factory()->accountant()->create());

    // A locked field on screen only invites the question of how to unlock it,
    // so the counter's form carries the switch alone.
    Livewire::test(App\Filament\Resources\ProductResource\Pages\EditProduct::class, ['record' => $product->getKey()])
        ->assertFormFieldExists('available')
        ->assertFormFieldDoesNotExist('slug')
        ->assertFormFieldDoesNotExist('category_id');

    $this->actingAs(User::factory()->create());

    Livewire::test(App\Filament\Resources\ProductResource\Pages\EditProduct::class, ['record' => $product->getKey()])
        ->assertFormFieldExists('slug');
});

it('locks the tabs inside a product against the counter', function () {
    $product = Tests\Support\CatalogFactory::flatList('waffle', ['name' => 'وافل']);
    $item    = Tests\Support\CatalogFactory::item($product, 'nutella', ['label' => 'نوتيلا', 'price' => 20]);

    $this->actingAs(User::factory()->accountant()->create());

    // Opening the product page would have opened the price grid with it:
    // a relation manager authorises itself, and with no policies registered
    // it answers yes to everyone.
    Livewire::test(App\Filament\Resources\ProductResource\RelationManagers\ItemsRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass'   => App\Filament\Resources\ProductResource\Pages\EditProduct::class,
    ])
        ->assertCanSeeTableRecords([$item])
        ->assertTableActionHidden('edit', $item)
        ->assertTableActionHidden('delete', $item)
        ->assertTableActionDoesNotExist('create');

    $this->actingAs(User::factory()->create());

    Livewire::test(App\Filament\Resources\ProductResource\RelationManagers\ItemsRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass'   => App\Filament\Resources\ProductResource\Pages\EditProduct::class,
    ])->assertTableActionVisible('edit', $item);
});

it('leaves the counter the availability toggle on an item that ran out', function () {
    $product = Tests\Support\CatalogFactory::flatList('waffle', ['name' => 'وافل']);
    $item    = Tests\Support\CatalogFactory::item($product, 'nutella', ['label' => 'نوتيلا', 'price' => 20]);

    $this->actingAs(User::factory()->accountant()->create());

    // The one edit the counter came to make. Filament does not route editable
    // columns through the relation manager's authorization, which is what
    // leaves this working while the form beside it is shut.
    Livewire::test(App\Filament\Resources\ProductResource\RelationManagers\ItemsRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass'   => App\Filament\Resources\ProductResource\Pages\EditProduct::class,
    ])->call('updateTableColumnState', 'available', (string) $item->getKey(), false);

    expect($item->fresh()->available)->toBeFalse();
});

it('lets the counter look a customer up but not rewrite one', function () {
    $customer = Customer::create(['name' => 'زبون', 'phone' => '0599000003']);

    $this->actingAs(User::factory()->accountant()->create());

    // Finding somebody at the till is the whole of what the counter needs here.
    expect(CustomerResource::canViewAny())->toBeTrue()
        // The name and the phone are frozen onto every order they placed, and
        // `blocked` decides whether they can order at all.
        ->and(CustomerResource::canEdit($customer))->toBeFalse();

    $this->get(CustomerResource::getUrl('view', ['record' => $customer]))->assertSuccessful();
    $this->get(CustomerResource::getUrl('edit', ['record' => $customer]))->assertForbidden();

    $this->actingAs(User::factory()->create());

    expect(CustomerResource::canEdit($customer))->toBeTrue();
});

it('lets the counter stop and resume orders on the website', function () {
    $this->actingAs(User::factory()->accountant()->create());

    // Eight on a Friday, the kitchen is swamped: whoever is at the till makes
    // this call, and it changes nothing that outlives the evening.
    Livewire::test(App\Filament\Pages\StoreSettings::class)
        ->call('forceClose', 'store')
        ->assertHasNoErrors();

    expect(app(App\Services\Storefront\StoreHours::class)->isStoreOpen())->toBeFalse();

    Livewire::test(App\Filament\Pages\StoreSettings::class)->call('resumeSchedule', 'store');

    expect(app(App\Services\Storefront\StoreHours::class)->status('store')['source'])->not->toBe('override');
});

it('still keeps the weekly schedule itself to the manager', function () {
    $this->actingAs(User::factory()->accountant()->create());

    // Flipping the sign on the door is one thing; rewriting when the shop
    // opens all week is another.
    Livewire::test(App\Filament\Pages\StoreSettings::class)
        ->set('schedules.store.5', ['enabled' => true, 'open' => '01:00', 'close' => '02:00'])
        ->call('saveSchedule', 'store');

    expect(app(App\Services\Storefront\StoreHours::class)->schedule('store')[5]['open'] ?? null)
        ->not->toBe('01:00');
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
