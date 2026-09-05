<?php

use App\Services\Checkout\CartPricer;
use Illuminate\Validation\ValidationException;
use Tests\Support\CatalogFactory;

function priceCart(array $lines)
{
    return app(CartPricer::class)->price($lines);
}

/** @return array<string, string> field => first message */
function priceErrors(array $lines): array
{
    try {
        priceCart($lines);
    } catch (ValidationException $e) {
        return array_map(fn (array $messages) => $messages[0], $e->errors());
    }

    return [];
}

// ─── flat-list ──────────────────────────────────────────────────────────────

it('prices a flat list item from the catalog, never from the client', function () {
    $product = CatalogFactory::flatList();
    CatalogFactory::item($product, 'classic', ['price' => 12.5, 'label' => 'كلاسيك']);

    $cart = priceCart([[
        'productId' => $product->id,
        'itemId'    => 'classic',
        'quantity'  => 3,
        // A client-supplied price must be ignored outright.
        'price'     => 1,
    ]]);

    expect($cart->total())->toBe(37.5)
        ->and($cart->lines[0]->description)->toBe('كلاسيك')
        ->and($cart->lines[0]->toArray()['unitPrice'])->toBe(12.5);
});

it('prices a mix per chosen flavour, with premium replacing the standard price', function () {
    $product = CatalogFactory::flatList();
    CatalogFactory::item($product, 'nutella', ['label' => 'نوتيلا']);
    CatalogFactory::item($product, 'lotus', ['label' => 'لوتس', 'is_premium_mix_flavor' => true]);
    CatalogFactory::mix($product, 'mix', ['nutella', 'lotus'], [
        'pick' => 2, 'base_price' => 14, 'flavor_price' => 7, 'premium_flavor_price' => 11,
    ]);

    $cart = priceCart([[
        'productId'  => $product->id,
        'mixId'      => 'mix',
        'mixItemIds' => ['nutella', 'lotus'],
        'quantity'   => 1,
    ]]);

    // 7 standard + 11 premium — not basePrice(14) plus anything.
    expect($cart->total())->toBe(18.0)
        ->and($cart->lines[0]->description)->toBe('مكس: نوتيلا + لوتس');
});

it('charges basePrice exactly when every pick is a standard flavour', function () {
    $product = CatalogFactory::flatList();
    CatalogFactory::item($product, 'nutella');
    CatalogFactory::item($product, 'pistachio');
    $mix = CatalogFactory::mix($product, 'mix', ['nutella', 'pistachio']);

    $cart = priceCart([[
        'productId'  => $product->id,
        'mixId'      => 'mix',
        'mixItemIds' => ['nutella', 'pistachio'],
        'quantity'   => 1,
    ]]);

    // The invariant the whole rule rests on: base = pick × flavorPrice.
    expect($cart->total())->toEqual($mix->base_price)
        ->and($cart->total())->toEqual($mix->pick * $mix->flavor_price);
});

it('holds the mix to its exact pick count and its own item list', function () {
    $product = CatalogFactory::flatList();
    CatalogFactory::item($product, 'nutella');
    CatalogFactory::item($product, 'lotus');
    CatalogFactory::item($product, 'outsider');
    CatalogFactory::mix($product, 'mix', ['nutella', 'lotus'], ['pick' => 2]);

    $line = ['productId' => $product->id, 'mixId' => 'mix', 'quantity' => 1];

    expect(priceErrors([$line + ['mixItemIds' => ['nutella']]]))
        ->toHaveKey('items.0.mixItemIds');

    expect(priceErrors([$line + ['mixItemIds' => ['nutella', 'outsider']]])['items.0.mixItemIds'])
        ->toContain('غير متاح ضمن');

    expect(priceErrors([$line + ['mixItemIds' => ['nutella', 'nutella']]])['items.0.mixItemIds'])
        ->toContain('نفس الصنف');
});

// ─── builder ────────────────────────────────────────────────────────────────

