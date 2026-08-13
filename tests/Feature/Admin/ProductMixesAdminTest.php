<?php

use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\RelationManagers\MixesRelationManager;
use App\Models\ProductMix;
use App\Models\User;
use Livewire\Livewire;
use Tests\Support\CatalogFactory;

/**
 * Handoff 07 / 08 §ب-2 — full CRUD for مكس / سوبر مكس from Filament:
 * label, pick, the three prices, itemIds and available.
 */

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->product = CatalogFactory::flatList();
    CatalogFactory::item($this->product, 'nutella');
    CatalogFactory::item($this->product, 'lotus');
    CatalogFactory::item($this->product, 'pistachio', ['is_premium_mix_flavor' => true]);
});

function mixesManager($product)
{
    return Livewire::test(MixesRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass'   => EditProduct::class,
    ]);
}

it('adds a mix rule to a product that had none', function () {
    mixesManager($this->product)
        ->callTableAction('create', data: [
            'slug'                 => 'mix',
            'label'                => 'مكس (اختر طعمين)',
            'pick'                 => 2,
            'base_price'           => 14,
            'flavor_price'         => 7,
            'premium_flavor_price' => 11,
            'item_ids'             => ['nutella', 'lotus'],
            'available'            => true,
            'sort_order'           => 1,
        ])
        ->assertHasNoTableActionErrors();

    $mix = $this->getJson('/api/menu/products/pancake')->assertOk()->json('mixes.0');

    expect($mix['id'])->toBe('mix')
        ->and($mix['pick'])->toBe(2)
        ->and($mix['basePrice'])->toEqual(14)
        ->and($mix['itemIds'])->toBe(['nutella', 'lotus']);
});

it('offers only this product\'s variants as mix items', function () {
    $other = CatalogFactory::flatList('waffle');
    CatalogFactory::item($other, 'foreign-item');

    $options = mixesManager($this->product)
        ->mountTableAction('create')
        ->instance()
        ->getMountedTableActionForm()
        ->getFlatFields()['item_ids']
        ->getOptions();

    expect(array_keys($options))->toBe(['nutella', 'lotus', 'pistachio'])
        ->and(array_keys($options))->not->toContain('foreign-item');
});

it('rejects a mix that references an unknown item id', function () {
    mixesManager($this->product)
        ->callTableAction('create', data: [
            'slug'                 => 'mix',
            'label'                => 'مكس',
            'pick'                 => 2,
            'base_price'           => 14,
            'flavor_price'         => 7,
            'premium_flavor_price' => 11,
            'item_ids'             => ['does-not-exist'],
            'available'            => true,
            'sort_order'           => 1,
        ])
        // The rule lands on the offending element, not the whole array.
        ->assertHasTableActionErrors(['item_ids.0']);

    expect(ProductMix::count())->toBe(0);
});

it('updates mix prices and the order endpoint follows immediately', function () {
    $mix = CatalogFactory::mix($this->product, 'mix', ['nutella'], ['base_price' => 14]);

    mixesManager($this->product)
        ->callTableAction('edit', $mix, data: [
            'label'                => 'مكس (اختر طعمين)',
            'pick'                 => 2,
            'base_price'           => 20,
            'flavor_price'         => 7,
            'premium_flavor_price' => 11,
            'item_ids'             => ['nutella', 'pistachio'],
            'available'            => true,
            'sort_order'           => 1,
        ])
        ->assertHasNoTableActionErrors();

    $payload = $this->getJson('/api/menu/products/pancake')->json('mixes.0');

    expect($payload['basePrice'])->toEqual(20)
        ->and($payload['itemIds'])->toBe(['nutella', 'pistachio']);
});

it('pauses a mix without deleting it', function () {
    $mix = CatalogFactory::mix($this->product, 'super-mix', ['nutella']);

    mixesManager($this->product)
        ->callTableAction('edit', $mix, data: [
            'label'                => 'سوبر مكس',
            'pick'                 => 3,
            'base_price'           => 18,
            'flavor_price'         => 6,
            'premium_flavor_price' => 10,
            'item_ids'             => ['nutella'],
            'available'            => false,
            'sort_order'           => 1,
        ])
        ->assertHasNoTableActionErrors();

    expect(ProductMix::count())->toBe(1)
        ->and($this->getJson('/api/menu/products/pancake')->json('mixes.0.available'))->toBeFalse();
});

it('deletes a mix rule', function () {
    $mix = CatalogFactory::mix($this->product, 'mix', ['nutella']);

    mixesManager($this->product)->callTableAction('delete', $mix);

    expect(ProductMix::count())->toBe(0)
        ->and($this->getJson('/api/menu/products/pancake')->json())->not->toHaveKey('mixes');
});

it('refuses a duplicate mix id within the same product', function () {
    CatalogFactory::mix($this->product, 'mix', ['nutella']);

    mixesManager($this->product)
        ->callTableAction('create', data: [
            'slug'                 => 'mix',
            'label'                => 'مكرر',
            'pick'                 => 2,
            'base_price'           => 1,
            'flavor_price'         => 1,
            'premium_flavor_price' => 1,
            'item_ids'             => ['nutella'],
            'available'            => true,
            'sort_order'           => 2,
        ])
        ->assertHasTableActionErrors(['slug']);
});

it('shows the mixes tab only for flat-list products', function () {
    expect(MixesRelationManager::canViewForRecord($this->product, EditProduct::class))->toBeTrue()
        ->and(MixesRelationManager::canViewForRecord(CatalogFactory::builder(), EditProduct::class))->toBeFalse();
});
