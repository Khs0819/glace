<?php

use App\Models\Customer;
use App\Services\Auth\CustomerAuthService;
use Tests\Support\CatalogFactory;

/**
 * The checkout shape the storefront actually sends: choices as flat ids on the
 * item, rather than inside `selections[]`.
 *
 * Both shapes are equally valid — CartItemNormalizer has always read either —
 * but a rule has to exist for every one of them, because `validated()` returns
 * only the keys the rules name. An id with no rule is dropped in silence, and
 * the pricer then rejects the line for a field the client did send.
 */

beforeEach(function () {
    $this->customer = Customer::create(['name' => 'أحمد علي', 'phone' => '0599123456']);
    $this->headers  = ['Authorization' => app(CustomerAuthService::class)->issueToken($this->customer)];

    // A flat-list product: لقيمات with three fillings.
    $this->flat = CatalogFactory::flatList('loqaimat', ['name' => 'لقيمات']);
    CatalogFactory::item($this->flat, 'arabian', ['label' => 'عربية', 'price' => 15]);
    CatalogFactory::item($this->flat, 'pistachio', ['label' => 'بيستاشيو', 'price' => 20]);

    // A builder: a cup with a container and a size.
    $this->builder = CatalogFactory::builder('cup', ['name' => 'بوظة كاسة']);
    CatalogFactory::container($this->builder, 'plastic', ['label' => 'بلاستيك']);
    CatalogFactory::size($this->builder, 'plastic-half', ['classic' => 12], [
        'label' => 'نصف كيلو', 'container_slug' => 'plastic',
    ]);
    $this->builder->flavors()->attach(CatalogFactory::flavor('mango')->id);
});

function flatOrder(array $item): array
{
    return [
        'items'          => json_encode([$item]),
        'paymentMethod'  => 'cash',
        'deliveryMethod' => 'pickup',
    ];
}

it('accepts a flat-list line identified by itemId alone', function () {
    // This is attempt 3 from the storefront's report. It failed only because
    // `itemId` had no rule and was stripped before the pricer saw it.
    $response = test()->post('/api/orders', flatOrder([
        'productId' => $this->flat->id,
        'itemId'    => 'arabian',
        'quantity'  => 2,
    ]), $this->headers)->assertCreated();

    $response->assertJsonPath('total', fn ($v) => (float) $v === 30.0)
        ->assertJsonPath('items.0.description', fn ($v) => str_contains((string) $v, 'عربية'));
});

it('accepts a builder line identified by containerId and sizeId', function () {
    test()->post('/api/orders', flatOrder([
        'productId'   => $this->builder->id,
        'containerId' => 'plastic',
        'sizeId'      => 'plastic-half',
        'flavorIds'   => ['mango'],
        'quantity'    => 1,
    ]), $this->headers)->assertCreated();
});

it('still accepts the documented selections[] shape', function () {
    // The contract's own example must not regress while the flat shape is
    // being made to work.
    test()->post('/api/orders', flatOrder([
        'productId'  => $this->flat->id,
        'selections' => [['kind' => 'item', 'id' => 'pistachio', 'qty' => 1]],
        'quantity'   => 1,
    ]), $this->headers)
        ->assertCreated()
        ->assertJsonPath('total', fn ($v) => (float) $v === 20.0);
});

it('names the field when the slug is real but not on this product', function () {
    test()->post('/api/orders', flatOrder([
        'productId' => $this->flat->id,
        'itemId'    => 'no-such-filling',
        'quantity'  => 1,
    ]), $this->headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors('items.0.itemId');
});

it('still refuses a line that names no choice at all', function () {
    test()->post('/api/orders', flatOrder([
        'productId' => $this->flat->id,
        'quantity'  => 1,
    ]), $this->headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors('items.0.itemId');
});
