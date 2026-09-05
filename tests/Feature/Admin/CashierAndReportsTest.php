<?php

use App\Filament\Pages\CashierBoard;
use App\Models\CashierShift;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use App\Services\Checkout\Money;
use App\Services\Reporting\FinancialReport;
use App\Services\Storefront\WalletService;
use Livewire\Livewire;

/**
 * The counter and the books: shifts, cash reconciliation, and the figures an
 * accountant has to be able to defend.
 */

beforeEach(function () {
    $this->cashier = User::factory()->create(['name' => 'محمد الكاشير']);
    $this->actingAs($this->cashier);
});

function counterOrder(array $attributes = []): Order
{
    $customer = Customer::firstOrCreate(
        ['phone' => '0599123456'],
        ['name' => 'أحمد علي'],
    );

    $order = $customer->orders()->create(array_merge([
        'reference'       => Order::newReference(),
        'public_token'    => Order::newPublicToken(),
        'customer_name'   => 'أحمد علي',
        'customer_phone'  => '0599123456',
        'delivery_method' => 'dine-in',
        'payment_method'  => 'cash',
        'subtotal'        => 30,
        'total'           => 30,
        'currency'        => 'ILS',
    ], $attributes));

    $order->items()->create([
        'product_slug' => 'cup', 'product_name' => 'بوظة', 'kind' => 'builder',
        'selection' => [], 'description' => 'صغير',
        'unit_price' => 30, 'quantity' => 1, 'addons_total' => 0, 'line_total' => 30,
    ]);

    return $order;
}

// ─── the shift ──────────────────────────────────────────────────────────────

it('renders the cashier screen', function () {
    Livewire::test(CashierBoard::class)->assertOk();
});

it('opens and closes a shift with the drawer counted', function () {
    Livewire::test(CashierBoard::class)
        ->callAction('openShift', ['opening_float' => 100]);

    $shift = CashierShift::openFor($this->cashier);

    expect($shift)->not->toBeNull()
        ->and($shift->opening_float)->toBe(100.0);

    Livewire::test(CashierBoard::class)
        ->callAction('closeShift', ['counted_cash' => 100, 'notes' => null]);

    $shift = $shift->fresh();

    expect($shift->open())->toBeFalse()
        ->and($shift->expected_cash)->toBe(100.0)
        ->and($shift->difference)->toBe(0.0);
});

it('records a short drawer rather than quietly balancing it', function () {
    Livewire::test(CashierBoard::class)->callAction('openShift', ['opening_float' => 0]);

    $order = counterOrder();
    Livewire::test(CashierBoard::class)->call('markPaid', $order->reference);

    // 30 taken, 25 counted: a five shekel gap somebody has to explain.
    Livewire::test(CashierBoard::class)
        ->callAction('closeShift', ['counted_cash' => 25, 'notes' => 'فرق غير مبرر']);

    $shift = CashierShift::latest('id')->first();

    expect($shift->expected_cash)->toBe(30.0)
        ->and($shift->counted_cash)->toBe(25.0)
        ->and($shift->difference)->toBe(-5.0);
});

it('refuses to take cash with no shift open', function () {
    $order = counterOrder();

    // Otherwise the money has nowhere to be counted and the closing report is
    // short by exactly this amount.
    Livewire::test(CashierBoard::class)->call('markPaid', $order->reference);

    expect($order->fresh()->isPaid())->toBeFalse();
});

it('attributes a cash payment to the cashier and their shift', function () {
    Livewire::test(CashierBoard::class)->callAction('openShift', ['opening_float' => 0]);

    $order = counterOrder();
    Livewire::test(CashierBoard::class)->call('markPaid', $order->reference);

    $order = $order->fresh();
    $shift = CashierShift::openFor($this->cashier);

    expect($order->isPaid())->toBeTrue()
        ->and($order->paid_by)->toBe($this->cashier->id)
        ->and($order->shift_id)->toBe($shift->id)
        ->and($order->paid_at)->not->toBeNull();
});

