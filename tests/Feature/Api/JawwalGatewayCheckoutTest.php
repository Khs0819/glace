<?php

use App\Models\Customer;
use App\Models\Order;
use App\Models\OtpCode;
use App\Models\Payment;
use App\Models\PaymentAccount;
use App\Services\Auth\CustomerAuthService;
use App\Services\JawwalPay\JawwalPayClient;
use Illuminate\Support\Facades\Http;
use Tests\Support\CatalogFactory;

/**
 * The storefront's automatic Jawwal Pay, once the gateway is live.
 *
 * Before this, "automatic" meant we texted our own code, checked it ourselves
 * and wrote an unpaid order: nothing was ever charged. These cover the real
 * thing — the code comes from Jawwal Pay and the wallet is charged.
 */

const GATEWAY_OTP = JAWWAL_BASE . '/v1/business/send_otp';
const GATEWAY_MFP = JAWWAL_BASE . '/v1/business/MFP';

beforeEach(function () {
    fakePublicDisk();

    $this->customer = Customer::create(['name' => 'أحمد علي', 'phone' => '0599123456']);
    $this->headers  = ['Authorization' => app(CustomerAuthService::class)->issueToken($this->customer)];

    $this->product = CatalogFactory::flatList('milkshake', ['name' => 'ميلك شيك']);
    CatalogFactory::item($this->product, 'vanilla', ['label' => 'فانيلا', 'price' => 12]);

    // The method ships switched off; a shop using it has turned it on.
    PaymentAccount::where('method', 'jawwal')->update(['active' => true]);
});

/** The gateway, answering. */
function liveGateway(array $endpoints): void
{
    fakeJawwalPay($endpoints);

    config(['services.jawwalpay.enabled' => true]);
    app()->forgetInstance(JawwalPayClient::class);
}

function jawwalPayload(array $overrides = []): array
{
    $items = [[
        'productId'  => test()->product->id,
        'name'       => 'ميلك شيك',
        'selections' => [['kind' => 'item', 'id' => 'vanilla', 'label' => 'فانيلا', 'qty' => 1]],
        'quantity'   => 2,
    ]];

    return array_merge([
        'items'          => json_encode($items),
        'paymentMethod'  => 'jawwal',
        'deliveryMethod' => 'pickup',
        'jawwalPhone'    => '0599002286',
        'jawwalCode'     => '123456',
    ], $overrides);
}

function sendGatewayCode(float $amount = 24): \Illuminate\Testing\TestResponse
{
    return test()->postJson('/api/orders/jawwal/send-code', [
        'phone'  => '0599002286',
        'amount' => $amount,
    ]);
}

// ─── the code comes from Jawwal Pay ─────────────────────────────────────────

it('asks the gateway for the code instead of texting one of our own', function () {
    liveGateway([GATEWAY_OTP => Http::response(jawwalEnvelope())]);

    sendGatewayCode()->assertOk()->assertJson(['sent' => true]);

    Http::assertSent(fn ($request) => $request->url() === GATEWAY_OTP);

    // Our own OTP table is not involved at all: only Jawwal Pay knows this code.
    expect(OtpCode::where('purpose', OtpCode::PURPOSE_JAWWAL)->count())->toBe(0);
});

it('passes the gateway refusal on to the customer', function () {
    liveGateway([GATEWAY_OTP => Http::response(jawwalEnvelope('20'))]);

    sendGatewayCode()->assertStatus(422)->assertJsonValidationErrors('phone');
});

// ─── charging ───────────────────────────────────────────────────────────────

it('charges the wallet and marks the order paid', function () {
    liveGateway([
        GATEWAY_OTP => Http::response(jawwalEnvelope()),
        GATEWAY_MFP => Http::response(jawwalEnvelope()),
    ]);

    sendGatewayCode()->assertOk();

    test()->post('/api/orders', jawwalPayload(), $this->headers)->assertCreated();

    $order = Order::sole();

    expect($order->isPaid())->toBeTrue()
        ->and($order->paid_at)->not->toBeNull()
        ->and($order->payments()->sole()->status)->toBe(Payment::STATUS_PAID);

    Http::assertSent(fn ($request) => $request->url() === GATEWAY_MFP);
});

it('cancels the order when the gateway refuses the charge', function () {
    liveGateway([
        GATEWAY_OTP => Http::response(jawwalEnvelope()),
        GATEWAY_MFP => Http::response(jawwalEnvelope('34')),
    ]);

    sendGatewayCode()->assertOk();

    test()->post('/api/orders', jawwalPayload(), $this->headers)
        ->assertStatus(422)
        ->assertJsonValidationErrors('jawwalCode');

    $order = Order::sole();

    // The order is kept, but cancelled: nobody prepares it, and support can
    // still find what happened.
    expect($order->status)->toBe(Order::FULFILMENT_CANCELLED)
        ->and($order->isPaid())->toBeFalse();
});

it('will not charge without a code having been asked for', function () {
    liveGateway([GATEWAY_MFP => Http::response(jawwalEnvelope())]);

    test()->post('/api/orders', jawwalPayload(), $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.jawwalCode.0', 'اطلب رمز التأكيد أولاً');

    expect(Order::count())->toBe(0);
});

it('will not charge a total the customer never approved', function () {
    liveGateway([GATEWAY_OTP => Http::response(jawwalEnvelope())]);

    // A code sent for 99 ₪ against a 24 ₪ cart: the customer approved a
    // different number than the one about to be taken.
    sendGatewayCode(99)->assertOk();

    test()->post('/api/orders', jawwalPayload(), $this->headers)
        ->assertStatus(422)
        ->assertJsonPath('errors.jawwalCode.0', 'تغيّرت قيمة الطلب — يرجى طلب رمز تأكيد جديد');

    expect(Order::count())->toBe(0);
});

it('spends the code once, so a replayed order cannot charge twice', function () {
    liveGateway([
        GATEWAY_OTP => Http::response(jawwalEnvelope()),
        GATEWAY_MFP => Http::response(jawwalEnvelope()),
    ]);

    sendGatewayCode()->assertOk();

    test()->post('/api/orders', jawwalPayload(), $this->headers)->assertCreated();
    test()->post('/api/orders', jawwalPayload(), $this->headers)->assertStatus(422);

    expect(Order::count())->toBe(1);
});
