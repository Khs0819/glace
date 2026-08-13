<?php

use Tests\Support\CatalogFactory;

/**
 * Contract: swagger `IProduct` (IFlatListProduct | IBuilderProduct)
 * Handoff: 01 (items[].image), 02 (flavors), 03 (category filter),
 *          05 (containers/sizes/prices), 07 (mixes).
 */

it('returns every required IProductVariant field including image', function () {
    $product = CatalogFactory::flatList();
    CatalogFactory::item($product, 'nutella', ['price' => 11]);
    CatalogFactory::item($product, 'pistachio', ['is_premium_mix_flavor' => true]);

    $response = $this->getJson('/api/menu/products/pancake')->assertOk();

    $items = $response->json('items');
    expect($items)->toHaveCount(2);

    foreach ($items as $item) {
        foreach (['id', 'label', 'price', 'available', 'image'] as $key) {
            expect($item)->toHaveKey($key);
        }
        expect($item['image'])->toStartWith('http');
    }

    expect($items[0]['id'])->toBe('nutella')
        ->and($items[0]['price'])->toEqual(11)
        ->and($items[1]['isPremiumMixFlavor'])->toBeTrue();
});

it('exposes the item slug as the contract id so renaming a label cannot break a mix', function () {
    $product = CatalogFactory::flatList();
    $item = CatalogFactory::item($product, 'nutella');
    CatalogFactory::mix($product, 'mix', ['nutella']);

    $item->update(['label' => 'اسم جديد تماماً']);

    $response = $this->getJson('/api/menu/products/pancake')->assertOk();

    expect($response->json('items.0.id'))->toBe('nutella')
        ->and($response->json('mixes.0.itemIds'))->toBe(['nutella']);
});

it('returns every required IMixRule field and keeps available:false', function () {
    $product = CatalogFactory::flatList();
    CatalogFactory::item($product, 'nutella');
    CatalogFactory::mix($product, 'super-mix', ['nutella'], ['available' => false, 'pick' => 3]);

    $mix = $this->getJson('/api/menu/products/pancake')->assertOk()->json('mixes.0');

    foreach (['id', 'label', 'pick', 'basePrice', 'flavorPrice', 'premiumFlavorPrice', 'itemIds', 'available'] as $key) {
        expect($mix)->toHaveKey($key);
    }

    expect($mix['available'])->toBeFalse()
        ->and($mix['pick'])->toBe(3);
});

it('returns builder containers, sizes, prices and size images', function () {
    $product = CatalogFactory::builder();
    CatalogFactory::container($product, 'cup', ['pricing_label' => 'الكاسة']);
    CatalogFactory::size($product, 'cup-small', ['classic' => 2, 'special' => 4], [
        'container_slug' => 'cup',
        'image'          => 'sizes/cup-small.png',
    ]);

    $response = $this->getJson('/api/menu/products/cup')->assertOk();

    expect($response->json('containerOptions.0'))
        ->toMatchArray(['id' => 'cup', 'label' => 'حاوية cup', 'available' => true, 'pricingLabel' => 'الكاسة']);

    $size = $response->json('sizes.0');
    expect($size['id'])->toBe('cup-small')
        ->and($size['containerId'])->toBe('cup')
        ->and($size['maxBalls'])->toBe(1)
        ->and($size['available'])->toBeTrue()
        ->and($size['image'])->toStartWith('http')
        ->and($size['prices'])->toEqual([
            ['flavorFamily' => 'classic', 'price' => 2],
            ['flavorFamily' => 'special', 'price' => 4],
        ]);
});

it('keeps a paused size in the payload instead of dropping it', function () {
    $product = CatalogFactory::builder();
    CatalogFactory::size($product, 'foam-one', ['classic' => 31], ['available' => false]);

    $size = $this->getJson('/api/menu/products/cup')->assertOk()->json('sizes.0');

    expect($size['id'])->toBe('foam-one')
        ->and($size['available'])->toBeFalse();
});

