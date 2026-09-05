<?php

use App\Filament\Resources\OrderResource;
use App\Filament\Resources\OrderResource\Pages\ListOrders;
use App\Filament\Resources\OrderResource\Pages\ViewOrder;
use App\Filament\Resources\OrderResource\RelationManagers\PaymentsRelationManager;
use App\Filament\Widgets\JawwalPayStatusWidget;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

function adminOrder(string $status = Order::STATUS_PENDING, float $total = 25): Order
{
    $order = Order::create([
        'reference'      => Order::newReference(),
        'public_token'   => Order::newPublicToken(),
        'customer_name'  => 'أحمد',
        'customer_phone' => '0599002286',
        'payment_status' => $status,
        'subtotal'       => $total,
        'total'          => $total,
        'currency'       => 'ILS',
    ]);

    $order->items()->create([
        'product_slug' => 'milkshake',
        'product_name' => 'ميلك شيك',
        'kind'         => 'flat-list',
        'selection'    => ['type' => 'item', 'itemId' => 'vanilla'],
        'description'  => 'فانيلا',
        'unit_price'   => $total,
        'quantity'     => 1,
        'addons_total' => 0,
        'line_total'   => $total,
    ]);

    return $order;
}

function adminPayment(Order $order, string $status = Payment::STATUS_UNRESOLVED): Payment
{
    return $order->payments()->create([
        'provider'      => 'jawwalpay',
        'method'        => 'otp',
        'otp_msg_id'    => (string) random_int(10000000000000, 99999999999999),
        'charge_msg_id' => (string) random_int(10000000000000, 99999999999999),
        'wallet'        => '00970599002286',
        'amount'        => $order->total,
        'status'        => $status,
        'otp_sent_at'   => now(),
    ]);
}

// ─── listing ────────────────────────────────────────────────────────────────

it('lists orders with their totals and status', function () {
    $order = adminOrder(Order::STATUS_PAID, 42);

    Livewire::test(ListOrders::class)
        ->assertCanSeeTableRecords([$order])
        ->assertSee($order->reference)
        ->assertSee('مدفوع');
});

it('separates the orders that still need someone', function () {
    $open  = adminOrder(Order::STATUS_PENDING);
    $paid  = adminOrder(Order::STATUS_PAID);
    $stuck = adminOrder(Order::STATUS_AWAITING_PAYMENT);
    adminPayment($stuck);

    // A charge nobody got an answer for outranks every fulfilment queue.
    Livewire::test(ListOrders::class)
        ->set('activeTab', 'needs_review')
        ->assertCanSeeTableRecords([$stuck])
        ->assertCanNotSeeTableRecords([$open, $paid]);

    // The payment tab is about money and ignores where the order has got to;
    // a paid order has nothing left owing, so it is not here.
    Livewire::test(ListOrders::class)
        ->set('activeTab', 'unpaid')
        ->assertCanSeeTableRecords([$open, $stuck])
        ->assertCanNotSeeTableRecords([$paid]);
});

it('queues orders by what the kitchen has to do next', function () {
    // Both axes at once: unpaid but being prepared, and paid but not started.
    $new = adminOrder(Order::STATUS_PENDING);

    $preparing = adminOrder(Order::STATUS_PAID);
    $preparing->update(['status' => Order::FULFILMENT_PREPARING]);

    $done = adminOrder(Order::STATUS_PAID);
    $done->update(['status' => Order::FULFILMENT_RECEIVED]);

    Livewire::test(ListOrders::class)
        ->set('activeTab', 'new')
        ->assertCanSeeTableRecords([$new])
        ->assertCanNotSeeTableRecords([$preparing, $done]);

    Livewire::test(ListOrders::class)
        ->set('activeTab', 'preparing')
        ->assertCanSeeTableRecords([$preparing])
        ->assertCanNotSeeTableRecords([$new, $done]);

    Livewire::test(ListOrders::class)
        ->set('activeTab', 'done')
        ->assertCanSeeTableRecords([$done])
        ->assertCanNotSeeTableRecords([$new, $preparing]);
});

it('flags an unresolved charge in the navigation badge', function () {
    adminPayment(adminOrder(Order::STATUS_AWAITING_PAYMENT));

    expect(OrderResource::getNavigationBadge())->toBe('1 غير مؤكد')
        ->and(OrderResource::getNavigationBadgeColor())->toBe('danger');
});

// ─── the record is a record ─────────────────────────────────────────────────

it('never offers to create or edit an order', function () {
    $order = adminOrder();

    expect(OrderResource::canCreate())->toBeFalse()
        ->and(OrderResource::canEdit($order))->toBeFalse()
        ->and(array_keys(OrderResource::getPages()))->toBe(['index', 'view']);
});

it('shows the full order on the view page', function () {
    $order = adminOrder(Order::STATUS_PAID, 42);

    Livewire::test(ViewOrder::class, ['record' => $order->getKey()])
        ->assertSee($order->reference)
        ->assertSee('ميلك شيك')
        ->assertSee('فانيلا')
        ->assertSee('أحمد');
});