function cupProduct(): App\Models\Product
{
    $product = CatalogFactory::builder('cup', ['flavor_families' => ['classic', 'special', 'mix']]);

    CatalogFactory::container($product, 'cup', ['label' => 'كاسة']);
    CatalogFactory::container($product, 'biscuit', ['label' => 'بسكوت']);

    CatalogFactory::size($product, 'cup-large', ['classic' => 5, 'special' => 7, 'mix' => 6], [
        'label' => 'كبير', 'max_balls' => 3, 'container_slug' => 'cup',
    ]);
    CatalogFactory::size($product, 'biscuit-small', ['classic' => 2], [
        'label' => 'بسكوت صغير', 'max_balls' => 1, 'container_slug' => 'biscuit',
    ]);

    $product->flavors()->attach([
        CatalogFactory::flavor('vanilla', ['name_ar' => 'فانيلا', 'family' => 'classic'])->id,
        CatalogFactory::flavor('pistachio', ['name_ar' => 'فستق', 'family' => 'special'])->id,
    ]);

    return $product->fresh();
}

it('prices a builder by the family its flavours resolve to', function () {
    $product = cupProduct();

    $classic = priceCart([[
        'productId' => $product->id, 'containerId' => 'cup', 'sizeId' => 'cup-large',
        'flavorIds' => ['vanilla'], 'quantity' => 1,
    ]]);

    $special = priceCart([[
        'productId' => $product->id, 'containerId' => 'cup', 'sizeId' => 'cup-large',
        'flavorIds' => ['pistachio'], 'quantity' => 1,
    ]]);

    expect($classic->total())->toBe(5.0)
        ->and($special->total())->toBe(7.0)
        ->and($classic->lines[0]->selection['flavorFamily'])->toBe('classic')
        ->and($classic->lines[0]->description)->toBe('كاسة · كبير · فانيلا');
});

it('falls to the mix tier when the balls span more than one family', function () {
    $product = cupProduct();

    $cart = priceCart([[
        'productId' => $product->id, 'containerId' => 'cup', 'sizeId' => 'cup-large',
        'flavorIds' => ['vanilla', 'pistachio'], 'quantity' => 1,
    ]]);

    expect($cart->total())->toBe(6.0)
        ->and($cart->lines[0]->selection['flavorFamily'])->toBe('mix');
});

it('refuses a family the chosen size has no price row for', function () {
    $product = cupProduct();

    // biscuit-small is priced classic-only; a special ball there must not
    // quietly fall back to the classic price.
    expect(priceErrors([[
        'productId' => $product->id, 'containerId' => 'biscuit', 'sizeId' => 'biscuit-small',
        'flavorIds' => ['pistachio'], 'quantity' => 1,
    ]])['items.0.flavorIds'])->toContain('لا يوجد سعر');
});

it('refuses a size that belongs to a different container', function () {
    $product = cupProduct();

    expect(priceErrors([[
        'productId' => $product->id, 'containerId' => 'biscuit', 'sizeId' => 'cup-large',
        'flavorIds' => ['vanilla'], 'quantity' => 1,
    ]])['items.0.sizeId'])->toContain('غير متاح مع هذا النوع');
});

it('keeps the ball count inside the size', function () {
    $product = cupProduct();

    expect(priceErrors([[
        'productId' => $product->id, 'containerId' => 'biscuit', 'sizeId' => 'biscuit-small',
        'flavorIds' => ['vanilla', 'vanilla'], 'quantity' => 1,
    ]])['items.0.flavorIds'])->toContain('كحد أقصى');
});

it('lets repeatable products double a flavour but not toggle ones', function () {
    $product = cupProduct();

    $repeatable = priceCart([[
        'productId' => $product->id, 'containerId' => 'cup', 'sizeId' => 'cup-large',
        'flavorIds' => ['vanilla', 'vanilla'], 'quantity' => 1,
    ]]);

    expect($repeatable->total())->toBe(5.0);

    $product->update(['selection_mode' => 'toggle']);

    expect(priceErrors([[
        'productId' => $product->id, 'containerId' => 'cup', 'sizeId' => 'cup-large',
        'flavorIds' => ['vanilla', 'vanilla'], 'quantity' => 1,
    ]])['items.0.flavorIds'])->toContain('النكهة نفسها');
});

