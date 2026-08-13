<?php

use App\Filament\Resources\GlobalAddonResource;
use App\Filament\Resources\GlobalAddonResource\Pages\CreateGlobalAddon;
use App\Filament\Resources\GlobalAddonResource\Pages\ListGlobalAddons;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\RelationManagers\ProductAddonsRelationManager;
use App\Models\Addon;
use App\Models\User;
use Livewire\Livewire;
use Tests\Support\CatalogFactory;

/**
 * Handoff 08 §ب-5 — the shared additions screen must not be able to create a
 * duplicate id, and GET /menu/addons must stay duplicate free.
 */

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('creates a shared addon from the dashboard', function () {
    Livewire::test(CreateGlobalAddon::class)
        ->fillForm([
            'slug'       => 'extra-caramel',
            'label'      => 'صوص كراميل',
            'price'      => 3,
            'type'       => 'toggle',
            'available'  => true,
            'sort_order' => 1,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $this->getJson('/api/menu/addons')
        ->assertOk()
        ->assertJsonPath('0.id', 'extra-caramel')
        ->assertJsonPath('0.type', 'toggle');
});

it('refuses a duplicate shared addon id from the form', function () {
    Addon::create([
        'product_id' => null, 'slug' => 'extra-caramel', 'label' => 'صوص كراميل',
        'price' => 3, 'available' => true, 'type' => 'toggle', 'sort_order' => 1,
    ]);

    Livewire::test(CreateGlobalAddon::class)
        ->fillForm([
            'slug'       => 'extra-caramel',
            'label'      => 'مكرر',
            'price'      => 5,
            'type'       => 'toggle',
            'available'  => true,
            'sort_order' => 2,
        ])
        ->call('create')
        ->assertHasFormErrors(['slug']);

    expect(Addon::count())->toBe(1);
});

it('refuses a duplicate addon id within one product', function () {
    $product = CatalogFactory::flatList();
    $product->addons()->create([
        'slug' => 'ms-caramel', 'label' => 'صوص كراميل', 'price' => 3,
        'available' => true, 'type' => 'toggle', 'sort_order' => 1,
    ]);

    Livewire::test(ProductAddonsRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass'   => EditProduct::class,
    ])
        ->callTableAction('create', data: [
            'slug'       => 'ms-caramel',
            'label'      => 'مكرر',
            'price'      => 9,
            'type'       => 'toggle',
            'available'  => true,
            'sort_order' => 2,
        ])
        ->assertHasTableActionErrors(['slug']);

    expect($product->addons()->count())->toBe(1);
});

it('lists only shared addons on the shared additions screen', function () {
    Addon::create([
        'product_id' => null, 'slug' => 'extra-caramel', 'label' => 'صوص كراميل',
        'price' => 3, 'available' => true, 'type' => 'toggle', 'sort_order' => 1,
    ]);
    $product = CatalogFactory::flatList();
    $productAddon = $product->addons()->create([
        'slug' => 'ms-caramel', 'label' => 'صوص كراميل', 'price' => 3,
        'available' => true, 'type' => 'toggle', 'sort_order' => 1,
    ]);

    Livewire::test(ListGlobalAddons::class)
        ->assertCanSeeTableRecords(Addon::whereNull('product_id')->get())
        ->assertCanNotSeeTableRecords([$productAddon]);
});

it('keeps the counter cap field only for counter addons', function () {
    Livewire::test(CreateGlobalAddon::class)
        ->fillForm(['type' => 'toggle'])
        ->assertFormFieldIsHidden('max_qty')
        ->fillForm(['type' => 'counter'])
        ->assertFormFieldExists('max_qty');
});

it('exposes the shared addons resource in the admin panel', function () {
    expect(GlobalAddonResource::getModel())->toBe(Addon::class);

    $this->get(GlobalAddonResource::getUrl('index'))->assertSuccessful();
});