it('always sends flavors[].available even when false', function () {
    $product = CatalogFactory::builder();
    CatalogFactory::size($product, 'cup-small');
    $product->flavors()->attach([
        CatalogFactory::flavor('mango', ['available' => false])->id,
        CatalogFactory::flavor('pistachio', ['available' => true, 'family' => 'special'])->id,
    ]);

    $flavors = $this->getJson('/api/menu/products/cup')->assertOk()->json('flavors');

    expect($flavors)->toHaveCount(2);

    foreach ($flavors as $flavor) {
        foreach (['id', 'nameAr', 'nameEn', 'image', 'family', 'available'] as $key) {
            expect($flavor)->toHaveKey($key);
        }
    }

    expect(collect($flavors)->firstWhere('id', 'mango')['available'])->toBeFalse();
});

it('omits flavors from the list endpoint but returns them on detail', function () {
    $product = CatalogFactory::builder();
    CatalogFactory::size($product, 'cup-small');
    $product->flavors()->attach(CatalogFactory::flavor()->id);

    $list = $this->getJson('/api/menu/products')->assertOk()->json();

    expect($list)->toHaveCount(1)
        ->and($list[0])->not->toHaveKey('flavors');

    expect($this->getJson('/api/menu/products/cup')->json('flavors'))->toHaveCount(1);
});

it('returns iceCreamAddonPrices for brad-boza and never a secondaryImage', function () {
    $product = CatalogFactory::builder('brad-boza', [
        'includes_ice_cream_step' => true,
        'pricing_label'           => 'أسعار البراد',
    ]);
    CatalogFactory::size($product, 'brad-boza-small');
    $product->iceCreamAddonPrices()->create(['flavor_family' => 'classic', 'price' => 3]);

    $response = $this->getJson('/api/menu/products/brad-boza')->assertOk();

    expect($response->json('iceCreamAddonPrices'))->toEqual([['flavorFamily' => 'classic', 'price' => 3]])
        ->and($response->json('pricingLabel'))->toBe('أسعار البراد')
        ->and($response->json())->not->toHaveKey('secondaryImage');
});

it('filters by category and rejects the wrong categoryId key with 422', function () {
    CatalogFactory::flatList();
    CatalogFactory::category('drinks')->update(['label' => 'مشروبات']);
    CatalogFactory::flatList('juices', ['category_id' => 'drinks']);

    expect($this->getJson('/api/menu/products?category=pancake')->assertOk()->json())->toHaveCount(1);

    // Handoff 03 §ب: never silently return the whole catalog for a wrong filter key.
    $this->getJson('/api/menu/products?categoryId=pancake')
        ->assertStatus(422)
        ->assertJsonStructure(['message', 'errors']);
});

it('hides unavailable products from the browse grid but still serves the order page', function () {
    CatalogFactory::flatList('pancake', ['available' => false]);

    expect($this->getJson('/api/menu/products')->assertOk()->json())->toBeEmpty();

    $this->getJson('/api/menu/products/pancake')
        ->assertOk()
        ->assertJsonPath('available', false);
});

it('returns a json 404 for an unknown slug', function () {
    $this->getJson('/api/menu/products/does-not-exist')
        ->assertStatus(404)
        ->assertJsonStructure(['message']);
});

it('never emits a relative media path or a dead example.com host', function () {
    $product = CatalogFactory::flatList('pancake', ['image' => 'https://cdn.example.com/dead.png']);
    CatalogFactory::item($product, 'nutella', ['image' => 'items/nutella.png']);

    $response = $this->getJson('/api/menu/products/pancake')->assertOk();

    expect($response->json('image'))->toBeNull()
        ->and($response->json('items.0.image'))->toStartWith('http')
        ->and($response->json('items.0.image'))->toContain('/storage/items/nutella.png');
});

it('serializes the product id as the primary key, not the slug', function () {
    $product = CatalogFactory::flatList();

    $this->getJson('/api/menu/products/pancake')
        ->assertOk()
        ->assertJsonPath('id', (string) $product->id)
        ->assertJsonPath('slug', 'pancake');
});