it('prices a builder with no ball picker from its single price row', function () {
    $product = CatalogFactory::builder('brad', ['flavor_families' => null, 'selection_mode' => null]);
    CatalogFactory::container($product, 'lemon', ['label' => 'ليمون']);
    CatalogFactory::size($product, 'brad-large', ['classic' => 3], ['label' => 'كبير', 'max_balls' => 0]);

    $cart = priceCart([[
        'productId' => $product->id, 'containerId' => 'lemon', 'sizeId' => 'brad-large', 'quantity' => 2,
    ]]);

    expect($cart->total())->toBe(6.0)
        ->and($cart->lines[0]->description)->toBe('ليمون · كبير');
});

it('adds the ice cream surcharge on top of a flat brad price', function () {
    $product = CatalogFactory::builder('brad-boza', [
        'flavor_families'         => ['classic', 'special', 'mix'],
        'selection_mode'          => 'toggle',
        'includes_ice_cream_step' => true,
    ]);
    CatalogFactory::container($product, 'lemon', ['label' => 'ليمون']);
    CatalogFactory::size($product, 'bb-medium', ['classic' => 2], ['label' => 'وسط', 'max_balls' => 3]);
    CatalogFactory::iceCreamAddonPrice($product, 'classic', 3);
    CatalogFactory::iceCreamAddonPrice($product, 'special', 5);
    CatalogFactory::iceCreamAddonPrice($product, 'mix', 4);

    $product->flavors()->attach([
        CatalogFactory::flavor('vanilla', ['name_ar' => 'فانيلا', 'family' => 'classic'])->id,
        CatalogFactory::flavor('pistachio', ['name_ar' => 'فستق', 'family' => 'special'])->id,
    ]);

    // The brad is 2 whatever is scooped on it; the balls drive the surcharge.
    $special = priceCart([[
        'productId' => $product->id, 'containerId' => 'lemon', 'sizeId' => 'bb-medium',
        'flavorIds' => ['pistachio'], 'quantity' => 1,
    ]]);

    $mixed = priceCart([[
        'productId' => $product->id, 'containerId' => 'lemon', 'sizeId' => 'bb-medium',
        'flavorIds' => ['vanilla', 'pistachio'], 'quantity' => 1,
    ]]);

    expect($special->total())->toBe(7.0)                       // 2 + 5
        ->and($mixed->total())->toBe(6.0)                      // 2 + 4
        ->and($special->lines[0]->description)->toBe('ليمون · وسط · بوظة: فستق');
});

// ─── availability ───────────────────────────────────────────────────────────

it('refuses anything the dashboard has switched off', function () {
    $product = cupProduct();
    CatalogFactory::item($product, 'ignored');

    $product->update(['available' => false]);
    expect(priceErrors([[
        'productId' => $product->id, 'containerId' => 'cup', 'sizeId' => 'cup-large',
        'flavorIds' => ['vanilla'], 'quantity' => 1,
    ]]))->toHaveKey('items.0.productId');

    $product->update(['available' => true]);
    $product->sizes()->where('slug', 'cup-large')->update(['available' => false]);
    expect(priceErrors([[
        'productId' => $product->id, 'containerId' => 'cup', 'sizeId' => 'cup-large',
        'flavorIds' => ['vanilla'], 'quantity' => 1,
    ]]))->toHaveKey('items.0.sizeId');
});

it('refuses a flavour that is not attached to the product', function () {
    $product = cupProduct();
    CatalogFactory::flavor('mango', ['family' => 'classic']);

    expect(priceErrors([[
        'productId' => $product->id, 'containerId' => 'cup', 'sizeId' => 'cup-large',
        'flavorIds' => ['mango'], 'quantity' => 1,
    ]])['items.0.flavorIds'])->toContain('غير متاحة لهذا المنتج');
});

// ─── addons ─────────────────────────────────────────────────────────────────

