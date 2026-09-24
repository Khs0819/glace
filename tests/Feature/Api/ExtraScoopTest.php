<?php

use App\Filament\Resources\ProductResource\RelationManagers\ExtraScoopRelationManager;
use App\Filament\Resources\ProductResource\RelationManagers\ProductAddonsRelationManager;
use App\Filament\Resources\ProductResource\Pages\EditProduct;
use App\Models\Addon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Auth\CustomerAuthService;
use Livewire\Livewire;
use Tests\Support\CatalogFactory;

/**
 * A scoop of ice cream on a crepe: two flavour lists on the product page, each
 * flavour priced on its own, and priced again on the server when it is ordered.
 */

beforeEach(function () {
    fakePublicDisk();

    $this->crepe = CatalogFactory::flatList('crepe', ['name' => 'كريب']);
    CatalogFactory::item($this->crepe, 'nutella', ['label' => 'نوتيلا', 'price' => 20]);

    $this->customer = Customer::create(['name' => 'أحمد', 'phone' => '0599123456']);
    $this->headers  = ['Authorization' => app(CustomerAuthService::class)->issueToken($this->customer)];
});

function scoop(Product $product, string $slug, string $label, float $price, string $family, bool $available = true): Addon
{
    return $product->addons()->create([
        'slug'         => $slug,
        'label'        => $label,
        'price'        => $price,
        'available'    => $available,
        'type'         => 'toggle',
        'scoop_family' => $family,
    ]);
}

function crepePayload(array $selections, array $overrides = []): array
{
    $items = [array_merge([
        'productId'  => test()->crepe->id,
        'name'       => 'كريب',
        'selections' => array_merge(
            [['kind' => 'item', 'id' => 'nutella', 'label' => 'نوتيلا', 'qty' => 1]],
            $selections,
        ),
        'quantity'   => 1,
    ], $overrides)];

    return [
        'items'          => json_encode($items),
        'paymentMethod'  => 'cash',
        'deliveryMethod' => 'pickup',
    ];
}

// ─── the product payload ────────────────────────────────────────────────────

it('sends the two scoop families, each flavour with its own price', function () {
    scoop($this->crepe, 'scoop-classic-vanilla', 'فانيلا', 5, Addon::SCOOP_CLASSIC);
    scoop($this->crepe, 'scoop-special-pistachio', 'بيستاشيو', 9, Addon::SCOOP_SPECIAL);
    scoop($this->crepe, 'scoop-special-lotus', 'لوتس', 8, Addon::SCOOP_SPECIAL);

    $product = test()->getJson('/api/menu/products/crepe')->assertOk()->json();

    expect($product['extraScoop']['classic'])->toBe([
        ['id' => 'scoop-classic-vanilla', 'label' => 'فانيلا', 'price' => 5, 'available' => true],
    ])
        // Each flavour priced on its own: pistachio is not lotus.
        ->and(array_column($product['extraScoop']['special'], 'price'))->toBe([9, 8]);
});

it('says nothing about scoops on a product that offers none', function () {
    expect(test()->getJson('/api/menu/products/crepe')->json())->not->toHaveKey('extraScoop');

    test()->getJson('/api/menu/products')
        ->assertOk()
        ->assertJsonMissingPath('0.extraScoop');
});

it('keeps an unavailable flavour in the list, greyed rather than gone', function () {
    scoop($this->crepe, 'scoop-classic-vanilla', 'فانيلا', 5, Addon::SCOOP_CLASSIC);
    scoop($this->crepe, 'scoop-classic-chocolate', 'شوكولاتة', 5, Addon::SCOOP_CLASSIC, available: false);

    $classic = test()->getJson('/api/menu/products/crepe')->json('extraScoop.classic');

    expect($classic)->toHaveCount(2)
        ->and($classic[1]['available'])->toBeFalse();
});

it('drops the whole control when every flavour is switched off', function () {
    scoop($this->crepe, 'scoop-classic-vanilla', 'فانيلا', 5, Addon::SCOOP_CLASSIC, available: false);
    scoop($this->crepe, 'scoop-special-lotus', 'لوتس', 8, Addon::SCOOP_SPECIAL, available: false);

    expect(test()->getJson('/api/menu/products/crepe')->json())->not->toHaveKey('extraScoop');
});

it('does not list a scoop twice as an ordinary addon', function () {
    scoop($this->crepe, 'scoop-classic-vanilla', 'فانيلا', 5, Addon::SCOOP_CLASSIC);
    $this->crepe->addons()->create(['slug' => 'extra-sauce', 'label' => 'صوص نوتيلا', 'price' => 3, 'type' => 'toggle']);

    $product = test()->getJson('/api/menu/products/crepe')->json();

    expect(array_column($product['addons'], 'id'))->toBe(['extra-sauce'])
        ->and($product['extraScoop']['classic'][0]['id'])->toBe('scoop-classic-vanilla');
});