it('will not let the counter settle an order paid by gateway or transfer', function () {
    Livewire::test(CashierBoard::class)->callAction('openShift', ['opening_float' => 0]);

    // Not the cashier's to declare: a transfer is settled by whoever reviews
    // the receipt, and a wallet order was settled at checkout.
    foreach (['jawwal-manual', 'wallet', 'bop'] as $method) {
        $order = counterOrder(['payment_method' => $method]);

        Livewire::test(CashierBoard::class)->call('markPaid', $order->reference);

        expect($order->fresh()->isPaid())->toBeFalse();
    }
});

it('only counts card and transfer money outside the drawer', function () {
    Livewire::test(CashierBoard::class)->callAction('openShift', ['opening_float' => 50]);

    $shift = CashierShift::openFor($this->cashier);

    counterOrder(['payment_method' => 'cash'])->update([
        'payment_status' => Order::STATUS_PAID, 'shift_id' => $shift->id, 'paid_at' => now(),
    ]);

    counterOrder(['payment_method' => 'visa', 'total' => 90])->update([
        'payment_status' => Order::STATUS_PAID, 'shift_id' => $shift->id, 'paid_at' => now(),
    ]);

    // Card money never enters the drawer, so it must not raise what the count
    // is checked against: 50 float + 30 cash.
    expect(Money::toDecimal($shift->expectedCashAgorot()))->toBe(80.0)
        ->and($shift->takings())->toHaveKey('visa');
});

it('sets a table on a dine-in order that arrived without one', function () {
    $order = counterOrder(['table_number' => null]);

    Livewire::test(CashierBoard::class)->call('setTable', $order->reference, '7');

    expect($order->fresh()->table_number)->toBe('7');
});

it('advances an order only along its own ladder', function () {
    $order = counterOrder(['delivery_method' => 'pickup']);

    Livewire::test(CashierBoard::class)->call('advance', $order->reference, Order::FULFILMENT_PREPARING);
    expect($order->fresh()->status)->toBe(Order::FULFILMENT_PREPARING);

    // "في الطريق" belongs to delivery; a pickup tracker cannot draw it.
    Livewire::test(CashierBoard::class)->call('advance', $order->reference, Order::FULFILMENT_ON_WAY);
    expect($order->fresh()->status)->toBe(Order::FULFILMENT_PREPARING);
});

// ─── the report ─────────────────────────────────────────────────────────────

function financials(): array
{
    return (new FinancialReport(now()->startOfDay(), now()->endOfDay()))->toArray();
}

it('counts sales when the money settled, not when the order was placed', function () {
    // Placed today but never paid: not takings.
    counterOrder();

    counterOrder(['total' => 45])->update([
        'payment_status' => Order::STATUS_PAID, 'paid_at' => now(),
    ]);

    $r = financials();

    expect($r['sales']['orders'])->toBe(1)
        ->and($r['sales']['gross'])->toBe(45.0);
});

it('splits sales by payment method and by channel', function () {
    counterOrder(['payment_method' => 'cash', 'total' => 30, 'delivery_method' => 'dine-in'])
        ->update(['payment_status' => Order::STATUS_PAID, 'paid_at' => now()]);

    counterOrder(['payment_method' => 'visa', 'total' => 70, 'delivery_method' => 'delivery'])
        ->update(['payment_status' => Order::STATUS_PAID, 'paid_at' => now()]);

    $r = financials();

    expect(collect($r['salesByMethod'])->pluck('total', 'method')->all())
        ->toBe(['visa' => 70.0, 'cash' => 30.0])
        ->and(collect($r['salesByChannel'])->pluck('total', 'channel')->all())
        ->toHaveKeys(['dine-in', 'delivery']);
});

