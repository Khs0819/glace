<?php

use App\Models\Coupon;
use App\Models\Customer;
use App\Services\Auth\CustomerAuthService;

/**
 * Discount codes (handoff 11).
 *
 * The frontend used to hold the whole table in its bundle, so every code in the
 * shop was readable by anyone who opened devtools.
 */

// ─── validating ─────────────────────────────────────────────────────────────

it('applies a fixed coupon', function () {
    Coupon::create(['code' => 'GLACE10', 'type' => 'fixed', 'value' => 10]);

    test()->postJson('/api/cart/apply-coupon', ['code' => 'GLACE10', 'subtotal' => 85])
        ->assertOk()
        ->assertJson([
            'valid'    => true,
            'code'     => 'GLACE10',
            'discount' => 10,
            'message'  => 'تم تطبيق الكوبون',
        ]);
});

it('returns a percentage coupon as a final shekel figure', function () {
    Coupon::create(['code' => 'TENPC', 'type' => 'percent', 'value' => 10]);

    // handoff 11: the response carries a computed amount either way, so the
    // storefront never has to know which kind it was.
    test()->postJson('/api/cart/apply-coupon', ['code' => 'TENPC', 'subtotal' => 85])
        ->assertOk()
        ->assertJsonPath('valid', true)
        ->assertJsonPath('discount', 8.5);
});

it('caps a percentage coupon at its ceiling', function () {
    Coupon::create(['code' => 'BIG', 'type' => 'percent', 'value' => 50, 'max_discount' => 20]);

    test()->postJson('/api/cart/apply-coupon', ['code' => 'BIG', 'subtotal' => 200])
        ->assertOk()
        ->assertJsonPath('discount', 20);
});

it('answers an unknown code with 200 and valid false, not an error', function () {
    // The customer is typing into a box; a miss is an answer, not a failure.
    test()->postJson('/api/cart/apply-coupon', ['code' => 'NOPE', 'subtotal' => 85])
        ->assertOk()
        ->assertJson([
            'valid'    => false,
            'discount' => 0,
            'message'  => 'الكوبون غير صالح أو منتهي',
        ]);
});

it('matches a code whatever case it was typed in', function () {
    Coupon::create(['code' => 'GLACE10', 'type' => 'fixed', 'value' => 10]);

    test()->postJson('/api/cart/apply-coupon', ['code' => 'glace10', 'subtotal' => 85])
        ->assertOk()->assertJsonPath('valid', true);

    test()->postJson('/api/cart/apply-coupon', ['code' => '  GlAcE10  ', 'subtotal' => 85])
        ->assertOk()->assertJsonPath('valid', true);
});

it('refuses an expired, inactive or spent coupon the same way', function () {
    Coupon::create(['code' => 'OLD', 'type' => 'fixed', 'value' => 10, 'expires_at' => now()->subDay()]);
    Coupon::create(['code' => 'OFF', 'type' => 'fixed', 'value' => 10, 'active' => false]);
    Coupon::create(['code' => 'SPENT', 'type' => 'fixed', 'value' => 10, 'usage_limit' => 1, 'used_count' => 1]);

    // One message for all three: telling them apart makes guessing cheaper.
    foreach (['OLD', 'OFF', 'SPENT'] as $code) {
        test()->postJson('/api/cart/apply-coupon', ['code' => $code, 'subtotal' => 85])
            ->assertOk()
            ->assertJson(['valid' => false, 'message' => 'الكوبون غير صالح أو منتهي']);
    }
});

it('enforces a minimum order value', function () {
    Coupon::create(['code' => 'MIN50', 'type' => 'fixed', 'value' => 10, 'min_subtotal' => 50]);

    test()->postJson('/api/cart/apply-coupon', ['code' => 'MIN50', 'subtotal' => 20])
        ->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('message', 'الحد الأدنى لاستخدام هذا الكوبون 50 ₪');

    test()->postJson('/api/cart/apply-coupon', ['code' => 'MIN50', 'subtotal' => 50])
        ->assertOk()->assertJsonPath('valid', true);
});

it('never discounts more than the order is worth', function () {
    Coupon::create(['code' => 'HUGE', 'type' => 'fixed', 'value' => 500]);

    test()->postJson('/api/cart/apply-coupon', ['code' => 'HUGE', 'subtotal' => 24])
        ->assertOk()
        ->assertJsonPath('discount', 24);
});

it('works signed out, because the cart does', function () {
    Coupon::create(['code' => 'GLACE10', 'type' => 'fixed', 'value' => 10]);

    test()->postJson('/api/cart/apply-coupon', ['code' => 'GLACE10', 'subtotal' => 85])
        ->assertOk()->assertJsonPath('valid', true);
});

it('demands a code and a subtotal', function () {
    test()->postJson('/api/cart/apply-coupon', [])->assertStatus(422);
    test()->postJson('/api/cart/apply-coupon', ['code' => 'X'])->assertStatus(422);
});

// ─── per-customer limits ────────────────────────────────────────────────────

it('holds a customer to their personal usage limit', function () {
    $coupon   = Coupon::create([
        'code' => 'ONEEACH', 'type' => 'fixed', 'value' => 5, 'per_customer_limit' => 1,
    ]);
    $customer = Customer::create(['name' => 'أحمد', 'phone' => '0599123456']);
    $headers  = ['Authorization' => app(CustomerAuthService::class)->issueToken($customer)];

    test()->postJson('/api/cart/apply-coupon', ['code' => 'ONEEACH', 'subtotal' => 85], $headers)
        ->assertOk()->assertJsonPath('valid', true);

    $customer->orders()->create([
        'reference' => 'ORD-AAAAAA', 'public_token' => str_repeat('a', 64),
        'customer_name' => 'أحمد', 'customer_phone' => '0599123456',
        'coupon_code' => 'ONEEACH', 'subtotal' => 85, 'total' => 80, 'currency' => 'ILS',
    ]);

    test()->postJson('/api/cart/apply-coupon', ['code' => 'ONEEACH', 'subtotal' => 85], $headers)
        ->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('message', 'لقد استخدمت هذا الكوبون من قبل');
});

it('does not count a cancelled order against the personal limit', function () {
    $coupon   = Coupon::create([
        'code' => 'ONEEACH', 'type' => 'fixed', 'value' => 5, 'per_customer_limit' => 1,
    ]);
    $customer = Customer::create(['name' => 'أحمد', 'phone' => '0599123456']);
    $headers  = ['Authorization' => app(CustomerAuthService::class)->issueToken($customer)];

    // They never got the discount, so it should not have been spent.
    $customer->orders()->create([
        'reference' => 'ORD-BBBBBB', 'public_token' => str_repeat('b', 64),
        'customer_name' => 'أحمد', 'customer_phone' => '0599123456',
        'status' => 'ملغي', 'coupon_code' => 'ONEEACH',
        'subtotal' => 85, 'total' => 80, 'currency' => 'ILS',
    ]);

    test()->postJson('/api/cart/apply-coupon', ['code' => 'ONEEACH', 'subtotal' => 85], $headers)
        ->assertOk()->assertJsonPath('valid', true);
});

it('ignores a personal limit for a guest, who has no history to check', function () {
    Coupon::create(['code' => 'ONEEACH', 'type' => 'fixed', 'value' => 5, 'per_customer_limit' => 1]);

    test()->postJson('/api/cart/apply-coupon', ['code' => 'ONEEACH', 'subtotal' => 85])
        ->assertOk()->assertJsonPath('valid', true);
});
