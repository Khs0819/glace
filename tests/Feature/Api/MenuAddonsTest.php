<?php

use App\Models\Addon;
use Illuminate\Database\QueryException;
use Tests\Support\CatalogFactory;

/**
 * Contract: swagger `IAddonOption`.
 * Handoff 08 §أ-5 / §ب-5: `GET /menu/addons` must never contain a duplicate id.
 */

function sharedAddon(string $slug, array $attributes = []): Addon
{
    return Addon::create(array_merge([
        'product_id' => null,
        'slug'       => $slug,
        'label'      => 'إضافة ' . $slug,
        'price'      => 3,
        'available'  => true,
        'type'       => 'toggle',
        'sort_order' => 1,
    ], $attributes));
}

it('returns only the shared catalog, never product scoped addons', function () {
    sharedAddon('extra-caramel');
    $product = CatalogFactory::flatList();
    $product->addons()->create([
        'slug' => 'ms-caramel', 'label' => 'صوص كراميل', 'price' => 3,
        'available' => true, 'type' => 'toggle', 'sort_order' => 1,
    ]);

    $addons = $this->getJson('/api/menu/addons')->assertOk()->json();

    expect($addons)->toHaveCount(1)
        ->and($addons[0]['id'])->toBe('extra-caramel');
});

it('returns every required IAddonOption field including counter metadata', function () {
    sharedAddon('extra-biscuit', ['type' => 'counter', 'max_qty' => 10]);
    sharedAddon('extra-caramel', ['sort_order' => 2]);

    $addons = $this->getJson('/api/menu/addons')->assertOk()->json();

    expect($addons[0])->toMatchArray([
        'id' => 'extra-biscuit', 'type' => 'counter', 'maxQty' => 10, 'available' => true,
    ]);

    foreach ($addons as $addon) {
        foreach (['id', 'label', 'price'] as $key) {
            expect($addon)->toHaveKey($key);
        }
    }

    // maxQty is only meaningful for counters and must be omitted otherwise.
    expect($addons[1])->not->toHaveKey('maxQty');
});

it('never exposes a duplicate addon id', function () {
    sharedAddon('extra-caramel');
    sharedAddon('extra-nuts', ['sort_order' => 2]);

    $ids = collect($this->getJson('/api/menu/addons')->assertOk()->json())->pluck('id');

    expect($ids->duplicates())->toBeEmpty();
});

it('deletes product addons with their product instead of leaking them into the shared catalog', function () {
    sharedAddon('extra-caramel');
    $product = CatalogFactory::flatList();
    $product->addons()->create([
        'slug' => 'ms-caramel', 'label' => 'صوص كراميل', 'price' => 3,
        'available' => true, 'type' => 'toggle', 'sort_order' => 1,
    ]);

    $product->delete();

    expect(Addon::whereNull('product_id')->pluck('slug')->all())->toBe(['extra-caramel'])
        ->and(Addon::count())->toBe(1);

    $ids = collect($this->getJson('/api/menu/addons')->json())->pluck('id');
    expect($ids->duplicates())->toBeEmpty();
});

it('rejects a duplicate slug within the same owner at the database level', function () {
    $product = CatalogFactory::flatList();
    $attributes = [
        'slug' => 'ms-caramel', 'label' => 'صوص كراميل', 'price' => 3,
        'available' => true, 'type' => 'toggle', 'sort_order' => 1,
    ];

    $product->addons()->create($attributes);

    expect(fn () => $product->addons()->create($attributes))->toThrow(QueryException::class);
});