it('keeps wallet deposits out of sales, because they are not revenue', function () {
    $customer = Customer::firstOrCreate(['phone' => '0599123456'], ['name' => 'أحمد علي']);
    $wallet   = app(WalletService::class);

    $request = $wallet->requestTopUp($customer, ['amount' => 200, 'method' => 'bop', 'receiptNote' => 'x']);
    $wallet->approveTopUp($request, $this->cashier);

    // Spending it later is the sale; the deposit itself is a liability. Booking
    // both as revenue counts the same shekel twice.
    counterOrder(['payment_method' => 'wallet', 'total' => 50])
        ->update(['payment_status' => Order::STATUS_PAID, 'paid_at' => now()]);

    $r = financials();

    expect($r['deposits']['approved'])->toBe(200.0)
        ->and($r['sales']['gross'])->toBe(50.0)
        ->and(collect($r['salesByMethod'])->firstWhere('method', 'wallet')['isNewMoney'])->toBeFalse();
});

it('reports the wallet balance the shop still owes', function () {
    $customer = Customer::firstOrCreate(['phone' => '0599123456'], ['name' => 'أحمد علي']);

    app(WalletService::class)->credit($customer, Money::toAgorot(150), 'شحن');
    app(WalletService::class)->debit($customer, Money::toAgorot(40), 'دفع');

    expect(financials()['deposits']['outstanding'])->toBe(110.0);
});

it('separates discounts, refunds and cancellations', function () {
    Coupon::create(['code' => 'GLACE10', 'type' => 'fixed', 'value' => 10]);

    counterOrder(['subtotal' => 40, 'discount' => 10, 'total' => 30, 'coupon_code' => 'GLACE10'])
        ->update(['payment_status' => Order::STATUS_PAID, 'paid_at' => now()]);

    counterOrder(['total' => 25])->update([
        'status' => Order::FULFILMENT_CANCELLED, 'cancelled_at' => now(),
    ]);

    counterOrder(['total' => 60])->update([
        'payment_status'  => Order::STATUS_PAID, 'paid_at' => now(),
        'refunded_amount' => 60, 'refunded_at' => now(),
    ]);

    $r = financials()['adjustments'];

    expect($r['discountTotal'])->toBe(10.0)
        ->and($r['byCoupon'][0]['code'])->toBe('GLACE10')
        ->and($r['cancelledOrders'])->toBe(1)
        ->and($r['cancelledValue'])->toBe(25.0)
        ->and($r['refundedTotal'])->toBe(60.0);
});

it('subtracts refunds from the net figure', function () {
    counterOrder(['total' => 100])->update([
        'payment_status'  => Order::STATUS_PAID, 'paid_at' => now(),
        'refunded_amount' => 30, 'refunded_at' => now(),
    ]);

    $r = financials()['sales'];

    expect($r['gross'])->toBe(100.0)
        ->and($r['refunded'])->toBe(30.0)
        ->and($r['net'])->toBe(70.0);
});

it('flags a shift that was left open rather than treating it as balanced', function () {
    CashierShift::create([
        'user_id' => $this->cashier->id, 'opened_at' => now(), 'opening_float' => 0,
    ]);

    $r = financials()['reconciliation'];

    // An uncounted drawer is not the same as one that matched.
    expect($r['shiftsOpen'])->toBe(1)
        ->and($r['shiftsClosed'])->toBe(0);
});

it('adds up short and over shifts separately', function () {
    foreach ([['counted' => 90, 'expected' => 100], ['counted' => 110, 'expected' => 100]] as $row) {
        CashierShift::create([
            'user_id'       => $this->cashier->id,
            'opened_at'     => now(),
            'closed_at'     => now(),
            'opening_float' => 0,
            'expected_cash' => $row['expected'],
            'counted_cash'  => $row['counted'],
            'difference'    => $row['counted'] - $row['expected'],
        ]);
    }

    $r = financials()['reconciliation'];

    // They must not net to zero and look clean — two problems, not none.
    expect($r['shortShifts'])->toBe(1)
        ->and($r['overShifts'])->toBe(1)
        ->and($r['difference'])->toBe(0.0);
});

it('renders the financial reports page', function () {
    Livewire::test(App\Filament\Pages\FinancialReports::class)->assertOk();
});
