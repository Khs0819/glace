<?php

use App\Models\Order;
use Tests\Support\CatalogFactory;

function shakeProduct(): App\Models\Product
{
    $product = CatalogFactory::flatList('milkshake', ['name' => 'ميلك شيك']);
    CatalogFactory::item($product, 'vanilla', ['label' => 'فانيلا', 'price' => 12]);
    CatalogFactory::addon('nuts', null, ['label' => 'بندق', 'price' => 4]);

    return $product;
}

/** @return array<string, mixed> */
function cartPayload(App\Models\Product $product, array $overrides = []): array
{
    return array_merge([
        'customer' => ['name' => 'أحمد', 'phone' => '0599002286'],
        'items'    => [[
            'productId' => $product->id,
            'itemId'    => 'vanilla',
            'quantity'  => 2,
            'units'     => [
                ['addons' => [['id' => 'nuts']]],
                ['addons' => []],
            ],
        ]],
    ], $overrides);
}

// ─── quote ──────────────────────────────────────────────────────────────────

it('quotes a cart without keeping anything', function () {
    $product = shakeProduct();

    $response = $this->postJson('/api/checkout/quote', [
        'items' => cartPayload($product)['items'],
    ]);

    $response->assertOk()
        ->assertJsonPath('total', 28)          // 2×12 + 4
        ->assertJsonPath('currency', 'ILS')
        ->assertJsonPath('items.0.lineTotal', 28);

    expect(Order::count())->toBe(0);
});

it('rejects a cart the catalog cannot satisfy, pointing at the field', function () {
    $product = shakeProduct();

    $this->postJson('/api/checkout/quote', [
        'items' => [['productId' => $product->id, 'itemId' => 'ghost', 'quantity' => 1]],
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items.0.itemId']);
});

it('rejects an empty cart', function () {
    $this->postJson('/api/checkout/quote', ['items' => []])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['items']);
});

// ─── place ──────────────────────────────────────────────────────────────────

it('places an order priced from the catalog and hands back the token once', function () {
    $product = shakeProduct();

    $response = $this->postJson('/api/checkout/orders', cartPayload($product));

    $response->assertCreated()
        ->assertJsonPath('order.total', 28)
        ->assertJsonPath('order.paymentStatus', 'pending')
        ->assertJsonPath('order.customer.name', 'أحمد')
        ->assertJsonPath('order.items.0.description', 'فانيلا + إضافات: بندق')
        ->assertJsonStructure(['token', 'order' => ['id', 'reference', 'items', 'total']]);

    $order = Order::sole();

    expect($order->total)->toBe(28.0)
        ->and($order->items)->toHaveCount(1)
        ->and($order->items->first()->unit_price)->toBe(12.0)
        ->and($order->items->first()->addons_total)->toBe(4.0)
        ->and($response->json('token'))->toBe($order->public_token)
        // The reference must be sayable down the phone.
        ->and($order->reference)->toMatch('/^ORD-[23456789ABCDEFGHJKMNPQRSTWXYZ]{6}$/');
});

it('ignores any price the client tries to send', function () {
    $product = shakeProduct();

    $payload                          = cartPayload($product);
    $payload['items'][0]['unitPrice'] = 0.01;
    $payload['items'][0]['lineTotal'] = 0.01;
    $payload['total']                 = 0.01;

    $this->postJson('/api/checkout/orders', $payload)
        ->assertCreated()
        ->assertJsonPath('order.total', 28);
});

it('snapshots the item so a later catalog edit cannot rewrite history', function () {
    $product = shakeProduct();

    $this->postJson('/api/checkout/orders', cartPayload($product))->assertCreated();

    $product->items()->where('slug', 'vanilla')->update(['label' => 'فانيلا مطوّرة', 'price' => 99]);
    $product->update(['name' => 'اسم جديد']);

    $item = Order::sole()->items->first();

    expect($item->product_name)->toBe('ميلك شيك')
        ->and($item->unit_price)->toBe(12.0)
        ->and($item->description)->toContain('فانيلا')
        ->and($item->description)->not->toContain('مطوّرة');
});

it('requires customer details', function () {
    $product = shakeProduct();

    $this->postJson('/api/checkout/orders', ['items' => cartPayload($product)['items']])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['customer']);
});

// ─── show ───────────────────────────────────────────────────────────────────

it('serves an order only to whoever holds its token', function () {
    $product = shakeProduct();
    $created = $this->postJson('/api/checkout/orders', cartPayload($product))->assertCreated();

    $id    = $created->json('order.id');
    $token = $created->json('token');

    $this->getJson("/api/checkout/orders/{$id}?token={$token}")
        ->assertOk()
        ->assertJsonPath('reference', $created->json('order.reference'));

    // A wrong or missing token must not even confirm the order exists.
    $this->getJson("/api/checkout/orders/{$id}?token=wrong")->assertNotFound();
    $this->getJson("/api/checkout/orders/{$id}")->assertNotFound();
});

it('never echoes the token back after creation', function () {
    $product = shakeProduct();
    $created = $this->postJson('/api/checkout/orders', cartPayload($product))->assertCreated();

    $body = $this->getJson("/api/checkout/orders/{$created->json('order.id')}?token={$created->json('token')}")
        ->assertOk()
        ->json();

    expect($body)->not->toHaveKey('token')
        ->and($body)->not->toHaveKey('public_token')
        ->and(json_encode($body))->not->toContain($created->json('token'));
});
