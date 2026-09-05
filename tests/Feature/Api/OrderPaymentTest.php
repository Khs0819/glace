<?php

use App\Models\Order;
use App\Models\Payment;
use App\Services\JawwalPay\ErrorCode;
use App\Services\JawwalPay\JawwalPayClient;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\CatalogFactory;

const OTP_URL = JAWWAL_BASE . '/v1/business/send_otp';
const MFP_URL = JAWWAL_BASE . '/v1/business/MFP';

/** An order of exactly 25 ILS, ready to be paid. */
function payableOrder(): Order
{
    $product = CatalogFactory::flatList('milkshake', ['name' => 'ميلك شيك']);
    CatalogFactory::item($product, 'vanilla', ['label' => 'فانيلا', 'price' => 25]);

    $created = test()->postJson('/api/checkout/orders', [
        'customer' => ['name' => 'أحمد', 'phone' => '0599002286'],
        'items'    => [['productId' => $product->id, 'itemId' => 'vanilla', 'quantity' => 1]],
    ])->assertCreated();

    $order = Order::sole();
    $order->setAttribute('__token', $created->json('token'));

    return $order;
}

function requestOtp(Order $order, string $wallet = '0599002286')
{
    return test()->postJson("/api/orders/{$order->id}/payment/otp", [
        'token'  => $order->getAttribute('__token'),
        'wallet' => $wallet,
    ]);
}

function confirmOtp(Order $order, string $otp = '123456')
{
    return test()->postJson("/api/orders/{$order->id}/payment/confirm", [
        'token' => $order->getAttribute('__token'),
        'otp'   => $otp,
    ]);
}

// ─── happy path ─────────────────────────────────────────────────────────────

it('sends a code for the order total and charges that same total', function () {
    fakeJawwalPay([
        OTP_URL => Http::response(jawwalEnvelope()),
        MFP_URL => Http::response(jawwalEnvelope()),
    ]);

    $order = payableOrder();

    requestOtp($order)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('status', Payment::STATUS_OTP_SENT)
        ->assertJsonPath('order.paymentStatus', Order::STATUS_AWAITING_PAYMENT);

    confirmOtp($order)
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('order.paymentStatus', Order::STATUS_PAID);

    expect($order->fresh()->isPaid())->toBeTrue()
        ->and($order->fresh()->paid_at)->not->toBeNull();

    $payment = Payment::sole();

    expect($payment->status)->toBe(Payment::STATUS_PAID)
        ->and($payment->amount)->toBe(25.0)
        ->and($payment->wallet)->toBe('00970599002286')
        ->and($payment->provider_reference)->toBe('2921218715102')
        // Two calls, two different message ids — reusing one is code 46.
        ->and($payment->otp_msg_id)->not->toBe($payment->charge_msg_id);

    // The amount charged is the order's, in the gateway's own string format.
    Http::assertSent(fn (Request $request) => $request->url() !== MFP_URL
        || $request->data()['amount'] === '25');
});

it('tags the charge with the order reference so it can be traced later', function () {
    fakeJawwalPay([
        OTP_URL => Http::response(jawwalEnvelope()),
        MFP_URL => Http::response(jawwalEnvelope()),
    ]);

    $order = payableOrder();
    requestOtp($order);
    confirmOtp($order);

    Http::assertSent(fn (Request $request) => $request->url() !== MFP_URL
        || $request->data()['additionalReferenceLabel'] === $order->reference);
});

it('never lets the raw code leave the server', function () {
    fakeJawwalPay([
        OTP_URL => Http::response(jawwalEnvelope()),
        MFP_URL => Http::response(jawwalEnvelope()),
    ]);

    $order = payableOrder();
    requestOtp($order);
    confirmOtp($order, '778899');

    Http::assertSent(fn (Request $request) => $request->url() !== MFP_URL
        || ($request->data()['otp'] === JawwalPayClient::hashOtp('778899')
            && $request->data()['otp'] !== '778899'));

    expect(json_encode(Payment::sole()->last_response))->not->toContain('778899');
});

// ─── the gateway says no ────────────────────────────────────────────────────

it('reports a wrong code without failing the order, and counts the attempt', function () {
    fakeJawwalPay([
        OTP_URL => Http::response(jawwalEnvelope()),
        MFP_URL => Http::response(jawwalEnvelope(ErrorCode::INVALID_OTP)),
    ]);

    $order = payableOrder();
    requestOtp($order);

    confirmOtp($order, '000000')
        ->assertStatus(422)
        ->assertJsonPath('success', false)
        ->assertJsonPath('errorCode', '89')
        ->assertJsonPath('message', ErrorCode::message('89'))
        ->assertJsonPath('attemptsLeft', 2);

    expect($order->fresh()->payment_status)->toBe(Order::STATUS_AWAITING_PAYMENT)
        ->and(Payment::sole()->status)->toBe(Payment::STATUS_OTP_SENT);
});

it('closes the attempt off after three wrong codes', function () {
    fakeJawwalPay([
        OTP_URL => Http::response(jawwalEnvelope()),
        MFP_URL => Http::response(jawwalEnvelope(ErrorCode::INVALID_OTP)),
    ]);

    $order = payableOrder();
    requestOtp($order);

    confirmOtp($order, '000000')->assertStatus(422);
    confirmOtp($order, '000000')->assertStatus(422);
    confirmOtp($order, '000000')->assertStatus(422)->assertJsonPath('attemptsLeft', 0);

    // A fourth is refused before it reaches the gateway.
    confirmOtp($order, '000000')->assertStatus(409);

    expect($order->fresh()->payment_status)->toBe(Order::STATUS_FAILED)
        ->and(Payment::sole()->status)->toBe(Payment::STATUS_FAILED);
});

