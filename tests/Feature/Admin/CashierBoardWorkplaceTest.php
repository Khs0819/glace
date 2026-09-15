<?php

use App\Filament\Pages\CashierBoard;
use App\Filament\Resources\CashierShiftResource;
use App\Filament\Resources\DriverResource;
use App\Models\CashierShift;
use App\Models\ChangeRefundRequest;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverSettlement;
use App\Models\Order;
use App\Models\User;
use App\Services\Checkout\Money;
use App\Services\Drivers\DriverPayoutService;
use App\Services\Storefront\WalletService;
use Livewire\Livewire;

/**
 * The cashier screen as the shift's whole workplace: everything reachable
 * without leaving it, and every figure on it traceable to the orders.
 */

beforeEach(function () {
    $this->cashier = User::factory()->create();
    $this->actingAs($this->cashier);
});

function boardShift(array $attributes = []): CashierShift
{
    return CashierShift::create(array_merge([
        'user_id'       => test()->cashier->id,
        'opened_at'     => now()->subHour(),
        'opening_float' => 0,
    ], $attributes));
}

function boardOrder(array $attributes = [], bool $guest = false): Order
{
    $values = array_merge([
        'reference'       => Order::newReference(),
        'public_token'    => Order::newPublicToken(),
        'customer_name'   => 'أحمد',
        'customer_phone'  => '0599123456',
        'delivery_method' => 'pickup',
        'payment_method'  => 'cash',
        'subtotal'        => 36,
        'total'           => 36,
        'currency'        => 'ILS',
    ], $attributes);

    if ($guest) {
        return Order::create($values);
    }

    return Customer::firstOrCreate(['phone' => '0599123456'], ['name' => 'أحمد'])
        ->orders()->create($values);
}

function boardDelivery(float $fee = 10): Order
{
    return boardOrder([
        'delivery_method' => 'delivery',
        'payment_method'  => 'jawwal-manual',
        'delivery_fee'    => $fee,
        'total'           => 36 + $fee,
        'status'          => Order::FULFILMENT_READY,
    ]);
}

$refundData = ['refund_method' => 'jawwal', 'holder_name' => 'أحمد', 'holder_phone' => '0599123456'];

// ─── the board belongs to the shift ─────────────────────────────────────────

it('shows nothing without an open shift, but says how many orders are waiting', function () {
    boardOrder();

    $response = $this->getJson(route('receipts.queue'))->assertOk();

    expect($response->json('shiftOpen'))->toBeFalse()
        ->and($response->json('orders'))->toBe([])
        ->and($response->json('waiting'))->toBe(1);
});

it('starts a new shift from an empty board', function () {
    // Placed before this shift opened: it belongs to the previous one's archive.
    $old = boardOrder();
    $old->forceFill(['created_at' => now()->subHours(3)])->save();

    boardShift(['opened_at' => now()->subMinute()]);
    $new = boardOrder();

    $references = collect($this->getJson(route('receipts.queue'))->json('orders'))->pluck('reference')->all();

    expect($references)->toBe([$new->reference]);
});

it('carries customer notes, only when there are any, and the live shift figures', function () {
    boardShift();
    boardOrder(['notes' => 'بدون سكر', 'payment_status' => Order::STATUS_PAID, 'paid_at' => now()]);
    boardOrder(['notes' => '   ']);

    $response = $this->getJson(route('receipts.queue'));
    $notes    = collect($response->json('orders'))->pluck('notes')->all();

    expect($notes)->toContain('بدون سكر')
        ->and($notes)->toContain(null)
        ->and((float) $response->json('summary.net'))->toBe(36.0);
});

// ─── cash and change ────────────────────────────────────────────────────────

it('sends the change to the wallet when the cashier types what was handed over', function () {
    boardShift();
    $order = boardOrder();

    Livewire::test(CashierBoard::class)->call('markPaidToWallet', $order->reference, 50);

    $order = $order->fresh();

    expect($order->isPaid())->toBeTrue()
        ->and((float) $order->tendered_amount)->toBe(50.0)
        ->and((float) $order->change_credited)->toBe(14.0)
        ->and(app(WalletService::class)->walletFor($order->customer)->fresh()->balance)->toBe(14.0);
});

