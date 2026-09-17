<?php

use App\Filament\Pages\StoreSettings;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\Storefront\StoreHours;
use Carbon\CarbonImmutable;
use Livewire\Livewire;

/**
 * Opening hours for the shop and for delivery, the manager's overrides, and
 * what the storefront is told.
 *
 * 2026-09-21 is a Monday. Times are the shop's local time (Asia/Gaza).
 */

beforeEach(function () {
    config(['storefront.timezone' => 'Asia/Gaza']);
});

function at(string $local): void
{
    test()->travelTo(CarbonImmutable::parse($local, 'Asia/Gaza'));
}

function hours(): StoreHours
{
    return app(StoreHours::class);
}

/** Every day the same period, except the days listed as closed. */
function week(string $open, string $close, array $closedDays = []): array
{
    $week = [];

    foreach (array_keys(StoreHours::DAYS) as $dow) {
        $week[$dow] = ['enabled' => ! in_array($dow, $closedDays, true), 'open' => $open, 'close' => $close];
    }

    return $week;
}

// ─── the schedule ───────────────────────────────────────────────────────────

it('stays open around the clock until hours are set, as the shop was before', function () {
    at('2026-09-21 03:00');

    expect(hours()->isStoreOpen())->toBeTrue()
        ->and(hours()->isDeliveryOpen())->toBeTrue()
        ->and(hours()->status('store')['closesAt'])->toBeNull();
});

it('opens and closes with the schedule', function () {
    // Friday (5) closed.
    hours()->saveSchedule('store', week('10:00', '23:00', closedDays: [5]));

    at('2026-09-21 12:00');
    expect(hours()->isStoreOpen())->toBeTrue()
        ->and(hours()->status('store')['closesAt'])->toStartWith('2026-09-21T23:00');

    at('2026-09-21 23:30');
    expect(hours()->isStoreOpen())->toBeFalse()
        ->and(hours()->status('store')['opensAt'])->toStartWith('2026-09-22T10:00');

    at('2026-09-25 12:00'); // Friday
    expect(hours()->isStoreOpen())->toBeFalse()
        ->and(hours()->status('store')['opensAt'])->toStartWith('2026-09-26T10:00');
});

it('keeps a period that runs past midnight open into the next morning', function () {
    hours()->saveSchedule('store', week('14:00', '02:00'));

    at('2026-09-22 01:00');
    expect(hours()->isStoreOpen())->toBeTrue()
        ->and(hours()->status('store')['closesAt'])->toStartWith('2026-09-22T02:00');

    at('2026-09-22 03:00');
    expect(hours()->isStoreOpen())->toBeFalse()
        ->and(hours()->status('store')['opensAt'])->toStartWith('2026-09-22T14:00');
});

it('reads the hours in the shop timezone, not the server UTC', function () {
    hours()->saveSchedule('store', week('10:00', '23:00'));

    // 10:30 in Gaza is 07:30 or 08:30 UTC — both before 10:00.
    at('2026-09-21 10:30');

    expect(hours()->isStoreOpen())->toBeTrue();
});

it('refuses a day switched on without its hours', function () {
    $week = week('10:00', '23:00');
    $week[1]['open'] = '';

    expect(fn () => hours()->saveSchedule('store', $week))->toThrow(InvalidArgumentException::class, 'الإثنين');
});

// ─── delivery ───────────────────────────────────────────────────────────────

it('keeps its own hours for delivery', function () {
    hours()->saveSchedule('store', week('10:00', '23:00'));
    hours()->saveSchedule('delivery', week('12:00', '22:00'));

    at('2026-09-21 11:00');

    expect(hours()->isStoreOpen())->toBeTrue()
        ->and(hours()->isDeliveryOpen())->toBeFalse()
        ->and(hours()->status('delivery')['opensAt'])->toStartWith('2026-09-21T12:00');
});

it('never offers delivery while the shop is closed', function () {
    hours()->saveSchedule('store', week('10:00', '23:00'));

    at('2026-09-21 23:30');

    expect(hours()->isDeliveryOpen())->toBeFalse()
        ->and(hours()->status('delivery')['source'])->toBe('store_closed');
});

// ─── overrides ──────────────────────────────────────────────────────────────

it('closes the shop early until the schedule would have closed it anyway', function () {
    hours()->saveSchedule('store', week('10:00', '23:00'));
    at('2026-09-21 15:00');

    hours()->setOverride('store', 'closed', hours()->overrideUntil('store', 'closed', 'schedule'));

    expect(hours()->isStoreOpen())->toBeFalse()
        ->and(hours()->status('store')['source'])->toBe('override')
        // Tomorrow's opening is still the next change.
        ->and(hours()->status('store')['opensAt'])->toStartWith('2026-09-22T10:00');

    // Next morning the override has lapsed on its own.
    at('2026-09-22 11:00');
    expect(hours()->isStoreOpen())->toBeTrue()
        ->and(hours()->status('store')['source'])->toBe('schedule');
});

