<?php

use App\Filament\Resources\CouponResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\DeliveryZoneResource;
use App\Filament\Resources\FaqResource;
use App\Filament\Resources\PaymentAccountResource;
use App\Filament\Resources\SiteContentResource;
use App\Filament\Resources\TopUpRequestResource;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Faq;
use App\Models\Order;
use App\Models\PaymentAccount;
use App\Models\SiteContent;
use App\Models\TopUpRequest;
use App\Models\User;
use App\Services\Storefront\WalletService;
use Livewire\Livewire;

/**
 * The dashboard screens behind the storefront systems (handoff 10–17).
 */

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('renders every new resource index page', function (string $resource) {
    $this->get($resource::getUrl('index'))->assertSuccessful();
})->with([
    CouponResource::class,
    TopUpRequestResource::class,
    PaymentAccountResource::class,
    DeliveryZoneResource::class,
    FaqResource::class,
    SiteContentResource::class,
    CustomerResource::class,
]);

it('renders the create and edit screens that have them', function () {
    $this->get(CouponResource::getUrl('create'))->assertSuccessful();
    $this->get(FaqResource::getUrl('create'))->assertSuccessful();
    $this->get(PaymentAccountResource::getUrl('create'))->assertSuccessful();
    $this->get(DeliveryZoneResource::getUrl('create'))->assertSuccessful();

    $coupon = Coupon::create(['code' => 'X10', 'type' => 'fixed', 'value' => 10]);
    $this->get(CouponResource::getUrl('edit', ['record' => $coupon]))->assertSuccessful();

    $content = SiteContent::create(['key' => 'terms', 'title' => 'الشروط', 'body' => '<p>x</p>']);
    $this->get(SiteContentResource::getUrl('edit', ['record' => $content]))->assertSuccessful();
});

it('renders a customer view with their wallet and order totals', function () {
    $customer = Customer::create(['name' => 'أحمد', 'phone' => '0599123456']);
    app(WalletService::class)->credit($customer, 5000, 'شحن');

    $this->get(CustomerResource::getUrl('view', ['record' => $customer]))->assertSuccessful();
});

it('renders a top-up request with its receipt', function () {
    $customer = Customer::create(['name' => 'أحمد', 'phone' => '0599123456']);
    $request  = app(WalletService::class)->requestTopUp($customer, [
        'amount' => 50, 'method' => 'bop', 'receiptNote' => 'حوّلت من بنك فلسطين',
    ]);

    $this->get(TopUpRequestResource::getUrl('view', ['record' => $request]))->assertSuccessful();
});

// ─── the actions that move money ────────────────────────────────────────────

it('credits a wallet from the top-up approve action', function () {
    $customer = Customer::create(['name' => 'أحمد', 'phone' => '0599123456']);
    $request  = app(WalletService::class)->requestTopUp($customer, [
        'amount' => 50, 'method' => 'bop', 'receiptNote' => 'x',
    ]);

    Livewire::test(TopUpRequestResource\Pages\ListTopUpRequests::class)
        ->callTableAction('approve', $request, ['note' => 'تم التحقق'])
        ->assertHasNoTableActionErrors();

    expect($customer->wallet->fresh()->balance)->toBe(50.0)
        ->and($request->fresh()->status)->toBe(TopUpRequest::STATUS_APPROVED);
});

it('hides approve once a request has been dealt with', function () {
    $customer = Customer::create(['name' => 'أحمد', 'phone' => '0599123456']);
    $request  = app(WalletService::class)->requestTopUp($customer, [
        'amount' => 50, 'method' => 'bop', 'receiptNote' => 'x',
    ]);

    app(WalletService::class)->approveTopUp($request);

    // The service refuses a second credit anyway; the button should not invite
    // one either.
    Livewire::test(TopUpRequestResource\Pages\ListTopUpRequests::class)
        ->assertTableActionHidden('approve', $request->fresh());
});

it('adjusts a wallet by hand from the customer screen', function () {
    $customer = Customer::create(['name' => 'أحمد', 'phone' => '0599123456']);

    Livewire::test(CustomerResource\Pages\ListCustomers::class)
        ->callTableAction('adjustWallet', $customer, [
            'direction' => 'credit', 'amount' => 25, 'label' => 'تعويض عن طلب ملغي',
        ])
        ->assertHasNoTableActionErrors();

    expect($customer->wallet->fresh()->balance)->toBe(25.0)
        // The reason is shown to the customer verbatim.
        ->and($customer->wallet->transactions()->first()->label)->toBe('تعويض عن طلب ملغي');
});

it('refuses a manual debit the balance cannot cover', function () {
    $customer = Customer::create(['name' => 'أحمد', 'phone' => '0599123456']);

    Livewire::test(CustomerResource\Pages\ListCustomers::class)
        ->callTableAction('adjustWallet', $customer, [
            'direction' => 'debit', 'amount' => 500, 'label' => 'خصم',
        ]);

    expect((float) ($customer->wallet()->first()?->balance ?? 0))->toBe(0.0);
});

// ─── order fulfilment ───────────────────────────────────────────────────────

