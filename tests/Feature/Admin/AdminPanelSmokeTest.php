<?php

use App\Filament\Resources\BranchResource;
use App\Filament\Resources\ContactResource;
use App\Filament\Resources\EventResource;
use App\Filament\Resources\FlavorResource;
use App\Filament\Resources\GlobalAddonResource;
use App\Filament\Resources\HeroSlideResource;
use App\Filament\Resources\HomeAboutResource;
use App\Filament\Resources\HomeWhyGlaceResource;
use App\Filament\Resources\MenuCategoryResource;
use App\Filament\Resources\ProductResource;
use App\Models\User;
use Livewire\Livewire;
use Tests\Support\CatalogFactory;

/** Regression guard: every admin screen an operator needs must actually render. */

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('requires authentication for the admin panel', function () {
    auth()->logout();

    $this->get(ProductResource::getUrl('index'))->assertRedirect();
});

it('renders every resource index page', function (string $resource) {
    $this->get($resource::getUrl('index'))->assertSuccessful();
})->with([
    ProductResource::class,
    FlavorResource::class,
    MenuCategoryResource::class,
    GlobalAddonResource::class,
    EventResource::class,
    HeroSlideResource::class,
    HomeAboutResource::class,
    HomeWhyGlaceResource::class,
    BranchResource::class,
    ContactResource::class,
]);

it('renders the product edit screen for both product kinds', function () {
    $flatList = CatalogFactory::flatList();
    CatalogFactory::item($flatList, 'nutella');

    $builder = CatalogFactory::builder();
    CatalogFactory::container($builder, 'cup');
    CatalogFactory::size($builder, 'cup-small');

    $this->get(ProductResource::getUrl('edit', ['record' => $flatList]))->assertSuccessful();
    $this->get(ProductResource::getUrl('edit', ['record' => $builder]))->assertSuccessful();
});

it('renders the dashboard with its widgets', function () {
    $this->get('/admin')->assertSuccessful();
});

it('lists products with the missing-image backlog column', function () {
    $product = CatalogFactory::flatList();
    CatalogFactory::item($product, 'no-image', ['image' => null]);

    Livewire::test(ProductResource\Pages\ListProducts::class)
        ->assertCanSeeTableRecords([$product])
        ->assertTableColumnExists('items_missing_image_count')
        ->assertCanRenderTableColumn('items_missing_image_count');
});

it('filters products down to those still missing variant images', function () {
    $withGap = CatalogFactory::flatList('pancake');
    CatalogFactory::item($withGap, 'no-image', ['image' => null]);

    $complete = CatalogFactory::flatList('waffle');
    CatalogFactory::item($complete, 'has-image', ['image' => 'items/x.png']);

    Livewire::test(ProductResource\Pages\ListProducts::class)
        ->filterTable('items_missing_image')
        ->assertCanSeeTableRecords([$withGap])
        ->assertCanNotSeeTableRecords([$complete]);
});