it('opens outside the hours for a set time, then follows the schedule again', function () {
    hours()->saveSchedule('store', week('10:00', '23:00'));
    at('2026-09-21 23:30');

    hours()->setOverride('store', 'open', hours()->overrideUntil('store', 'open', '1'));
    expect(hours()->isStoreOpen())->toBeTrue();

    at('2026-09-22 00:31');
    expect(hours()->isStoreOpen())->toBeFalse();
});

it('keeps the shop open past closing through to the next opening when asked', function () {
    hours()->saveSchedule('store', week('10:00', '23:00'));
    at('2026-09-21 22:00');

    // Already open: "until the schedule" means skip tonight's closing.
    $until = hours()->overrideUntil('store', 'open', 'schedule');

    expect($until->toIso8601String())->toStartWith('2026-09-22T10:00');
});

it('keeps a manual override until it is cleared', function () {
    hours()->saveSchedule('store', week('10:00', '23:00'));
    at('2026-09-21 12:00');

    hours()->setOverride('store', 'closed', hours()->overrideUntil('store', 'closed', 'manual'));

    at('2026-09-25 12:00');
    expect(hours()->isStoreOpen())->toBeFalse();

    hours()->clearOverride('store');
    expect(hours()->isStoreOpen())->toBeTrue();
});

it('honours the old closed switch until a manager uses the new controls', function () {
    StoreSetting::set('store_open', '0');

    expect(hours()->isStoreOpen())->toBeFalse();

    hours()->clearOverride('store');

    expect(hours()->isStoreOpen())->toBeTrue();
});

// ─── the storefront ─────────────────────────────────────────────────────────

it('tells the storefront the state, the next change and the week', function () {
    hours()->saveSchedule('store', week('10:00', '23:00', closedDays: [5]));
    hours()->saveSchedule('delivery', week('12:00', '22:00'));
    at('2026-09-21 11:00');

    $response = test()->getJson('/api/store/status')->assertOk();

    $response->assertJsonPath('storeOpen', true)
        ->assertJsonPath('deliveryOpen', false)
        ->assertJsonPath('timezone', 'Asia/Gaza')
        ->assertJsonPath('store.source', 'schedule')
        ->assertJsonPath('delivery.opensAt', fn ($v) => str_starts_with($v, '2026-09-21T12:00'))
        ->assertJsonPath('schedule.store.0.day', 'sat')
        ->assertJsonPath('schedule.store.6.day', 'fri')
        ->assertJsonPath('schedule.store.6.enabled', false)
        ->assertJsonPath('schedule.store.6.open', null)
        ->assertJsonStructure(['closedMessage', 'deliveryClosedMessage', 'autoConfirmMinutes', 'serverTime']);
});

it('reports an override and when it ends to the storefront', function () {
    hours()->saveSchedule('store', week('10:00', '23:00'));
    at('2026-09-21 12:00');
    hours()->setOverride('store', 'closed', hours()->overrideUntil('store', 'closed', '2'));

    test()->getJson('/api/store/status')
        ->assertJsonPath('storeOpen', false)
        ->assertJsonPath('store.source', 'override')
        ->assertJsonPath('store.override.state', 'closed')
        ->assertJsonPath('store.opensAt', fn ($v) => str_starts_with($v, '2026-09-21T14:00'));
});

// ─── the dashboard ──────────────────────────────────────────────────────────

it('lets a manager set the hours and close the shop from the dashboard', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_MANAGER]));
    at('2026-09-21 12:00');

    Livewire::test(StoreSettings::class)
        ->set('schedules.store', week('10:00', '23:00'))
        ->call('saveSchedule', 'store')
        ->set('durations.store', 'manual')
        ->call('forceClose', 'store');

    expect(hours()->schedule('store')[1])->toBe(['enabled' => true, 'open' => '10:00', 'close' => '23:00'])
        ->and(hours()->isStoreOpen())->toBeFalse();
});

it('does not let anyone but a manager change the hours or the state', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_ACCOUNTANT]));

    Livewire::test(StoreSettings::class)
        ->set('schedules.store', week('10:00', '11:00'))
        ->call('saveSchedule', 'store')
        ->call('forceClose', 'store')
        ->assertOk();

    expect(hours()->schedule('store')[1]['close'])->toBe('00:00')
        ->and(hours()->isStoreOpen())->toBeTrue();
});

it('renders the settings page', function () {
    $this->actingAs(User::factory()->create(['role' => User::ROLE_MANAGER]));

    $this->get(StoreSettings::getUrl())->assertSuccessful()->assertSee('فتح استثنائي');
});