it('surfaces a rejected wallet in arabic without leaking our own problems', function () {
    fakeJawwalPay([OTP_URL => Http::response(jawwalEnvelope('05'))]); // not registered

    $order = payableOrder();

    requestOtp($order)
        ->assertStatus(422)
        ->assertJsonPath('errorCode', '05')
        ->assertJsonPath('message', ErrorCode::message('05'));

    expect($order->fresh()->payment_status)->toBe(Order::STATUS_PENDING);
});

it('hides an error that is about our merchant account, not the customer', function () {
    fakeJawwalPay([OTP_URL => Http::response(jawwalEnvelope('26'))]); // corporate not active

    $order = payableOrder();

    requestOtp($order)
        ->assertStatus(422)
        ->assertJsonPath('errorCode', '26')
        ->assertJsonPath('message', ErrorCode::customerMessage('26'));

    expect(ErrorCode::customerMessage('26'))->not->toBe(ErrorCode::message('26'));
});

// ─── the gateway does not answer ────────────────────────────────────────────

it('answers 503 and keeps the order untouched when the code request times out', function () {
    fakeJawwalPay([OTP_URL => fn () => throw new ConnectionException('timeout')]);

    $order = payableOrder();

    requestOtp($order)->assertStatus(503);

    expect($order->fresh()->payment_status)->toBe(Order::STATUS_PENDING)
        ->and(Payment::sole()->status)->toBe(Payment::STATUS_FAILED);
});

it('parks a charge we never got an answer for instead of guessing', function () {
    fakeJawwalPay([
        OTP_URL => Http::response(jawwalEnvelope()),
        MFP_URL => fn () => throw new ConnectionException('timeout'),
    ]);

    $order = payableOrder();
    requestOtp($order);

    confirmOtp($order)->assertStatus(503);

    // The money may or may not have moved, so it is neither paid nor failed.
    expect(Payment::sole()->status)->toBe(Payment::STATUS_UNRESOLVED)
        ->and($order->fresh()->isPaid())->toBeFalse();

    // And nothing may be charged again until a human has checked.
    confirmOtp($order)->assertStatus(409);
    requestOtp($order)->assertStatus(409);
});

// ─── guards ─────────────────────────────────────────────────────────────────

it('refuses to charge an order twice', function () {
    fakeJawwalPay([
        OTP_URL => Http::response(jawwalEnvelope()),
        MFP_URL => Http::response(jawwalEnvelope()),
    ]);

    $order = payableOrder();
    requestOtp($order);
    confirmOtp($order)->assertOk();

    requestOtp($order)->assertStatus(409)->assertJsonPath('message', 'هذا الطلب مدفوع بالفعل');
    confirmOtp($order)->assertStatus(409);

    expect(Payment::where('status', Payment::STATUS_PAID)->count())->toBe(1);
});

it('will not confirm before a code has been asked for', function () {
    fakeJawwalPay([MFP_URL => Http::response(jawwalEnvelope())]);

    confirmOtp(payableOrder())
        ->assertStatus(422)
        ->assertJsonValidationErrors(['otp']);

    Http::assertNotSent(fn (Request $request) => $request->url() === MFP_URL);
});

it('rejects a wallet number the gateway would only bounce', function () {
    fakeJawwalPay();

    requestOtp(payableOrder(), '022960000')
        ->assertStatus(422)
        ->assertJsonValidationErrors(['wallet']);

    Http::assertNotSent(fn (Request $request) => $request->url() === OTP_URL);
});

it('requires the order token for every payment call', function () {
    fakeJawwalPay([OTP_URL => Http::response(jawwalEnvelope())]);

    $order = payableOrder();

    $this->postJson("/api/orders/{$order->id}/payment/otp", ['token' => 'wrong', 'wallet' => '0599002286'])
        ->assertNotFound();

    $this->postJson("/api/orders/{$order->id}/payment/confirm", ['token' => 'wrong', 'otp' => '123456'])
        ->assertNotFound();

    Http::assertNothingSent();
});

it('supersedes an outstanding code when a new one is asked for', function () {
    fakeJawwalPay([OTP_URL => Http::response(jawwalEnvelope())]);

    $order = payableOrder();
    requestOtp($order)->assertOk();

    // Past the cooldown, a second code replaces the first.
    Payment::sole()->update(['otp_sent_at' => now()->subMinutes(5)]);
    requestOtp($order)->assertOk();

    $payments = Payment::orderBy('id')->get();

    expect($payments)->toHaveCount(2)
        ->and($payments[0]->status)->toBe(Payment::STATUS_EXPIRED)
        ->and($payments[1]->status)->toBe(Payment::STATUS_OTP_SENT)
        ->and($payments[0]->otp_msg_id)->not->toBe($payments[1]->otp_msg_id);
});

it('makes the customer wait before asking for another code', function () {
    fakeJawwalPay([OTP_URL => Http::response(jawwalEnvelope())]);

    $order = payableOrder();
    requestOtp($order)->assertOk();
    requestOtp($order)->assertStatus(429);

    expect(Payment::count())->toBe(1);
});

it('stops asking the gateway for codes after five tries', function () {
    fakeJawwalPay([OTP_URL => Http::response(jawwalEnvelope())]);

    $order = payableOrder();

    for ($i = 0; $i < Payment::MAX_OTP_REQUESTS; $i++) {
        requestOtp($order)->assertOk();
        Payment::latest('id')->first()->update(['otp_sent_at' => now()->subMinutes(5)]);
    }

    requestOtp($order)->assertStatus(409);

    expect(Payment::count())->toBe(Payment::MAX_OTP_REQUESTS);
});