it('sends the list on the products index too, not only the single product', function () {
    scoop($this->crepe, 'scoop-special-pistachio', 'بيستاشيو', 9, Addon::SCOOP_SPECIAL);

    test()->getJson('/api/menu/products')
        ->assertOk()
        ->assertJsonPath('0.extraScoop.special.0.id', 'scoop-special-pistachio');
});

// ─── ordering one ───────────────────────────────────────────────────────────

it('prices the scoop from the dashboard, not from what the client sent', function () {
    scoop($this->crepe, 'scoop-special-pistachio', 'بيستاشيو', 9, Addon::SCOOP_SPECIAL);

    test()->post('/api/orders', crepePayload([
        // The client says one shekel; the flavour costs nine.
        ['kind' => 'addon', 'id' => 'scoop-special-pistachio', 'label' => 'بيستاشيو', 'qty' => 1, 'unitPrice' => 1],
    ]), $this->headers)
        ->assertCreated()
        ->assertJsonPath('total', fn ($v) => (float) $v === 29.0);

    expect(Order::sole()->items->first()->description)->toContain('بيستاشيو');
});

it('refuses a scoop that is out of stock', function () {
    scoop($this->crepe, 'scoop-special-lotus', 'لوتس', 8, Addon::SCOOP_SPECIAL, available: false);

    test()->post('/api/orders', crepePayload([
        ['kind' => 'addon', 'id' => 'scoop-special-lotus', 'label' => 'لوتس', 'qty' => 1],
    ]), $this->headers)->assertStatus(422);

    expect(Order::count())->toBe(0);
});

it('refuses a scoop that belongs to another product', function () {
    $waffle = CatalogFactory::flatList('waffle', ['name' => 'وافل']);
    scoop($waffle, 'scoop-waffle-only', 'فانيلا', 5, Addon::SCOOP_CLASSIC);

    test()->post('/api/orders', crepePayload([
        ['kind' => 'addon', 'id' => 'scoop-waffle-only', 'label' => 'فانيلا', 'qty' => 1],
    ]), $this->headers)->assertStatus(422);

    expect(Order::count())->toBe(0);
});

it('refuses more than one of the same scoop on a unit', function () {
    scoop($this->crepe, 'scoop-classic-vanilla', 'فانيلا', 5, Addon::SCOOP_CLASSIC);

    test()->post('/api/orders', crepePayload([
        ['kind' => 'addon', 'id' => 'scoop-classic-vanilla', 'label' => 'فانيلا', 'qty' => 2],
    ]), $this->headers)->assertStatus(422);
});

// ─── the dashboard ──────────────────────────────────────────────────────────

it('adds a scoop flavour from the product page', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(ExtraScoopRelationManager::class, [
        'ownerRecord' => $this->crepe,
        'pageClass'   => EditProduct::class,
    ])
        ->callTableAction('create', data: [
            'scoop_family' => Addon::SCOOP_SPECIAL,
            'slug'         => 'scoop-special-pistachio',
            'label'        => 'بيستاشيو',
            'price'        => 9,
            'available'    => true,
            'sort_order'   => 1,
        ])
        ->assertHasNoTableActionErrors();

    $addon = $this->crepe->addons()->sole();

    expect($addon->scoop_family)->toBe(Addon::SCOOP_SPECIAL)
        // One scoop or none: the same shape as any toggle addon, which is what
        // stops an order asking for four of them.
        ->and($addon->type)->toBe('toggle')
        ->and($addon->max_qty)->toBeNull();
});

it('takes the whole control off the storefront without losing the prices', function () {
    $this->actingAs(User::factory()->create());
    scoop($this->crepe, 'scoop-classic-vanilla', 'فانيلا', 5, Addon::SCOOP_CLASSIC);

    Livewire::test(ExtraScoopRelationManager::class, [
        'ownerRecord' => $this->crepe,
        'pageClass'   => EditProduct::class,
    ])->callTableAction('disableAll');

    expect(test()->getJson('/api/menu/products/crepe')->json())->not->toHaveKey('extraScoop')
        ->and($this->crepe->addons()->sole()->price)->toBe(5.0);
});

it('keeps scoops off the ordinary addons tab', function () {
    $this->actingAs(User::factory()->create());

    $scoop = scoop($this->crepe, 'scoop-classic-vanilla', 'فانيلا', 5, Addon::SCOOP_CLASSIC);
    $sauce = $this->crepe->addons()->create(['slug' => 'extra-sauce', 'label' => 'صوص', 'price' => 3, 'type' => 'toggle']);

    Livewire::test(ProductAddonsRelationManager::class, [
        'ownerRecord' => $this->crepe,
        'pageClass'   => EditProduct::class,
    ])
        ->assertCanSeeTableRecords([$sauce])
        ->assertCanNotSeeTableRecords([$scoop]);
});

it('offers the scoop tab on flat-list products only', function () {
    $builder = CatalogFactory::builder('cup', ['name' => 'كاسة']);

    expect(ExtraScoopRelationManager::canViewForRecord($this->crepe, EditProduct::class))->toBeTrue()
        ->and(ExtraScoopRelationManager::canViewForRecord($builder, EditProduct::class))->toBeFalse();
});
