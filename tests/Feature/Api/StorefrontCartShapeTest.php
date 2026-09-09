<?php

use App\Models\Customer;
use App\Models\Order;
use App\Services\Auth\CustomerAuthService;
use Tests\Support\CatalogFactory;

/**
 * The cart line exactly as the storefront sends it.
 *
 * Written from a browser capture of a real checkout, because the payload the
 * frontend actually posts and the payload the contract describes had drifted
 * apart, and every test here was passing against the contract's shape while
 * every real order was rejected.
 */

beforeEach(function () {
    fakePublicDisk();

    $this->customer = Customer::create(['name' => 'أحمد علي', 'phone' => '0599123456']);
    $this->headers  = ['Authorization' => app(CustomerAuthService::class)->issueToken($this->customer)];

    $this->flat = CatalogFactory::flatList('loqaimat', ['name' => 'لقيمات']);
    CatalogFactory::item($this->flat, 'nutella', ['label' => 'نوتيلا', 'price' => 25]);
    CatalogFactory::item($this->flat, 'plain', ['label' => 'سادة', 'price' => 20]);
});

/** A line in the storefront's own shape: labels for display, no ids. */
function browserLine(array $overrides = []): array
{
    return array_merge([
        'productId'      => test()->flat->id,
        'name'           => 'لقيمات — نوتيلا',
        'image'          => 'https://back.glaceelameer.com/storage/items/x.webp',
        'type'           => 'نوتيلا',
        'selections'     => [],
        'addonTotal'     => 0,
        'unitPrice'      => 25,
        'quantity'       => 1,
        'flatSelections' => [],
        'flatAddonTotal' => 0,
        // A React key. Not an identifier the server should ever read.
        'id'             => '1788802147577-1g0e3',
    ], $overrides);
}

function postBrowserCart(array $line, array $overrides = [])
{
    return test()->post('/api/orders', array_merge([
        'items'          => json_encode([$line]),
        'paymentMethod'  => 'cash',
        'deliveryMethod' => 'pickup',
    ], $overrides), test()->headers);
}

it('prices a flat-list line that names its variant instead of identifying it', function () {
    // The storefront holds no id for "نوتيلا" — only the display label in
    // `type`. Refusing this made every flat-list product unorderable.
    $response = postBrowserCart(browserLine())->assertCreated();

    expect((float) $response->json('total'))->toBe(25.0);
});

it('charges the variant that was named, not the first on the list', function () {
    $response = postBrowserCart(browserLine(['type' => 'سادة']))->assertCreated();

    expect((float) $response->json('total'))->toBe(20.0);
});

it('keeps the explicit id when one is sent, instead of dropping it', function () {
    // The request used to carry no rule for `itemId`, and validated() deletes
    // what it has no rule for — so a client doing exactly the right thing had
    // its choice thrown away and was told it had made none.
    $response = postBrowserCart(browserLine(['itemId' => 'plain', 'type' => 'نوتيلا']))
        ->assertCreated();

    expect((float) $response->json('total'))->toBe(20.0);
});