it('simply takes the payment when nothing is over the total', function () {
    boardShift();
    $order = boardOrder();

    Livewire::test(CashierBoard::class)->call('markPaidToWallet', $order->reference, 0);

    expect($order->fresh()->isPaid())->toBeTrue()
        ->and((float) $order->fresh()->change_credited)->toBe(0.0);
});

it('refuses less than the total', function () {
    boardShift();
    $order = boardOrder();

    Livewire::test(CashierBoard::class)->call('markPaidToWallet', $order->reference, 20);

    expect($order->fresh()->isPaid())->toBeFalse();
});

it('will not send a guest change to a wallet they do not have', function () {
    boardShift();
    $order = boardOrder([], guest: true);

    Livewire::test(CashierBoard::class)->call('markPaidToWallet', $order->reference, 50);

    expect($order->fresh()->isPaid())->toBeFalse();
});

it('computes the refund amount instead of taking it from the screen', function () use ($refundData) {
    boardShift();
    $order = boardOrder(['tendered_amount' => 50, 'payment_status' => Order::STATUS_PAID, 'paid_at' => now()]);

    Livewire::test(CashierBoard::class)->call('createStandaloneRefund', $order->reference, $refundData);

    expect((float) ChangeRefundRequest::sole()->amount)->toBe(14.0);
});

it('cannot refund the same change twice', function () use ($refundData) {
    boardShift();
    $order = boardOrder(['tendered_amount' => 50, 'payment_status' => Order::STATUS_PAID, 'paid_at' => now()]);

    $board = Livewire::test(CashierBoard::class);
    $board->call('createStandaloneRefund', $order->reference, $refundData);
    $board->call('createStandaloneRefund', $order->reference, $refundData);

    expect(ChangeRefundRequest::count())->toBe(1);
});

it('offers no transfer refund for change that already went to the wallet', function () use ($refundData) {
    boardShift();
    $order = boardOrder();

    Livewire::test(CashierBoard::class)->call('markPaidToWallet', $order->reference, 50);
    Livewire::test(CashierBoard::class)->call('createStandaloneRefund', $order->reference, $refundData);

    expect($order->fresh()->refundableChange())->toBe(0.0)
        ->and(ChangeRefundRequest::count())->toBe(0);
});

it('keeps the whole note in the drawer until change is paid out in cash', function () {
    $shift = boardShift();
    $order = boardOrder([
        'tendered_amount' => 50,
        'payment_status'  => Order::STATUS_PAID,
        'paid_at'         => now(),
        'shift_id'        => $shift->id,
    ]);

    // Nothing was handed back at the counter, so all 50 is in the drawer — a
    // transfer refund later comes out of the bank, not out of this till.
    expect(Money::toDecimal($shift->expectedCashAgorot()))->toBe(50.0);

    ChangeRefundRequest::create([
        'order_id' => $order->id, 'order_reference' => $order->reference, 'amount' => 14,
        'holder_name' => 'أحمد', 'holder_phone' => '0599123456', 'refund_method' => 'cash',
        'status' => ChangeRefundRequest::STATUS_COMPLETED, 'created_by' => $this->cashier->id,
    ]);

    expect(Money::toDecimal($shift->fresh()->expectedCashAgorot()))->toBe(36.0);
});

// ─── transfers ──────────────────────────────────────────────────────────────

it('confirms a transfer from the details window once there is a receipt', function () {
    boardShift();
    $order = boardOrder(['payment_method' => 'jawwal-manual', 'receipt_note' => 'حوّلت 36']);

    $details = Livewire::test(CashierBoard::class)->instance()->orderDetails($order->reference);

    expect($details['canConfirmTransfer'])->toBeTrue()
        ->and($details['receiptNote'])->toBe('حوّلت 36');

    Livewire::test(CashierBoard::class)->call('confirmTransferPayment', $order->reference);

    expect($order->fresh()->isPaid())->toBeTrue()
        ->and($order->fresh()->paid_by)->toBe($this->cashier->id);
});