function storefrontOrder(array $attributes = []): Order
{
    $customer = Customer::create(['name' => 'أحمد', 'phone' => '0599123456']);

    return $customer->orders()->create(array_merge([
        'reference'       => Order::newReference(),
        'public_token'    => Order::newPublicToken(),
        'customer_name'   => 'أحمد',
        'customer_phone'  => '0599123456',
        'delivery_method' => 'delivery',
        'payment_method'  => 'jawwal-manual',
        'subtotal'        => 30,
        'total'           => 30,
        'currency'        => 'ILS',
    ], $attributes));
}

it('advances an order along its own ladder', function () {
    $order = storefrontOrder();

    Livewire::test(App\Filament\Resources\OrderResource\Pages\ListOrders::class)
        ->callTableAction('advance', $order, [
            'status' => Order::FULFILMENT_PREPARING, 'preparation_time' => 20,
        ])
        ->assertHasNoTableActionErrors();

    expect($order->fresh()->status)->toBe(Order::FULFILMENT_PREPARING)
        ->and($order->fresh()->preparation_time)->toBe(20);
});

it('stamps the time when an order reaches a terminal step', function () {
    $order = storefrontOrder(['status' => Order::FULFILMENT_ON_WAY]);

    Livewire::test(App\Filament\Resources\OrderResource\Pages\ListOrders::class)
        ->callTableAction('advance', $order, ['status' => Order::FULFILMENT_RECEIVED]);

    expect($order->fresh()->received_at)->not->toBeNull();
});

it('assigns a driver to a delivery', function () {
    $order = storefrontOrder(['status' => Order::FULFILMENT_PREPARING]);

    Livewire::test(App\Filament\Resources\OrderResource\Pages\ListOrders::class)
        ->callTableAction('assignDriver', $order, [
            'name' => 'محمود الأحمد', 'phone' => '0599876543', 'company' => 'توصيل فلسطين',
        ])
        ->assertHasNoTableActionErrors();

    expect($order->fresh()->driver['name'])->toBe('محمود الأحمد')
        ->and($order->fresh()->driver_assigned_at)->not->toBeNull();
});

it('does not offer a driver for a pickup order', function () {
    $order = storefrontOrder(['delivery_method' => 'pickup']);

    Livewire::test(App\Filament\Resources\OrderResource\Pages\ListOrders::class)
        ->assertTableActionHidden('assignDriver', $order);
});

it('refunds to the wallet only when someone decides to', function () {
    $order = storefrontOrder(['status' => Order::FULFILMENT_CANCELLED]);

    // Cancelling did not refund; this action is the separate decision.
    expect((float) ($order->customer->wallet()->first()?->balance ?? 0))->toBe(0.0);

    Livewire::test(App\Filament\Resources\OrderResource\Pages\ListOrders::class)
        ->callTableAction('refundToWallet', $order)
        ->assertHasNoTableActionErrors();

    expect($order->customer->wallet->fresh()->balance)->toBe(30.0)
        ->and($order->fresh()->status)->toBe(Order::FULFILMENT_REFUNDED);
});

it('does not offer a second refund on an order already refunded', function () {
    $order = storefrontOrder(['status' => Order::FULFILMENT_REFUNDED]);

    Livewire::test(App\Filament\Resources\OrderResource\Pages\ListOrders::class)
        ->assertTableActionHidden('refundToWallet', $order);
});

// ─── content ────────────────────────────────────────────────────────────────

it('sanitises legal page html saved through the dashboard', function () {
    Livewire::test(SiteContentResource\Pages\CreateSiteContent::class)
        ->fillForm([
            'key'   => 'privacy',
            'title' => 'سياسة الخصوصية',
            'body'  => '<h3>عنوان</h3><script>steal()</script><p onclick="x()">نص</p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $body = SiteContent::find('privacy')->body;

    expect($body)->not->toContain('script')
        ->and($body)->not->toContain('onclick')
        ->and($body)->toContain('<h3>عنوان</h3>');
});

it('keeps a delivery zone that addresses still point at', function () {
    $zone     = DeliveryZone::create(['id' => 'rimal', 'name' => 'الرمال', 'fee' => 10]);
    $customer = Customer::create(['name' => 'أحمد', 'phone' => '0599123456']);

    $customer->addresses()->create([
        'type' => 'home', 'label' => 'المنزل', 'name' => 'أحمد', 'phone' => '0599123456',
        'city' => 'غزة', 'zone_id' => 'rimal', 'street' => 'شارع الجلاء', 'is_default' => true,
    ]);

    // Deleting it would orphan the address; switching it off is the way.
    expect(DeliveryZoneResource::canDelete($zone))->toBeFalse();

    $customer->addresses()->delete();

    expect(DeliveryZoneResource::canDelete($zone->fresh()))->toBeTrue();
});

it('does not let the dashboard create or delete a customer', function () {
    $customer = Customer::create(['name' => 'أحمد', 'phone' => '0599123456']);

    // Accounts come from signing in; deleting one would take its orders too.
    expect(CustomerResource::canCreate())->toBeFalse()
        ->and(CustomerResource::canDelete($customer))->toBeFalse();
});

it('does not let anyone invent a top-up request from the dashboard', function () {
    expect(TopUpRequestResource::canCreate())->toBeFalse();
});
