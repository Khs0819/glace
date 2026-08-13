<?php

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\RelationManagers\ItemsRelationManager;
use App\Models\ProductItem;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\CatalogFactory;

/**
 * Handoff 01 / 08 §ب-1 — the admin must be able to manage every flat-list
 * variant: label, price, available, isPremiumMixFlavor and an image upload.
 */

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->product = CatalogFactory::flatList();
});

function itemsManager($product)
{
    return Livewire::test(ItemsRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass'   => EditProduct::class,
    ]);
}

it('creates a variant with a price from the dashboard', function () {
    itemsManager($this->product)
        ->callTableAction('create', data: [
            'slug'                  => 'nutella',
            'label'                 => 'نوتيلا',
            'price'                 => 11,
            'available'             => true,
            'is_premium_mix_flavor' => false,
            'sort_order'            => 1,
        ])
        ->assertHasNoTableActionErrors();

    expect(ProductItem::where('slug', 'nutella')->first())
        ->not->toBeNull()
        ->label->toBe('نوتيلا')
        ->price->toEqual(11);
});

it('uploads a variant image that the api then serves as an absolute url', function () {
    fakePublicDisk();

    itemsManager($this->product)
        ->callTableAction('create', data: [
            'slug'       => 'nutella',
            'label'      => 'نوتيلا',
            'price'      => 11,
            'available'  => true,
            'image'      => [UploadedFile::fake()->image('nutella.png')],
            'sort_order' => 1,
        ])
        ->assertHasNoTableActionErrors();

    $item = ProductItem::where('slug', 'nutella')->first();
    expect($item->image)->not->toBeNull();
    Storage::disk('public')->assertExists($item->image);

    $this->getJson('/api/menu/products/pancake')
        ->assertOk()
        ->assertJsonPath('items.0.image', fn ($url) => str_starts_with($url, 'http') && str_contains($url, $item->image));
});

it('reflects a price change from the dashboard on the order endpoint immediately', function () {
    $item = CatalogFactory::item($this->product, 'nutella', ['price' => 8]);

    itemsManager($this->product)
        ->callTableAction('edit', $item, data: [
            'label'      => 'نوتيلا',
            'price'      => 20,
            'available'  => true,
            'sort_order' => 1,
        ])
        ->assertHasNoTableActionErrors();

    $this->getJson('/api/menu/products/pancake')
        ->assertOk()
        ->assertJsonPath('items.0.price', fn ($price) => (float) $price === 20.0);
});

it('toggles availability and the premium mix flag from the dashboard', function () {
    $item = CatalogFactory::item($this->product, 'pistachio');

    itemsManager($this->product)
        ->callTableAction('edit', $item, data: [
            'label'                 => 'بيستاشيو',
            'price'                 => 17,
            'available'             => false,
            'is_premium_mix_flavor' => true,
            'sort_order'            => 1,
        ])
        ->assertHasNoTableActionErrors();

    $payload = $this->getJson('/api/menu/products/pancake')->json('items.0');

    expect($payload['available'])->toBeFalse()
        ->and($payload['isPremiumMixFlavor'])->toBeTrue();
});

it('deletes a variant from the dashboard', function () {
    $item = CatalogFactory::item($this->product, 'nutella');

    itemsManager($this->product)->callTableAction('delete', $item);

    expect(ProductItem::find($item->id))->toBeNull()
        ->and($this->getJson('/api/menu/products/pancake')->json('items'))->toBe([]);
});

it('refuses a duplicate variant id within the same product', function () {
    CatalogFactory::item($this->product, 'nutella');

    itemsManager($this->product)
        ->callTableAction('create', data: [
            'slug'       => 'nutella',
            'label'      => 'مكرر',
            'price'      => 5,
            'available'  => true,
            'sort_order' => 2,
        ])
        ->assertHasTableActionErrors(['slug']);

    expect(ProductItem::where('slug', 'nutella')->count())->toBe(1);
});

it('allows the same variant id on a different product', function () {
    CatalogFactory::item($this->product, 'nutella');
    $other = CatalogFactory::flatList('waffle');

    itemsManager($other)
        ->callTableAction('create', data: [
            'slug'       => 'nutella',
            'label'      => 'نوتيلا',
            'price'      => 9,
            'available'  => true,
            'sort_order' => 1,
        ])
        ->assertHasNoTableActionErrors();

    expect(ProductItem::where('slug', 'nutella')->count())->toBe(2);
});

it('shows the variants tab only for flat-list products', function () {
    expect(ItemsRelationManager::canViewForRecord($this->product, EditProduct::class))->toBeTrue()
        ->and(ItemsRelationManager::canViewForRecord(CatalogFactory::builder(), EditProduct::class))->toBeFalse();
});

it('assigns one uploaded image to several variants at once', function () {
    fakePublicDisk();

    $a = CatalogFactory::item($this->product, 'a', ['image' => null]);
    $b = CatalogFactory::item($this->product, 'b', ['image' => null]);

    itemsManager($this->product)
        ->callTableBulkAction('set_image', [$a, $b], data: [
            'image' => [UploadedFile::fake()->image('shared.png')],
        ])
        ->assertHasNoTableBulkActionErrors();

    expect($a->refresh()->image)->not->toBeNull()
        ->and($b->refresh()->image)->toBe($a->image);

    Storage::disk('public')->assertExists($a->image);

    $images = collect($this->getJson('/api/menu/products/pancake')->json('items'))->pluck('image');
    expect($images->filter())->toHaveCount(2);
});

it('flags how many variants still need an image', function () {
    CatalogFactory::item($this->product, 'with-image', ['image' => 'items/a.png']);
    CatalogFactory::item($this->product, 'without-image', ['image' => null]);

    expect(ItemsRelationManager::getBadge($this->product, EditProduct::class))->toBe('1 بلا صورة');
});