it('will not confirm a transfer nobody has seen proof of', function () {
    boardShift();
    $order = boardOrder(['payment_method' => 'jawwal-manual']);

    Livewire::test(CashierBoard::class)->call('confirmTransferPayment', $order->reference);

    expect($order->fresh()->isPaid())->toBeFalse();
});

// ─── statuses and drivers ───────────────────────────────────────────────────

it('prepares and readies every channel, and readies a delivery before the road', function () {
    expect((new Order(['delivery_method' => 'dine-in', 'status' => Order::FULFILMENT_REVIEW]))->allowedNextStatuses())
        ->toContain(Order::FULFILMENT_PREPARING, Order::FULFILMENT_READY);

    expect((new Order(['delivery_method' => 'delivery', 'status' => Order::FULFILMENT_READY]))->allowedNextStatuses())
        ->toBe([Order::FULFILMENT_ON_WAY, Order::FULFILMENT_RECEIVED, Order::FULFILMENT_CANCELLED, Order::FULFILMENT_REFUNDED]);
});

it('books the delivery fee to the driver who is chosen', function () {
    boardShift();
    $order  = boardDelivery(10);
    $driver = Driver::create(['name' => 'أحمد سعيد', 'phone' => '0599876543']);

    Livewire::test(CashierBoard::class)->call('assignDriver', $order->reference, $driver->id);

    expect($driver->balance())->toBe(10.0)
        ->and(DriverSettlement::sole()->delivery_fee)->toBe(10.0);
});

it('moves the fee when a different driver is chosen, instead of paying both', function () {
    boardShift();
    $order  = boardDelivery(10);
    $first  = Driver::create(['name' => 'الأول', 'phone' => '0599000001']);
    $second = Driver::create(['name' => 'الثاني', 'phone' => '0599000002']);

    Livewire::test(CashierBoard::class)->call('assignDriver', $order->reference, $first->id);
    Livewire::test(CashierBoard::class)->call('assignDriver', $order->reference, $second->id);

    expect($first->balance())->toBe(0.0)
        ->and($second->balance())->toBe(10.0)
        ->and(DriverSettlement::count())->toBe(1);
});

it('takes the fee back when the delivery is cancelled before the driver is paid', function () {
    boardShift();
    $order  = boardDelivery(10);
    $driver = Driver::create(['name' => 'أحمد سعيد', 'phone' => '0599876543']);

    Livewire::test(CashierBoard::class)->call('assignDriver', $order->reference, $driver->id);
    Livewire::test(CashierBoard::class)->call('advance', $order->reference, Order::FULFILMENT_CANCELLED);

    expect($driver->balance())->toBe(0.0);
});

it('clears a driver balance with one payout, and only once', function () {
    boardShift();
    $driver = Driver::create(['name' => 'أحمد سعيد', 'phone' => '0599876543']);

    Livewire::test(CashierBoard::class)->call('assignDriver', boardDelivery(10)->reference, $driver->id);
    Livewire::test(CashierBoard::class)->call('assignDriver', boardDelivery(15)->reference, $driver->id);

    expect($driver->balance())->toBe(25.0);

    $payout = app(DriverPayoutService::class)->pay($driver, 'driver-payouts/slip.png', null, $this->cashier->id);

    expect($payout->amount)->toBe(25.0)
        ->and($driver->balance())->toBe(0.0)
        ->and(app(DriverPayoutService::class)->pay($driver, 'driver-payouts/again.png'))->toBeNull();
});

it('adds a driver without leaving the cashier screen', function () {
    Livewire::test(CashierBoard::class)
        ->callAction('createDriver', ['name' => 'سائق جديد', 'phone' => '0599111222', 'company' => null])
        ->assertHasNoActionErrors();

    expect(Driver::where('name', 'سائق جديد')->exists())->toBeTrue();
});

it('shows the add-driver button on the drivers page', function () {
    Livewire::test(DriverResource\Pages\ListDrivers::class)->assertActionVisible('create');
});

