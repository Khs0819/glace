<?php

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\RelationManagers\ContainersRelationManager;
use App\Filament\Resources\ProductResource\RelationManagers\IceCreamAddonPricesRelationManager;
use App\Filament\Resources\ProductResource\RelationManagers\SizesRelationManager;
use App\Models\ProductContainer;
use App\Models\ProductSize;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CatalogFactory;

/**
 * Handoff 05 / 08 §ب-3 — the admin must fully control «اختر النوع»
 * (containerOptions), «اختر الحجم» (sizes), sizes[].prices, sizes[].image and
 * brad-boza iceCreamAddonPrices, for every builder product.
 */

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->product = CatalogFactory::builder();
});

function relationManager(string $class, $product)
{
    return Livewire::test($class, [
        'ownerRecord' => $product,
        'pageClass'   => EditProduct::class,
    ]);
}

it('adds a container that appears immediately in containerOptions', function () {
    relationManager(ContainersRelationManager::class, $this->product)
        ->callTableAction('create', data: [
            'slug'          => 'biscuit',
            'label'         => 'بسكوت',
            'name'          => 'بوظة بسكوت',
            'pricing_label' => 'البسكوت',
            'available'     => true,
            'sort_order'    => 2,
        ])
        ->assertHasNoTableActionErrors();

    CatalogFactory::size($this->product, 'biscuit-small', ['classic' => 2], ['container_slug' => 'biscuit']);

    $option = $this->getJson('/api/menu/products/cup')->assertOk()->json('containerOptions.0');

    expect($option)->toMatchArray([
        'id' => 'biscuit', 'label' => 'بسكوت', 'available' => true,
        'name' => 'بوظة بسكوت', 'pricingLabel' => 'البسكوت',
    ]);
});

it('pauses a container without deleting it', function () {
    $container = CatalogFactory::container($this->product, 'foam');
    CatalogFactory::size($this->product, 'foam-half', ['classic' => 16], ['container_slug' => 'foam']);

    relationManager(ContainersRelationManager::class, $this->product)
        ->callTableAction('edit', $container, data: [
            'label' => 'فلين', 'available' => false, 'sort_order' => 1,
        ])
        ->assertHasNoTableActionErrors();

    expect(ProductContainer::count())->toBe(1)
        ->and($this->getJson('/api/menu/products/cup')->json('containerOptions.0.available'))->toBeFalse();
});

it('creates a size together with its per-family price grid', function () {
    CatalogFactory::container($this->product, 'cup');

    relationManager(SizesRelationManager::class, $this->product)
        ->callTableAction('create', data: [
            'slug'           => 'cup-small',
            'label'          => 'صغير',
            'max_balls'      => 1,
            'container_slug' => 'cup',
            'available'      => true,
            'sort_order'     => 1,
            'prices'         => [
                ['flavor_family' => 'classic', 'price' => 2],
                ['flavor_family' => 'special', 'price' => 4],
            ],
        ])
        ->assertHasNoTableActionErrors();

    $size = $this->getJson('/api/menu/products/cup')->assertOk()->json('sizes.0');

    expect($size['id'])->toBe('cup-small')
        ->and($size['containerId'])->toBe('cup')
        ->and($size['prices'])->toEqual([
            ['flavorFamily' => 'classic', 'price' => 2],
            ['flavorFamily' => 'special', 'price' => 4],
        ]);
});

it('changes a size price and the order endpoint follows immediately', function () {
    $size = CatalogFactory::size($this->product, 'plastic-half', ['classic' => 14]);
    $priceRow = $size->prices()->first();

    relationManager(SizesRelationManager::class, $this->product)
        ->callTableAction('edit', $size, data: [
            'label'      => '1/2 لتر',
            'max_balls'  => 8,
            'available'  => true,
            'sort_order' => 1,
            // A relationship repeater keys existing rows as record-{id}; using a
            // fresh numeric key would append a row instead of editing this one.
            'prices'     => ["record-{$priceRow->id}" => ['flavor_family' => 'classic', 'price' => 25]],
        ])
        ->assertHasNoTableActionErrors();

    expect($this->getJson('/api/menu/products/cup')->json('sizes.0.prices'))
        ->toEqual([['flavorFamily' => 'classic', 'price' => 25]]);
});

