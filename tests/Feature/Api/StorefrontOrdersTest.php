<?php

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Services\Auth\CustomerAuthService;
use App\Services\Checkout\Money;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CatalogFactory;

/**
 * Storefront checkout and order tracking (handoff 12).
 */

beforeEach(function () {
    fakePublicDisk();

    DeliveryZone::create(['id' => 'rimal', 'name' => 'الرمال', 'fee' => 10]);

    $this->customer = Customer::create(['name' => 'أحمد علي', 'phone' => '0599123456']);
    $this->headers  = ['Authorization' => app(CustomerAuthService::class)->issueToken($this->customer)];

    $this->address = $this->customer->addresses()->create([
        'type' => 'home', 'label' => 'المنزل', 'name' => 'أحمد علي', 'phone' => '0599123456',
        'city' => 'غزة', 'zone_id' => 'rimal', 'street' => 'شارع الجلاء',
        'landmark' => 'بجانب صيدلية النور', 'is_default' => true,
    ]);

    // 12 ₪ a unit, with a 4 ₪ per-unit addon and a 3 ₪ counter addon for lines.
    $this->product = CatalogFactory::flatList('milkshake', ['name' => 'ميلك شيك']);
    CatalogFactory::item($this->product, 'vanilla', ['label' => 'فانيلا', 'price' => 12]);
    CatalogFactory::addon('nuts', null, ['label' => 'بندق', 'price' => 4]);
    CatalogFactory::addon('extra-biscuit', null, [
        'label' => 'بسكوت مخروط', 'price' => 3, 'type' => 'counter', 'max_qty' => 10,
    ]);
});

/**
 * The storefront's own shape: `items` is a JSON string inside multipart, and
 * every price on it is decoration the server must ignore.
 */
function storefrontPayload(array $overrides = [], array $itemOverrides = []): array
{
    $items = [array_merge([
        'productId'  => test()->product->id,
        'name'       => 'ميلك شيك',
        'type'       => 'فانيلا',
        'selections' => [
            ['kind' => 'item', 'id' => 'vanilla', 'label' => 'فانيلا', 'qty' => 1, 'unitPrice' => 12],
        ],
        'addonTotal' => 0,
        'unitPrice'  => 999,   // ignored
        'quantity'   => 2,
    ], $itemOverrides)];

    return array_merge([
        'items'          => json_encode($items),
        'paymentMethod'  => 'cash',
        'deliveryMethod' => 'pickup',
    ], $overrides);
}

// ─── server-side pricing ────────────────────────────────────────────────────

it('prices the cart from the catalog and ignores every number the client sends', function () {
    $response = test()->post('/api/orders', storefrontPayload([
        'subtotal' => 1,   // all
        'discount' => 999, // of
        'total'    => 1,   // these
    ]), $this->headers)->assertCreated();

    // 12 × 2, not the 999 unit price or the 1 total that was sent.
    $response->assertJsonPath('subtotal', fn ($v) => (float) $v === 24.0)
        ->assertJsonPath('discount', fn ($v) => (float) $v === 0.0)
        ->assertJsonPath('total', fn ($v) => (float) $v === 24.0);
});

it('prices per-unit addons against the quantity', function () {
    test()->post('/api/orders', storefrontPayload(itemOverrides: [
        'selections' => [
            ['kind' => 'item', 'id' => 'vanilla', 'label' => 'فانيلا', 'qty' => 1],
            ['kind' => 'addon', 'id' => 'nuts', 'label' => 'بندق', 'qty' => 1, 'unitPrice' => 4],
        ],
    ]), $this->headers)
        ->assertCreated()
        // (12 × 2) + (4 × 2 units) = 32
        ->assertJsonPath('subtotal', fn ($v) => (float) $v === 32.0);
});

