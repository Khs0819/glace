<?php

use App\Filament\Resources\FlavorResource\Pages\EditFlavor;
use App\Filament\Resources\FlavorResource\Pages\ListFlavors;
use App\Filament\Resources\FlavorResource\RelationManagers\ProductsRelationManager;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Resources\ProductResource\RelationManagers\FlavorsRelationManager;
use App\Models\User;
use App\Support\FlavorFamily;
use Livewire\Livewire;
use Tests\Support\CatalogFactory;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/** Keys the attach select offers once its options query has run. */
function attachOptionKeys(string $relationManager, $ownerRecord, string $pageClass): array
{
    $component = Livewire::test($relationManager, [
        'ownerRecord' => $ownerRecord,
        'pageClass'   => $pageClass,
    ])->mountTableAction('attach');

    $options = $component->instance()
        ->getMountedTableActionForm()
        ->getComponent('mountedTableActionsData.0.recordId')
        ->getOptions();

    return array_keys($options);
}

it('renders the attach form on a product (the missing inverse relation 500ed here)', function () {
    $product = CatalogFactory::builder();
    CatalogFactory::flavor('mango');
    CatalogFactory::flavor('pistachio', ['family' => 'special']);

    Livewire::test(FlavorsRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass'   => EditProduct::class,
    ])
        ->mountTableAction('attach')
        ->assertHasNoTableActionErrors();
});

it('exposes the product_flavor link from both sides', function () {
    $product = CatalogFactory::builder();
    $flavor = CatalogFactory::flavor('mango');

    $product->flavors()->attach($flavor);

    expect($flavor->fresh()->products->pluck('id')->all())->toBe([$product->id])
        ->and($product->fresh()->flavors->pluck('id')->all())->toBe(['mango']);
});

it('only offers flavors from families the product declares', function () {
    $product = CatalogFactory::builder('cup', ['flavor_families' => ['classic', 'mix']]);
    CatalogFactory::flavor('mango');
    CatalogFactory::flavor('pistachio', ['family' => 'special']);
    CatalogFactory::flavor('vanilla-stevia', ['family' => 'stevia']);

    expect(attachOptionKeys(FlavorsRelationManager::class, $product, EditProduct::class))
        ->toBe(['mango']);
});

it('lets a product offer the stevia family', function () {
    $product = CatalogFactory::builder('cup', ['flavor_families' => ['classic', 'stevia']]);
    CatalogFactory::flavor('mango');
    CatalogFactory::flavor('vanilla-stevia', ['family' => 'stevia']);
    CatalogFactory::flavor('pistachio', ['family' => 'special']);

    expect(attachOptionKeys(FlavorsRelationManager::class, $product, EditProduct::class))
        ->toEqualCanonicalizing(['mango', 'vanilla-stevia']);
});

it('drops a flavor from the options once it is attached', function () {
    $product = CatalogFactory::builder();
    CatalogFactory::flavor('mango');

    Livewire::test(FlavorsRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass'   => EditProduct::class,
    ])
        ->callTableAction('attach', data: ['recordId' => 'mango'])
        ->assertHasNoTableActionErrors();

    expect($product->fresh()->flavors->pluck('id')->all())->toBe(['mango'])
        ->and(attachOptionKeys(FlavorsRelationManager::class, $product->fresh(), EditProduct::class))
        ->toBe([]);
});

it('flags attached flavors whose family the product does not offer', function () {
    $product = CatalogFactory::builder('cup', ['flavor_families' => ['classic']]);
    $mango = CatalogFactory::flavor('mango');
    $stevia = CatalogFactory::flavor('vanilla-stevia', ['family' => 'stevia']);

    $product->flavors()->attach([$mango->id, $stevia->id]);

    Livewire::test(FlavorsRelationManager::class, [
        'ownerRecord' => $product,
        'pageClass'   => EditProduct::class,
    ])
        ->filterTable('family_not_offered')
        ->assertCanSeeTableRecords([$stevia])
        ->assertCanNotSeeTableRecords([$mango]);
});

it('shows which products serve a flavor from the flavors section', function () {
    $product = CatalogFactory::builder();
    $mango = CatalogFactory::flavor('mango');

    $product->flavors()->attach($mango);

    Livewire::test(ProductsRelationManager::class, [
        'ownerRecord' => $mango->fresh(),
        'pageClass'   => EditFlavor::class,
    ])->assertCanSeeTableRecords([$product]);
});

it('surfaces unlinked flavors and a usage count in the flavors list', function () {
    $product = CatalogFactory::builder();
    $mango = CatalogFactory::flavor('mango');
    $orphan = CatalogFactory::flavor('grape');

    $product->flavors()->attach($mango);

    Livewire::test(ListFlavors::class)
        ->assertTableColumnStateSet('products_count', 1, $mango->getKey())
        ->assertTableColumnStateSet('products_count', 0, $orphan->getKey())
        ->filterTable('unlinked')
        ->assertCanSeeTableRecords([$orphan])
        ->assertCanNotSeeTableRecords([$mango]);
});

it('only offers builders that declare the family when linking from the flavors section', function () {
    $classicOnly = CatalogFactory::builder('cup', ['flavor_families' => ['classic']]);
    $withStevia = CatalogFactory::builder('family', ['flavor_families' => ['classic', 'stevia']]);
    $stevia = CatalogFactory::flavor('vanilla-stevia', ['family' => 'stevia']);

    $keys = attachOptionKeys(ProductsRelationManager::class, $stevia, EditFlavor::class);

    expect($keys)->toBe([$withStevia->id])
        ->and($keys)->not->toContain($classicOnly->id);
});

it('keeps the flavor picker and the price tiers in sync', function () {
    // Every family a flavor can carry must be offerable on a product, otherwise
    // an attached flavor lands in a family the storefront cannot price.
    expect(array_keys(FlavorFamily::pricingOptions()))
        ->toContain(...FlavorFamily::FLAVOR)
        ->and(FlavorFamily::FLAVOR)->not->toContain(FlavorFamily::MIX);
});
