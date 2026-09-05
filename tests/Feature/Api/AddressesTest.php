<?php

use App\Models\Address;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Services\Auth\CustomerAuthService;

/**
 * Saved delivery addresses (handoff 10).
 */

beforeEach(function () {
    DeliveryZone::create(['id' => 'rimal', 'name' => 'الرمال', 'description' => 'حي الرمال', 'fee' => 10]);
    DeliveryZone::create(['id' => 'shejaiya', 'name' => 'الشجاعية', 'fee' => 15]);

    $this->customer = Customer::create(['name' => 'أحمد علي', 'phone' => '0599123456']);
    $this->headers  = ['Authorization' => app(CustomerAuthService::class)->issueToken($this->customer)];
});

function addressPayload(array $overrides = []): array
{
    return array_merge([
        'type'     => 'home',
        'name'     => 'أحمد علي',
        'phone'    => '0599123456',
        'city'     => 'غزة',
        'zoneId'   => 'rimal',
        'street'   => 'شارع الجلاء',
        'landmark' => 'بجانب صيدلية النور',
        'location' => ['lat' => 31.5, 'lng' => 34.46],
    ], $overrides);
}

// ─── delivery zones ─────────────────────────────────────────────────────────

it('serves delivery zones without a token, because checkout needs them signed out', function () {
    test()->getJson('/api/addresses/delivery-zones')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.id', 'rimal')
        ->assertJsonPath('0.name', 'الرمال')
        ->assertJsonPath('0.fee', 10);
});

it('omits an absent zone description rather than sending null', function () {
    $zones = test()->getJson('/api/addresses/delivery-zones')->json();

    // The frontend's own file has description as optional, not nullable.
    expect($zones[1])->not->toHaveKey('description');
});

it('hides zones the dashboard has switched off', function () {
    DeliveryZone::whereKey('shejaiya')->update(['available' => false]);

    test()->getJson('/api/addresses/delivery-zones')->assertOk()->assertJsonCount(1);
});

// ─── creating ───────────────────────────────────────────────────────────────

it('creates an address in the saved shape the storefront reads', function () {
    test()->postJson('/api/addresses', addressPayload(), $this->headers)
        ->assertCreated()
        ->assertJsonPath('address.type', 'home')
        ->assertJsonPath('address.label', 'المنزل')
        ->assertJsonPath('address.zoneId', 'rimal')
        ->assertJsonPath('address.location.lat', 31.5)
        ->assertJsonPath('address.isDefault', true);
});

it('makes the very first address the default on its own', function () {
    test()->postJson('/api/addresses', addressPayload(), $this->headers)
        ->assertCreated()->assertJsonPath('address.isDefault', true);

    test()->postJson('/api/addresses', addressPayload(['type' => 'work']), $this->headers)
        ->assertCreated()->assertJsonPath('address.isDefault', false);
});