it('charges a flat addon once for the line, not once per unit', function () {
    test()->post('/api/orders', storefrontPayload(itemOverrides: [
        'flatSelections' => [
            ['kind' => 'addon', 'id' => 'extra-biscuit', 'label' => 'بسكوت', 'qty' => 4, 'unitPrice' => 3],
        ],
        'flatAddonTotal' => 12,
    ]), $this->headers)
        ->assertCreated()
        // (12 × 2) + (3 × 4, once) = 36 — not 48.
        ->assertJsonPath('subtotal', fn ($v) => (float) $v === 36.0);
});

it('refuses an item that is not in the catalog', function () {
    test()->post('/api/orders', storefrontPayload(itemOverrides: [
        'selections' => [['kind' => 'item', 'id' => 'nope', 'label' => 'وهمي', 'qty' => 1]],
    ]), $this->headers)->assertStatus(422);
});

it('refuses an empty cart', function () {
    test()->post('/api/orders', storefrontPayload(['items' => json_encode([])]), $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.items.0', 'السلة فارغة');
});

// ─── the response shape ─────────────────────────────────────────────────────

it('returns the order in the shape the storefront reads', function () {
    $response = test()->post('/api/orders', storefrontPayload([
        'deliveryMethod' => 'delivery',
        'paymentMethod'  => 'jawwal-manual',
        'addressId'      => $this->address->id,
        'receiptNote'    => 'حوّلت من بنك فلسطين',
    ]), $this->headers)->assertCreated();

    $response->assertJsonStructure([
        'id', 'items', 'subtotal', 'discount', 'total', 'paymentMethod',
        'deliveryMethod', 'address', 'status', 'createdAt', 'preparationTime',
    ])
        ->assertJsonPath('status', 'قيد المراجعة')
        ->assertJsonPath('deliveryMethod', 'delivery')
        ->assertJsonPath('address.area', 'الرمال')
        ->assertJsonPath('items.0.quantity', 2)
        ->assertJsonPath('items.0.unitPrice', fn ($v) => (float) $v === 12.0);

    // "ORD-M3K2A1" — the short reference, never the internal UUID.
    expect($response->json('id'))->toMatch('/^ORD-[A-Z0-9]{6}$/');
});

it('always opens an order under review, whatever was paid', function () {
    foreach (['cash', 'wallet', 'jawwal-manual'] as $method) {
        if ($method === 'wallet') {
            app(App\Services\Storefront\WalletService::class)
                ->credit($this->customer, Money::toAgorot(500), 'رصيد اختبار');
        }

        $response = test()->post('/api/orders', storefrontPayload([
            'paymentMethod' => $method,
            'receiptNote'   => 'ملاحظة',
        ]), $this->headers)->assertCreated();

        $response->assertJsonPath('status', 'قيد المراجعة');
    }
});

// ─── the address snapshot ───────────────────────────────────────────────────

it('freezes the address onto the order, zone name and all', function () {
    test()->post('/api/orders', storefrontPayload([
        'deliveryMethod' => 'delivery',
        'paymentMethod'  => 'jawwal-manual',
        'receiptNote'    => 'ملاحظة',
        'addressId'      => $this->address->id,
    ]), $this->headers)->assertCreated();

    $order = Order::sole();

    // Editing the address afterwards must not rewrite what was ordered.
    $this->address->update(['street' => 'شارع مختلف']);
    DeliveryZone::whereKey('rimal')->update(['name' => 'اسم مختلف']);

    expect($order->fresh()->address['street'])->toBe('شارع الجلاء')
        ->and($order->fresh()->address['area'])->toBe('الرمال');
});

it('refuses a delivery to an address belonging to somebody else', function () {
    $other = Customer::create(['name' => 'آخر', 'phone' => '0598000000']);
    $theirs = $other->addresses()->create([
        'type' => 'home', 'label' => 'المنزل', 'name' => 'آخر', 'phone' => '0598000000',
        'city' => 'غزة', 'street' => 'شارع آخر', 'is_default' => true,
    ]);

    test()->post('/api/orders', storefrontPayload([
        'deliveryMethod' => 'delivery',
        'paymentMethod'  => 'jawwal-manual',
        'receiptNote'    => 'ملاحظة',
        'addressId'      => $theirs->id,
    ]), $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.addressId.0', 'العنوان غير موجود');
});

it('insists on an address for a delivery', function () {
    test()->post('/api/orders', storefrontPayload([
        'deliveryMethod' => 'delivery',
        'paymentMethod'  => 'jawwal-manual',
        'receiptNote'    => 'ملاحظة',
    ]), $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.addressId.0', 'اختر عنوان التوصيل');
});

it('adds the delivery fee for the address zone', function () {
    test()->post('/api/orders', storefrontPayload([
        'deliveryMethod' => 'delivery',
        'paymentMethod'  => 'jawwal-manual',
        'receiptNote'    => 'ملاحظة',
        'addressId'      => $this->address->id,
    ]), $this->headers)
        ->assertCreated()
        ->assertJsonPath('subtotal', fn ($v) => (float) $v === 24.0)
        ->assertJsonPath('deliveryFee', fn ($v) => (float) $v === 10.0)
        ->assertJsonPath('total', fn ($v) => (float) $v === 34.0);
});

// ─── coupons ────────────────────────────────────────────────────────────────

it('recomputes the discount from the code, never from the client', function () {
    Coupon::create(['code' => 'GLACE10', 'type' => 'fixed', 'value' => 10]);

    test()->post('/api/orders', storefrontPayload([
        'couponCode' => 'GLACE10',
        'discount'   => 999, // ignored
    ]), $this->headers)
        ->assertCreated()
        ->assertJsonPath('discount', fn ($v) => (float) $v === 10.0)
        ->assertJsonPath('total', fn ($v) => (float) $v === 14.0);
});

it('computes a percentage coupon into a final shekel figure', function () {
    Coupon::create(['code' => 'HALF', 'type' => 'percent', 'value' => 25]);

    test()->post('/api/orders', storefrontPayload(['couponCode' => 'HALF']), $this->headers)
        ->assertCreated()
        ->assertJsonPath('discount', fn ($v) => (float) $v === 6.0)   // 25% of 24
        ->assertJsonPath('total', fn ($v) => (float) $v === 18.0);
});

it('refuses an order whose coupon expired between the cart and checkout', function () {
    Coupon::create([
        'code' => 'GONE', 'type' => 'fixed', 'value' => 10,
        'expires_at' => now()->subDay(),
    ]);

    // Silently dropping it would charge more than the cart screen promised.
    test()->post('/api/orders', storefrontPayload(['couponCode' => 'GONE']), $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.couponCode.0', 'الكوبون غير صالح أو منتهي');
});

it('counts a redemption only once an order exists', function () {
    $coupon = Coupon::create(['code' => 'ONCE', 'type' => 'fixed', 'value' => 5, 'usage_limit' => 1]);

    // A cart preview must not burn a single-use code.
    test()->postJson('/api/cart/apply-coupon', ['code' => 'ONCE', 'subtotal' => 24], $this->headers)
        ->assertOk()->assertJsonPath('valid', true);

    expect($coupon->fresh()->used_count)->toBe(0);

    test()->post('/api/orders', storefrontPayload(['couponCode' => 'ONCE']), $this->headers)->assertCreated();

    expect($coupon->fresh()->used_count)->toBe(1);

    test()->post('/api/orders', storefrontPayload(['couponCode' => 'ONCE']), $this->headers)
        ->assertStatus(422);
});

it('never lets a discount turn an order into a payout', function () {
    Coupon::create(['code' => 'HUGE', 'type' => 'fixed', 'value' => 500]);

    test()->post('/api/orders', storefrontPayload(['couponCode' => 'HUGE']), $this->headers)
        ->assertCreated()
        ->assertJsonPath('discount', fn ($v) => (float) $v === 24.0)
        ->assertJsonPath('total', fn ($v) => (float) $v === 0.0);
});

// ─── delivery restrictions ──────────────────────────────────────────────────

it('refuses to deliver an in-store-only product', function () {
    $this->product->update(['in_store_only' => true]);

    $response = test()->post('/api/orders', storefrontPayload([
        'deliveryMethod' => 'delivery',
        'paymentMethod'  => 'jawwal-manual',
        'receiptNote'    => 'ملاحظة',
        'addressId'      => $this->address->id,
    ]), $this->headers)->assertStatus(422);

    expect($response->json('errors')['items.0'][0])->toBe('«ميلك شيك» متاح للاستلام من المحل فقط');
});

it('still allows pickup of an in-store-only product', function () {
    $this->product->update(['in_store_only' => true]);

    test()->post('/api/orders', storefrontPayload(['deliveryMethod' => 'pickup']), $this->headers)
        ->assertCreated();
});

it('refuses to deliver a product the shop has blocked outright', function () {
    config(['storefront.delivery.blocked_products' => ['milkshake']]);

    test()->post('/api/orders', storefrontPayload([
        'deliveryMethod' => 'delivery',
        'paymentMethod'  => 'jawwal-manual',
        'receiptNote'    => 'ملاحظة',
        'addressId'      => $this->address->id,
    ]), $this->headers)
        ->assertStatus(422);
});

it('keeps cash and card inside the shop', function () {
    foreach (['cash', 'visa'] as $method) {
        test()->post('/api/orders', storefrontPayload([
            'paymentMethod'  => $method,
            'deliveryMethod' => 'delivery',
            'addressId'      => $this->address->id,
        ]), $this->headers)
            ->assertStatus(422)
            ->assertJsonPath('errors.paymentMethod.0', 'الدفع نقداً أو بالبطاقة متاح داخل المحل فقط');
    }
});

// ─── receipts ───────────────────────────────────────────────────────────────

it('stores an uploaded receipt as a file and returns its url', function () {
    $response = test()->post('/api/orders', storefrontPayload([
        'paymentMethod' => 'jawwal-manual',
        'receiptImage'  => UploadedFile::fake()->image('receipt.png'),
    ]), $this->headers)->assertCreated();

    // A URL, not base64 in a column (handoff 12).
    expect($response->json('receiptImage'))->toStartWith('http')
        ->and(Order::sole()->receipt_image)->toStartWith('receipts/');
});

it('accepts a note instead of an image', function () {
    test()->post('/api/orders', storefrontPayload([
        'paymentMethod' => 'bop',
        'receiptNote'   => 'حوّلت من حساب بنك فلسطين 12345',
    ]), $this->headers)
        ->assertCreated()
        ->assertJsonPath('receiptNote', 'حوّلت من حساب بنك فلسطين 12345');
});

it('insists on some proof for a manual transfer', function () {
    test()->post('/api/orders', storefrontPayload(['paymentMethod' => 'paypal']), $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.receiptImage.0', 'أرفق صورة إيصال التحويل أو اكتب ملاحظة توضح التحويل');
});

it('refuses a receipt that is not an image', function () {
    test()->post('/api/orders', storefrontPayload([
        'paymentMethod' => 'jawwal-manual',
        'receiptImage'  => UploadedFile::fake()->create('malware.php', 10, 'application/x-php'),
    ]), $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.receiptImage.0', 'يجب أن يكون الملف صورة (JPG أو PNG أو WEBP)');
});

it('refuses a receipt over the size cap', function () {
    test()->post('/api/orders', storefrontPayload([
        'paymentMethod' => 'jawwal-manual',
        'receiptImage'  => UploadedFile::fake()->image('huge.jpg')->size(6 * 1024),
    ]), $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.receiptImage.0', 'حجم الصورة كبير جداً — الحد الأقصى 5 ميجابايت');
});

// ─── wallet payment ─────────────────────────────────────────────────────────

it('takes the total off the wallet and settles the order', function () {
    app(App\Services\Storefront\WalletService::class)
        ->credit($this->customer, Money::toAgorot(100), 'رصيد اختبار');

    test()->post('/api/orders', storefrontPayload(['paymentMethod' => 'wallet']), $this->headers)
        ->assertCreated()
        ->assertJsonPath('paymentStatus', 'paid');

    expect($this->customer->wallet->fresh()->balance)->toBe(76.0);
});

it('refuses a wallet order the balance cannot cover, and writes no order', function () {
    app(App\Services\Storefront\WalletService::class)
        ->credit($this->customer, Money::toAgorot(5), 'رصيد اختبار');

    test()->post('/api/orders', storefrontPayload(['paymentMethod' => 'wallet']), $this->headers)
        ->assertStatus(409);

    // The debit and the order share one transaction: neither happened.
    expect(Order::count())->toBe(0)
        ->and($this->customer->wallet->fresh()->balance)->toBe(5.0);
});

// ─── automatic jawwal ───────────────────────────────────────────────────────

it('sends a jawwal code before any order exists', function () {
    test()->postJson('/api/orders/jawwal/send-code', ['phone' => '0599123456', 'amount' => 24])
        ->assertOk()->assertJson(['sent' => true]);

    expect(App\Models\OtpCode::where('purpose', 'jawwal_payment')->count())->toBe(1);
});

it('rate-limits jawwal codes to one number', function () {
    test()->postJson('/api/orders/jawwal/send-code', ['phone' => '0599123456', 'amount' => 24])->assertOk();
    test()->postJson('/api/orders/jawwal/send-code', ['phone' => '0599123456', 'amount' => 24])->assertStatus(429);
});

it('refuses a jawwal order with a wrong code and creates nothing', function () {
    test()->postJson('/api/orders/jawwal/send-code', ['phone' => '0599123456', 'amount' => 24])->assertOk();

    test()->post('/api/orders', storefrontPayload([
        'paymentMethod' => 'jawwal',
        'jawwalPhone'   => '0599123456',
        'jawwalCode'    => '000000',
    ]), $this->headers)->assertStatus(422);

    expect(Order::count())->toBe(0);
});

it('refuses a jawwal code approved for a different amount', function () {
    // The customer approved 99 ₪; the cart is 24 ₪. They must approve again.
    app(App\Services\Auth\OtpService::class)->send(
        '0599123456',
        App\Models\OtpCode::PURPOSE_JAWWAL,
        ['amount' => Money::toAgorot(99)],
    );

    $code = App\Models\OtpCode::where('purpose', 'jawwal_payment')->sole();
    $code->update(['code_hash' => Illuminate\Support\Facades\Hash::make('482913')]);

    test()->post('/api/orders', storefrontPayload([
        'paymentMethod' => 'jawwal',
        'jawwalPhone'   => '0599123456',
        'jawwalCode'    => '482913',
    ]), $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.jawwalCode.0', 'تغيّرت قيمة الطلب — يرجى طلب رمز تأكيد جديد');

    expect(Order::count())->toBe(0);
});

// ─── listing & tracking ─────────────────────────────────────────────────────

it('paginates the customer own orders, newest first', function () {
    for ($i = 0; $i < 3; $i++) {
        test()->post('/api/orders', storefrontPayload(), $this->headers)->assertCreated();
    }

    $response = test()->getJson('/api/orders?page=1&perPage=2', $this->headers)->assertOk();

    $response->assertJsonPath('total', 3)
        ->assertJsonPath('page', 1)
        ->assertJsonPath('perPage', 2)
        ->assertJsonPath('totalPages', 2)
        ->assertJsonCount(2, 'items');
});

it('shows a customer only their own orders', function () {
    test()->post('/api/orders', storefrontPayload(), $this->headers)->assertCreated();

    $other   = Customer::create(['name' => 'آخر', 'phone' => '0598000000']);
    $headers = ['Authorization' => app(CustomerAuthService::class)->issueToken($other)];

    test()->getJson('/api/orders', $headers)->assertOk()->assertJsonPath('total', 0);
});

it('will not show one customer another customer order', function () {
    $reference = test()->post('/api/orders', storefrontPayload(), $this->headers)
        ->assertCreated()->json('id');

    $other   = Customer::create(['name' => 'آخر', 'phone' => '0598000000']);
    $headers = ['Authorization' => app(CustomerAuthService::class)->issueToken($other)];

    test()->getJson("/api/orders/{$reference}", $headers)->assertStatus(404);
});

it('refuses a guest collection order with nobody to call', function () {
    // Neither an account nor an address means no name and no number — an
    // order the shop could never act on.
    test()->post('/api/orders', storefrontPayload())
        ->assertStatus(422)
        ->assertJsonPath('errors.customer.0', 'سجّل الدخول أو أدخل اسمك ورقم هاتفك لإتمام الطلب');
});

it('lets a guest order when they supply their own name and number', function () {
    test()->post('/api/orders', storefrontPayload([
        'customer' => ['name' => 'زائر', 'phone' => '0599111222'],
    ]))
        ->assertCreated()
        ->assertJsonPath('customer.name', 'زائر')
        ->assertJsonPath('customer.phone', '0599111222');
});

it('lets a guest track the order they just placed, with its token', function () {
    $created = test()->post('/api/orders', storefrontPayload([
        'customer' => ['name' => 'زائر', 'phone' => '0599111222'],
    ]))->assertCreated();

    $reference = $created->json('id');
    $token     = $created->json('token');

    test()->getJson("/api/orders/{$reference}?token={$token}")->assertOk();
    test()->getJson("/api/orders/{$reference}")->assertStatus(404);
});

// ─── cancelling ─────────────────────────────────────────────────────────────

it('cancels a live order and keeps the reason', function () {
    $reference = test()->post('/api/orders', storefrontPayload(), $this->headers)->json('id');

    test()->postJson("/api/orders/{$reference}/cancel", ['reason' => 'غيرت رأيي'], $this->headers)
        ->assertOk()
        ->assertJsonPath('order.status', 'ملغي')
        ->assertJsonPath('order.cancelReason', 'غيرت رأيي');
});

it('refuses to cancel an order that is already closed', function () {
    $reference = test()->post('/api/orders', storefrontPayload(), $this->headers)->json('id');

    Order::where('reference', $reference)->update(['status' => Order::FULFILMENT_DELIVERED]);

    test()->postJson("/api/orders/{$reference}/cancel", ['reason' => 'متأخر'], $this->headers)
        ->assertStatus(409);
});

it('does not refund on cancellation — that is a separate human decision', function () {
    app(App\Services\Storefront\WalletService::class)
        ->credit($this->customer, Money::toAgorot(100), 'رصيد اختبار');

    $reference = test()->post('/api/orders', storefrontPayload(['paymentMethod' => 'wallet']), $this->headers)
        ->json('id');

    test()->postJson("/api/orders/{$reference}/cancel", [], $this->headers)->assertOk();

    // handoff 12 §6: the money stays under review; only the dashboard moves an
    // order to "مسترد".
    expect($this->customer->wallet->fresh()->balance)->toBe(76.0)
        ->and(Order::where('reference', $reference)->value('status'))->toBe('ملغي');
});

// ─── receipts after the fact ────────────────────────────────────────────────

it('replaces a receipt without moving the order status', function () {
    $reference = test()->post('/api/orders', storefrontPayload([
        'paymentMethod' => 'jawwal-manual',
        'receiptNote'   => 'أول ملاحظة',
    ]), $this->headers)->json('id');

    test()->post("/api/orders/{$reference}/receipt", [
        'receiptImage' => UploadedFile::fake()->image('better.png'),
    ], $this->headers)->assertOk();

    expect(Order::where('reference', $reference)->value('status'))->toBe('قيد المراجعة');
});

it('refuses a receipt on an order that was not paid by transfer', function () {
    $reference = test()->post('/api/orders', storefrontPayload(), $this->headers)->json('id');

    test()->post("/api/orders/{$reference}/receipt", [
        'receiptImage' => UploadedFile::fake()->image('x.png'),
    ], $this->headers)->assertStatus(422);
});

// ─── confirming receipt ─────────────────────────────────────────────────────

it('lets the customer confirm a delivery arrived', function () {
    $reference = test()->post('/api/orders', storefrontPayload([
        'deliveryMethod' => 'delivery',
        'paymentMethod'  => 'jawwal-manual',
        'receiptNote'    => 'ملاحظة',
        'addressId'      => $this->address->id,
    ]), $this->headers)->json('id');

    test()->postJson("/api/orders/{$reference}/received", [], $this->headers)
        ->assertOk()
        ->assertJsonPath('order.status', 'تم الاستلام');

    expect(Order::where('reference', $reference)->value('received_at'))->not->toBeNull();
});

it('refuses to confirm receipt of something that was never being delivered', function () {
    $reference = test()->post('/api/orders', storefrontPayload(), $this->headers)->json('id');

    test()->postJson("/api/orders/{$reference}/received", [], $this->headers)->assertStatus(422);
});

// ─── email summary ──────────────────────────────────────────────────────────

it('mails a summary to any address the customer types', function () {
    Mail::fake();

    $reference = test()->post('/api/orders', storefrontPayload(), $this->headers)->json('id');

    test()->postJson("/api/orders/{$reference}/email-summary", [
        'email' => 'someone@example.com',
    ], $this->headers)->assertOk()->assertJson(['sent' => true]);

    Mail::assertSent(App\Mail\OrderSummaryMail::class);
});

it('refuses an invalid email for the summary', function () {
    $reference = test()->post('/api/orders', storefrontPayload(), $this->headers)->json('id');

    test()->postJson("/api/orders/{$reference}/email-summary", ['email' => 'nope'], $this->headers)
        ->assertStatus(422);
});

// ─── status flow ────────────────────────────────────────────────────────────

it('offers only the steps that make sense for the delivery method', function () {
    $order = new Order(['delivery_method' => 'pickup', 'status' => Order::FULFILMENT_REVIEW]);

    expect($order->allowedNextStatuses())
        ->toBe(['جاري التحضير', 'جاهز للاستلام', 'تم التسليم', 'ملغي', 'مسترد']);

    $delivery = new Order(['delivery_method' => 'delivery', 'status' => Order::FULFILMENT_PREPARING]);

    // Never "جاهز للاستلام" for a delivery — the storefront's tracker has no
    // such step to draw.
    expect($delivery->allowedNextStatuses())->toBe(['في الطريق', 'تم الاستلام', 'ملغي', 'مسترد']);

    $dineIn = new Order(['delivery_method' => 'dine-in', 'status' => Order::FULFILMENT_REVIEW]);

    expect($dineIn->allowedNextStatuses())->toBe(['تم التسليم', 'ملغي', 'مسترد']);
});

it('offers nothing once an order is closed', function () {
    expect((new Order(['delivery_method' => 'pickup', 'status' => Order::FULFILMENT_RECEIVED]))
        ->allowedNextStatuses())->toBe([]);
});

// ─── the counter ────────────────────────────────────────────────────────────

it('queues a receipt the moment an order lands', function () {
    Illuminate\Support\Facades\Queue::fake();

    test()->post('/api/orders', storefrontPayload(), $this->headers)->assertCreated();

    // Queued, not inline: an unplugged printer must never fail a paid checkout.
    Illuminate\Support\Facades\Queue::assertPushed(App\Jobs\PrintOrderReceipt::class);
});

it('carries a table number through to the order', function () {
    test()->post('/api/orders', storefrontPayload([
        'deliveryMethod' => 'dine-in',
        'tableNumber'    => '7',
    ]), $this->headers)
        ->assertCreated()
        ->assertJsonPath('tableNumber', '7');
});

it('does not put a table number on an order that is not dine-in', function () {
    test()->post('/api/orders', storefrontPayload([
        'deliveryMethod' => 'pickup',
        'tableNumber'    => '7',
    ]), $this->headers)
        ->assertCreated()
        ->assertJsonPath('tableNumber', null);
});