it('refuses a name that matches nothing on the product', function () {
    postBrowserCart(browserLine(['type' => 'مانجا']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('items.0.itemId');
});

it('refuses to guess when two variants share a name', function () {
    // Two prices behind one word: picking either is a coin toss the customer
    // pays for.
    CatalogFactory::item($this->flat, 'nutella-large', ['label' => 'نوتيلا', 'price' => 40]);

    postBrowserCart(browserLine())
        ->assertStatus(422)
        ->assertJsonValidationErrors('items.0.itemId');
});

it('never reads the price the browser put on the line', function () {
    $response = postBrowserCart(browserLine(['unitPrice' => 1, 'addonTotal' => 999]))
        ->assertCreated();

    expect((float) $response->json('total'))->toBe(25.0);
});

it('is deterministic — the same line prices the same way every time', function () {
    // The field report described the same payload succeeding once and failing
    // three times. Nothing here is stateful; this pins that down.
    $totals = collect(range(1, 5))->map(
        fn () => (float) postBrowserCart(browserLine())->assertCreated()->json('total'),
    );

    expect($totals->unique()->values()->all())->toBe([25.0])
        ->and(Order::count())->toBe(5);
});

// ─── builder products ───────────────────────────────────────────────────────

it('prices a builder line sent with display labels for size and container', function () {
    $family = CatalogFactory::builder('family', ['name' => 'بوظة عائلي']);
    CatalogFactory::container($family, 'plastic', ['label' => 'بلاستيك']);
    CatalogFactory::size($family, 'plastic-half', ['classic' => 25], [
        'label' => '1/2 لتر', 'max_balls' => 8, 'container_slug' => 'plastic',
    ]);
    CatalogFactory::flavor('banana', ['name_ar' => 'موز']);
    $family->flavors()->sync(['banana']);

    // The capture from the report: size and container are the words shown on
    // screen, and the only flavour id rides in `selections`.
    $response = test()->post('/api/orders', [
        'items' => json_encode([[
            'productId'  => $family->id,
            'name'       => 'بلاستيك',
            'size'       => '1/2 لتر',
            'container'  => 'بلاستيك',
            'type'       => 'كلاسيك',
            'selections' => [
                ['kind' => 'flavor', 'id' => 'banana', 'label' => 'موز', 'qty' => 8, 'unitPrice' => 0],
            ],
            'unitPrice'  => 25,
            'quantity'   => 1,
        ]]),
        'paymentMethod'  => 'cash',
        'deliveryMethod' => 'pickup',
    ], $this->headers)->assertCreated();

    expect((float) $response->json('total'))->toBe(25.0);
});

it('honours an explicit container id, which used to be discarded', function () {
    $family = CatalogFactory::builder('family2', ['name' => 'بوظة عائلي']);
    CatalogFactory::container($family, 'plastic', ['label' => 'بلاستيك']);
    CatalogFactory::container($family, 'foam', ['label' => 'فلين']);
    CatalogFactory::size($family, 'half', ['classic' => 30], ['label' => 'نصف لتر', 'max_balls' => 0]);

    $response = test()->post('/api/orders', [
        'items' => json_encode([[
            'productId'   => $family->id,
            'containerId' => 'foam',
            'sizeId'      => 'half',
            'selections'  => [],
            'quantity'    => 1,
        ]]),
        'paymentMethod'  => 'cash',
        'deliveryMethod' => 'pickup',
    ], $this->headers)->assertCreated();

    expect((float) $response->json('total'))->toBe(30.0);
});

// ─── which account was paid ─────────────────────────────────────────────────

it('records the shop account a transfer was made to', function () {
    $account = App\Models\PaymentAccount::create([
        'method' => 'jawwal-manual', 'holder_name' => 'يوسف عماد',
        'primary_label' => 'رقم جوال باي', 'primary_value' => '0599000111', 'active' => true,
    ]);

    $response = postBrowserCart(browserLine(), [
        'paymentMethod'    => 'jawwal-manual',
        'receiptNote'      => 'حوّلت المبلغ',
        'paymentAccountId' => $account->getKey(),
    ])->assertCreated();

    expect(Order::where('reference', $response->json('reference'))->first()->payment_account_id)
        ->toBe($account->getKey());
});

it('refuses an account that belongs to a different payment method', function () {
    // A Jawwal Pay receipt filed against the bank account sends whoever
    // verifies it to the wrong statement, where they find nothing.
    $bank = App\Models\PaymentAccount::create([
        'method' => 'bop', 'holder_name' => 'جلاسيه الأمير',
        'primary_label' => 'رقم الحساب', 'primary_value' => '123', 'active' => true,
    ]);

    postBrowserCart(browserLine(), [
        'paymentMethod'    => 'jawwal-manual',
        'receiptNote'      => 'حوّلت المبلغ',
        'paymentAccountId' => $bank->getKey(),
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('paymentAccountId');
});

it('accepts an order with no account named, as before', function () {
    postBrowserCart(browserLine(), [
        'paymentMethod' => 'jawwal-manual',
        'receiptNote'   => 'حوّلت المبلغ',
    ])->assertCreated();
});