it('names home and work automatically but insists on a label for other', function () {
    test()->postJson('/api/addresses', addressPayload(['type' => 'work']), $this->headers)
        ->assertCreated()->assertJsonPath('address.label', 'العمل');

    test()->postJson('/api/addresses', addressPayload(['type' => 'other']), $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.label.0', 'أدخل اسماً للعنوان');

    test()->postJson('/api/addresses', addressPayload(['type' => 'other', 'label' => 'بيت أمي']), $this->headers)
        ->assertCreated()->assertJsonPath('address.label', 'بيت أمي');
});

it('refuses a phone that is not 05XXXXXXXX', function () {
    test()->postJson('/api/addresses', addressPayload(['phone' => '0412345678']), $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.phone.0', 'رقم الهاتف يجب أن يكون بصيغة 05XXXXXXXX');
});

it('stores any form of the number in the one national form', function () {
    test()->postJson('/api/addresses', addressPayload(['phone' => '+970599123456']), $this->headers)
        ->assertCreated()
        ->assertJsonPath('address.phone', '0599123456');
});

it('refuses a zone that does not exist', function () {
    test()->postJson('/api/addresses', addressPayload(['zoneId' => 'nowhere']), $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.zoneId.0', 'منطقة التوصيل غير موجودة');
});

it('refuses an out-of-range gps pin', function () {
    test()->postJson('/api/addresses', addressPayload(['location' => ['lat' => 999, 'lng' => 34.4]]), $this->headers)
        ->assertStatus(422);
});

// ─── reading ────────────────────────────────────────────────────────────────

it('lists only the signed-in customer addresses, default first', function () {
    test()->postJson('/api/addresses', addressPayload(), $this->headers)->assertCreated();
    $second = test()->postJson('/api/addresses', addressPayload(['type' => 'work']), $this->headers)->json('address.id');

    // Another customer's address must not appear here.
    $other = Customer::create(['name' => 'آخر', 'phone' => '0598000000']);
    $other->addresses()->create([
        'type' => 'home', 'label' => 'المنزل', 'name' => 'آخر', 'phone' => '0598000000',
        'city' => 'غزة', 'street' => 'شارع آخر', 'is_default' => true,
    ]);

    $response = test()->getJson('/api/addresses', $this->headers)->assertOk()->assertJsonCount(2);

    expect($response->json('0.isDefault'))->toBeTrue()
        ->and($response->json('1.id'))->toBe($second);
});

it('requires a token for every address route', function () {
    test()->getJson('/api/addresses')->assertStatus(401);
    test()->postJson('/api/addresses', addressPayload())->assertStatus(401);
});

// ─── updating ───────────────────────────────────────────────────────────────

it('replaces an address wholesale', function () {
    $id = test()->postJson('/api/addresses', addressPayload(), $this->headers)->json('address.id');

    test()->putJson("/api/addresses/{$id}", addressPayload([
        'street'   => 'شارع عمر المختار',
        'zoneId'   => 'shejaiya',
        'landmark' => null,
    ]), $this->headers)
        ->assertOk()
        ->assertJsonPath('address.street', 'شارع عمر المختار')
        ->assertJsonPath('address.zoneId', 'shejaiya')
        ->assertJsonPath('address.landmark', null);
});

it('will not let one customer touch another customer address', function () {
    $other = Customer::create(['name' => 'آخر', 'phone' => '0598000000']);
    $theirs = $other->addresses()->create([
        'type' => 'home', 'label' => 'المنزل', 'name' => 'آخر', 'phone' => '0598000000',
        'city' => 'غزة', 'street' => 'شارع آخر', 'is_default' => true,
    ]);

    // 404, not 403 — the answer must not confirm the address exists.
    test()->putJson("/api/addresses/{$theirs->id}", addressPayload(), $this->headers)->assertStatus(404);
    test()->deleteJson("/api/addresses/{$theirs->id}", [], $this->headers)->assertStatus(404);
    test()->postJson("/api/addresses/{$theirs->id}/default", [], $this->headers)->assertStatus(404);

    expect($theirs->fresh())->not->toBeNull();
});

// ─── default ────────────────────────────────────────────────────────────────

it('moves the default flag and leaves exactly one behind', function () {
    $first  = test()->postJson('/api/addresses', addressPayload(), $this->headers)->json('address.id');
    $second = test()->postJson('/api/addresses', addressPayload(['type' => 'work']), $this->headers)->json('address.id');

    test()->postJson("/api/addresses/{$second}/default", [], $this->headers)
        ->assertOk()
        ->assertJsonPath('address.isDefault', true);

    expect(Address::whereKey($first)->value('is_default'))->toBeFalsy()
        ->and($this->customer->addresses()->where('is_default', true)->count())->toBe(1);
});

// ─── deleting ───────────────────────────────────────────────────────────────

it('hands the default to a survivor when the default is deleted', function () {
    $first  = test()->postJson('/api/addresses', addressPayload(), $this->headers)->json('address.id');
    $second = test()->postJson('/api/addresses', addressPayload(['type' => 'work']), $this->headers)->json('address.id');

    test()->deleteJson("/api/addresses/{$first}", [], $this->headers)->assertOk();

    // Otherwise the customer keeps addresses but the checkout screen has none
    // preselected.
    expect(Address::whereKey($second)->value('is_default'))->toBeTruthy();
});

it('leaves nothing behind when the last address goes', function () {
    $id = test()->postJson('/api/addresses', addressPayload(), $this->headers)->json('address.id');

    test()->deleteJson("/api/addresses/{$id}", [], $this->headers)->assertOk();

    expect($this->customer->addresses()->count())->toBe(0);
});