it('prices addons per unit so one line can carry different sets', function () {
    $product = CatalogFactory::flatList();
    CatalogFactory::item($product, 'shake', ['price' => 10, 'label' => 'ميلك شيك']);
    CatalogFactory::addon('extra-biscuit', null, ['label' => 'بسكوت', 'price' => 3, 'type' => 'counter', 'max_qty' => 10]);
    CatalogFactory::addon('nuts', null, ['label' => 'بندق', 'price' => 4]);

    $cart = priceCart([[
        'productId' => $product->id,
        'itemId'    => 'shake',
        'quantity'  => 3,
        'units'     => [
            ['addons' => [['id' => 'extra-biscuit', 'quantity' => 2]]],  // 6
            ['addons' => [['id' => 'nuts']]],                            // 4
            ['addons' => []],                                            // 0
        ],
    ]]);

    // 3 × 10 base + 10 of addons
    expect($cart->total())->toBe(40.0)
        ->and($cart->lines[0]->addonsTotalAgorot)->toBe(1000)
        ->and($cart->lines[0]->description)->toContain('إضافات: بسكوت ×2، بندق');
});

it('lets a product override a shared addon by slug', function () {
    $product = CatalogFactory::flatList();
    CatalogFactory::item($product, 'shake', ['price' => 10]);
    CatalogFactory::addon('nuts', null, ['price' => 4]);
    CatalogFactory::addon('nuts', $product, ['price' => 6]);

    $cart = priceCart([[
        'productId' => $product->id, 'itemId' => 'shake', 'quantity' => 1,
        'units'     => [['addons' => [['id' => 'nuts']]]],
    ]]);

    expect($cart->total())->toBe(16.0);
});

it('holds addons to their type, ceiling and availability', function () {
    $product = CatalogFactory::flatList();
    CatalogFactory::item($product, 'shake', ['price' => 10]);
    CatalogFactory::addon('extra-biscuit', null, ['type' => 'counter', 'max_qty' => 2]);
    CatalogFactory::addon('nuts', null, ['type' => 'toggle']);
    CatalogFactory::addon('gone', null, ['available' => false]);

    $base = ['productId' => $product->id, 'itemId' => 'shake', 'quantity' => 1];

    expect(priceErrors([$base + ['units' => [['addons' => [['id' => 'extra-biscuit', 'quantity' => 3]]]]]]))
        ->toHaveKey('items.0.units.0.addons.0.quantity');

    // A toggle has no quantity to raise.
    expect(priceErrors([$base + ['units' => [['addons' => [['id' => 'nuts', 'quantity' => 2]]]]]]))
        ->toHaveKey('items.0.units.0.addons.0.quantity');

    expect(priceErrors([$base + ['units' => [['addons' => [['id' => 'gone']]]]]])['items.0.units.0.addons.0.id'])
        ->toContain('غير متوفرة');

    expect(priceErrors([$base + ['units' => [['addons' => [['id' => 'ghost']]]]]])['items.0.units.0.addons.0.id'])
        ->toContain('غير معروفة');

    expect(priceErrors([$base + ['units' => [['addons' => [['id' => 'nuts'], ['id' => 'nuts']]]]]])['items.0.units.0.addons.1.id'])
        ->toContain('مكررة');
});

it('requires one unit per item when per-unit addons are sent', function () {
    $product = CatalogFactory::flatList();
    CatalogFactory::item($product, 'shake', ['price' => 10]);

    expect(priceErrors([[
        'productId' => $product->id, 'itemId' => 'shake', 'quantity' => 3,
        'units'     => [['addons' => []]],
    ]]))->toHaveKey('items.0.units');
});

// ─── arithmetic ─────────────────────────────────────────────────────────────

it('adds in agorot so a long cart does not drift off the receipt', function () {
    $product = CatalogFactory::flatList();
    CatalogFactory::item($product, 'a', ['price' => 0.1]);
    CatalogFactory::item($product, 'b', ['price' => 0.2]);

    $cart = priceCart([
        ['productId' => $product->id, 'itemId' => 'a', 'quantity' => 3],
        ['productId' => $product->id, 'itemId' => 'b', 'quantity' => 3],
    ]);

    expect($cart->totalAgorot())->toBe(90)
        ->and($cart->total())->toBe(0.9)
        ->and($cart->itemCount())->toBe(6);
});