it('cancels an unpaid order from the list', function () {
    $order = adminOrder();

    Livewire::test(ListOrders::class)
        ->callTableAction('cancel', $order);

    expect($order->fresh()->payment_status)->toBe(Order::STATUS_CANCELLED);
});

it('does not offer to cancel an order that was already paid', function () {
    $order = adminOrder(Order::STATUS_PAID);

    Livewire::test(ListOrders::class)
        ->assertTableActionHidden('cancel', $order);
});

// ─── payment attempts ───────────────────────────────────────────────────────

it('mounts the payments panel on the order screen', function () {
    expect(OrderResource::getRelations())->toContain(PaymentsRelationManager::class);
});

it('shows attempts with a masked wallet, never the full number', function () {
    $order   = adminOrder();
    $payment = adminPayment($order, Payment::STATUS_PAID);

    Livewire::test(PaymentsRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass'   => ViewOrder::class,
    ])
        ->assertCanSeeTableRecords([$payment])
        ->assertSee($payment->maskedWallet())
        ->assertDontSee('00970599002286');
});

it('asks the provider what happened to an unresolved charge', function () {
    $order   = adminOrder(Order::STATUS_AWAITING_PAYMENT);
    $payment = adminPayment($order);

    fakeJawwalPay([
        JAWWAL_BASE . '/v1/business/search_trans' => Http::response(jawwalEnvelope('00', [
            ['key' => 'txs', 'value' => json_encode([['reference' => '2921218694761', 'totalAmount' => '25.00']])],
        ])),
    ]);

    Livewire::test(PaymentsRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass'   => ViewOrder::class,
    ])
        ->callTableAction('lookup', $payment)
        ->assertHasNoTableActionErrors();

    // The order reference is what makes the lookup possible at all.
    Http::assertSent(fn ($request) => $request->url() !== JAWWAL_BASE . '/v1/business/search_trans'
        || $request->data()['externalReference'] === $order->reference);
});

it('closes an unresolved charge by hand and moves the order with it', function () {
    $order   = adminOrder(Order::STATUS_AWAITING_PAYMENT);
    $payment = adminPayment($order);

    Livewire::test(PaymentsRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass'   => ViewOrder::class,
    ])
        ->callTableAction('resolve', $payment, [
            'outcome' => 'paid',
            'note'    => 'ظهرت العملية في كشف الحساب',
        ])
        ->assertHasNoTableActionErrors();

    expect($payment->fresh()->status)->toBe(Payment::STATUS_PAID)
        ->and($order->fresh()->isPaid())->toBeTrue()
        ->and($payment->fresh()->error_description)->toContain('كشف الحساب');
});

it('requires a reason before closing a charge by hand', function () {
    $order   = adminOrder(Order::STATUS_AWAITING_PAYMENT);
    $payment = adminPayment($order);

    Livewire::test(PaymentsRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass'   => ViewOrder::class,
    ])
        ->callTableAction('resolve', $payment, ['outcome' => 'paid'])
        ->assertHasTableActionErrors(['note']);

    expect($order->fresh()->isPaid())->toBeFalse();
});

it('offers neither recovery action once a payment is settled', function () {
    $order   = adminOrder(Order::STATUS_PAID);
    $payment = adminPayment($order, Payment::STATUS_PAID);

    Livewire::test(PaymentsRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass'   => ViewOrder::class,
    ])
        ->assertTableActionHidden('lookup', $payment)
        ->assertTableActionHidden('resolve', $payment);
});

// ─── gateway status ─────────────────────────────────────────────────────────

it('reports the gateway as unconfigured rather than breaking the dashboard', function () {
    config(['services.jawwalpay' => ['base_url' => 'https://apitest.jawwalpay.ps']]);
    app()->forgetInstance(App\Services\JawwalPay\JawwalPayClient::class);

    Livewire::test(JawwalPayStatusWidget::class)
        ->assertOk()
        ->assertSee('غير مُعدّة');
});

it('keeps the dashboard up when the gateway is unreachable', function () {
    fakeJawwalPay([
        JAWWAL_BASE . '/v1/get_balance' => fn () => throw new Illuminate\Http\Client\ConnectionException('down'),
    ]);

    Livewire::test(JawwalPayStatusWidget::class)
        ->assertOk()
        ->assertSee('غير متاحة');
});

it('shows the wallet balance and says which environment it came from', function () {
    fakeJawwalPay([
        JAWWAL_BASE . '/v1/get_balance' => Http::response(jawwalEnvelope('00', [
            ['key' => 'info', 'value' => json_encode([
                'accounts' => [['accountNumber' => '1971', 'accountType' => 'WALLET', 'balance' => 835.2]],
            ])],
        ])),
    ]);

    adminOrder(Order::STATUS_PAID, 42)->update(['paid_at' => now()]);

    Livewire::test(JawwalPayStatusWidget::class)
        ->assertOk()
        ->assertSee('835.20 ₪')
        ->assertSee('بيئة اختبار');
});