it('opens the transfer-receipt window for a pending refund on the screen', function () {
    boardShift();
    $order   = boardOrder(['tendered_amount' => 50, 'payment_status' => Order::STATUS_PAID, 'paid_at' => now()]);
    $request = ChangeRefundRequest::create([
        'order_id' => $order->id, 'order_reference' => $order->reference, 'amount' => 14,
        'holder_name' => 'أحمد', 'holder_phone' => '0599123456', 'refund_method' => 'jawwal',
        'created_by' => $this->cashier->id,
    ]);

    Livewire::test(CashierBoard::class)
        ->mountAction('completeRefund', ['id' => $request->id])
        ->assertActionMounted('completeRefund');

    expect(Livewire::test(CashierBoard::class)->instance()->pendingRefunds()[0]['amount'])->toBe(14.0);
});

// ─── closing ────────────────────────────────────────────────────────────────

it('freezes net sales on the shift when it closes', function () {
    $shift = boardShift();
    boardOrder(['total' => 100, 'payment_status' => Order::STATUS_PAID, 'paid_at' => now(), 'shift_id' => $shift->id]);
    boardOrder([
        'total' => 40, 'payment_status' => Order::STATUS_PAID, 'paid_at' => now(), 'shift_id' => $shift->id,
        'refunded_amount' => 15, 'refunded_at' => now(),
    ]);

    Livewire::test(CashierBoard::class)->callAction('closeShift', ['counted_cash' => 125, 'notes' => null]);

    $summary = $shift->fresh()->summary;

    expect((float) $summary['gross'])->toBe(140.0)
        ->and((float) $summary['refunds'])->toBe(15.0)
        ->and((float) $summary['net'])->toBe(125.0);
});

it('shows the sales and the order archive on the shift page', function () {
    $shift = boardShift();
    $order = boardOrder(['payment_status' => Order::STATUS_PAID, 'paid_at' => now(), 'shift_id' => $shift->id]);

    $this->get(CashierShiftResource::getUrl('view', ['record' => $shift]))
        ->assertSuccessful()
        ->assertSee('صافي المبيعات')
        ->assertSee('أرشيف طلبات الوردية')
        ->assertSee($order->reference);
});

// ─── printing ───────────────────────────────────────────────────────────────

it('reports a print result as a real success or failure notice', function () {
    Livewire::test(CashierBoard::class)
        ->call('sendPrintAlert', '❌ فشلت الطباعة', 'الطلب ORD-TEST01', 'danger')
        ->assertNotified('❌ فشلت الطباعة');
});

it('returns both side panels in a single call', function () {
    Driver::create(['name' => 'أحمد سعيد', 'phone' => '0599876543']);

    $panels = Livewire::test(CashierBoard::class)->instance()->panels();

    expect($panels)->toHaveKeys(['drivers', 'refunds'])
        ->and($panels['drivers'][0]['name'])->toBe('أحمد سعيد')
        ->and($panels['refunds'])->toBe([]);
});

it('carries the captain note to the board and the details window', function () {
    boardShift();
    $order = boardDelivery(10);
    $order->update(['captain_note' => 'الطابق الثالث']);

    $feed = collect($this->getJson(route('receipts.queue'))->json('orders'))->firstWhere('reference', $order->reference);

    expect($feed['captainNote'])->toBe('الطابق الثالث')
        ->and(Livewire::test(CashierBoard::class)->instance()->orderDetails($order->reference)['captainNote'])
        ->toBe('الطابق الثالث');
});

it('shows who sent a transfer on the board and in the details window', function () {
    boardShift();
    $order = boardOrder(['payment_method' => 'bop', 'receipt_note' => 'x', 'sender_account_name' => 'محمود سالم']);

    $feed = collect($this->getJson(route('receipts.queue'))->json('orders'))->firstWhere('reference', $order->reference);

    expect($feed['senderAccountName'])->toBe('محمود سالم')
        ->and(Livewire::test(CashierBoard::class)->instance()->orderDetails($order->reference)['senderAccountName'])
        ->toBe('محمود سالم');
});