it('uploads a size image that the api returns as an absolute url', function () {
    fakePublicDisk();

    relationManager(SizesRelationManager::class, $this->product)
        ->callTableAction('create', data: [
            'slug'       => 'plastic-half',
            'label'      => '1/2 لتر',
            'max_balls'  => 8,
            'available'  => true,
            'sort_order' => 1,
            'image'      => [UploadedFile::fake()->image('family-half.png')],
            'prices'     => [['flavor_family' => 'classic', 'price' => 14]],
        ])
        ->assertHasNoTableActionErrors();

    $size = ProductSize::where('slug', 'plastic-half')->first();
    Storage::disk('public')->assertExists($size->image);

    $this->getJson('/api/menu/products/cup')
        ->assertOk()
        ->assertJsonPath('sizes.0.image', fn ($url) => str_starts_with($url, 'http') && str_contains($url, $size->image));
});

it('rejects a size bound to a container from another product', function () {
    CatalogFactory::container($this->product, 'cup');
    $other = CatalogFactory::builder('family');
    CatalogFactory::container($other, 'plastic');

    relationManager(SizesRelationManager::class, $this->product)
        ->callTableAction('create', data: [
            'slug'           => 'cup-small',
            'label'          => 'صغير',
            'max_balls'      => 1,
            'container_slug' => 'plastic',
            'available'      => true,
            'sort_order'     => 1,
        ])
        ->assertHasTableActionErrors(['container_slug']);

    expect(ProductSize::count())->toBe(0);
});

it('stops one size independently of its container', function () {
    $size = CatalogFactory::size($this->product, 'foam-one', ['classic' => 31]);

    // Only the availability toggle changes; the mounted price grid stays as-is.
    relationManager(SizesRelationManager::class, $this->product)
        ->callTableAction('edit', $size, data: [
            'label' => '1 لتر', 'max_balls' => 16, 'available' => false, 'sort_order' => 1,
        ])
        ->assertHasNoTableActionErrors();

    $payload = $this->getJson('/api/menu/products/cup')->json('sizes.0');

    expect($payload['available'])->toBeFalse()
        ->and($payload['id'])->toBe('foam-one');
});

it('deletes a size and its price grid', function () {
    $size = CatalogFactory::size($this->product, 'cup-small', ['classic' => 2, 'special' => 4]);

    relationManager(SizesRelationManager::class, $this->product)->callTableAction('delete', $size);

    expect(ProductSize::count())->toBe(0)
        ->and(App\Models\SizePrice::count())->toBe(0);
});

it('edits brad-boza ice cream addon prices from the dashboard', function () {
    $bradBoza = CatalogFactory::builder('brad-boza', ['includes_ice_cream_step' => true]);
    CatalogFactory::size($bradBoza, 'brad-boza-small', ['classic' => 1]);
    $price = $bradBoza->iceCreamAddonPrices()->create(['flavor_family' => 'classic', 'price' => 3]);

    relationManager(IceCreamAddonPricesRelationManager::class, $bradBoza)
        ->callTableAction('edit', $price, data: ['flavor_family' => 'classic', 'price' => 4])
        ->assertHasNoTableActionErrors();

    expect($this->getJson('/api/menu/products/brad-boza')->json('iceCreamAddonPrices'))
        ->toEqual([['flavorFamily' => 'classic', 'price' => 4]]);
});

it('refuses a second price for the same flavor family', function () {
    $bradBoza = CatalogFactory::builder('brad-boza', ['includes_ice_cream_step' => true]);
    $bradBoza->iceCreamAddonPrices()->create(['flavor_family' => 'classic', 'price' => 3]);

    relationManager(IceCreamAddonPricesRelationManager::class, $bradBoza)
        ->callTableAction('create', data: ['flavor_family' => 'classic', 'price' => 9])
        ->assertHasTableActionErrors(['flavor_family']);

    expect($bradBoza->iceCreamAddonPrices()->count())->toBe(1);
});

it('scopes builder-only panels to builder products', function () {
    $flatList = CatalogFactory::flatList();

    expect(SizesRelationManager::canViewForRecord($this->product, EditProduct::class))->toBeTrue()
        ->and(SizesRelationManager::canViewForRecord($flatList, EditProduct::class))->toBeFalse()
        ->and(ContainersRelationManager::canViewForRecord($this->product, EditProduct::class))->toBeTrue()
        ->and(ContainersRelationManager::canViewForRecord($flatList, EditProduct::class))->toBeFalse();
});

it('shows the ice cream price panel only when the product has that step', function () {
    $bradBoza = CatalogFactory::builder('brad-boza', ['includes_ice_cream_step' => true]);

    expect(IceCreamAddonPricesRelationManager::canViewForRecord($bradBoza, EditProduct::class))->toBeTrue()
        ->and(IceCreamAddonPricesRelationManager::canViewForRecord($this->product, EditProduct::class))->toBeFalse();
});
