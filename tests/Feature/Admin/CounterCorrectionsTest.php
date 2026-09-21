<?php

use App\Filament\Pages\CashierBoard;
use App\Filament\Resources\ChangeRefundRequestResource;
use App\Models\CashierShift;
use App\Models\ChangeRefundRequest;
use App\Models\Customer;
use App\Models\Driver;
use App\Models\DriverPayout;
use App\Models\DriverSettlement;
use App\Models\Order;
use App\Models\User;
use App\Services\Storefront\WalletService;
use Livewire\Livewire;

/**
 * The mistakes the counter made in its first week, and how each is undone:
 * a delivery closed without a driver, the wrong driver chosen, "مسترد" pressed
 * on a paid order that then went nowhere.
 */

beforeEach(function () {
    $this->cashier = User::factory()->accountant()->create();
    $this->actingAs($this->cashier);

    CashierShift::create(['user_id' => $this->cashier->id, 'opened_at' => now()->subHour(), 'opening_float' => 0]);
});

function ccDelivery(array $attributes = []): Order
{
    return Customer::firstOrCreate(['phone' => '0599123456'], ['name' => 'أحمد'])
        ->orders()->create(array_merge([
            'reference'       => Order::newReference(),
            'public_token'    => Order::newPublicToken(),
            'customer_name'   => 'أحمد',
            'customer_phone'  => '0599123456',
            'delivery_method' => 'delivery',
            'payment_method'  => 'jawwal-manual',
            'subtotal'        => 36,
            'delivery_fee'    => 10,
            'total'           => 46,
            'currency'        => 'ILS',
            'status'          => Order::FULFILMENT_READY,
        ], $attributes));
}

function ccDriver(string $name = 'محمود'): Driver
{
    return Driver::create(['name' => $name, 'phone' => '059900' . random_int(1000, 9999)]);
}

// ─── no delivery closes without a driver ────────────────────────────────────

it('will not close a delivery as received before a driver is named', function () {
    $order = ccDelivery();

    Livewire::test(CashierBoard::class)->call('advance', $order->reference, Order::FULFILMENT_RECEIVED);

    expect($order->fresh()->status)->toBe(Order::FULFILMENT_READY);
});

it('does not offer "مسترد" as a status to pick', function () {
    expect(ccDelivery()->allowedNextStatuses())->not->toContain(Order::FULFILMENT_REFUNDED);
});

it('refuses "مسترد" sent as a bare status', function () {
    $order = ccDelivery(['payment_status' => Order::STATUS_PAID, 'paid_at' => now()]);

    Livewire::test(CashierBoard::class)->call('advance', $order->reference, Order::FULFILMENT_REFUNDED);

    expect($order->fresh()->status)->toBe(Order::FULFILMENT_READY);
});

// ─── undoing a status pressed by mistake ────────────────────────────────────

it('lets the counter put a delivered order back, then give it a driver', function () {
    // The case from the shop: "تم الاستلام" pressed with no driver, after
    // which neither the status nor the driver could be changed.
    $order = ccDelivery(['status' => Order::FULFILMENT_RECEIVED, 'received_at' => now()]);

    expect($order->correctableStatuses())->toContain(Order::FULFILMENT_READY);

    Livewire::test(CashierBoard::class)
        ->callAction('correctStatus', ['status' => Order::FULFILMENT_READY], ['reference' => $order->reference])
        ->assertHasNoActionErrors();

    expect($order->fresh()->status)->toBe(Order::FULFILMENT_READY)
        ->and($order->fresh()->received_at)->toBeNull();

    $driver = ccDriver();
    Livewire::test(CashierBoard::class)->call('assignDriver', $order->reference, $driver->id);

    expect($order->fresh()->driver_id)->toBe($driver->id)
        ->and($order->fresh()->status)->toBe(Order::FULFILMENT_ON_WAY);
});

it('re-opens a cancellation pressed on the wrong card, and books the driver fee again', function () {
    $driver = ccDriver();
    $order  = ccDelivery(['status' => Order::FULFILMENT_ON_WAY, 'driver_id' => $driver->id]);

    Livewire::test(CashierBoard::class)->call('advance', $order->reference, Order::FULFILMENT_CANCELLED);
    expect(DriverSettlement::where('order_id', $order->id)->exists())->toBeFalse();

    Livewire::test(CashierBoard::class)
        ->callAction('correctStatus', ['status' => Order::FULFILMENT_ON_WAY], ['reference' => $order->reference]);

    expect($order->fresh()->status)->toBe(Order::FULFILMENT_ON_WAY)
        ->and($order->fresh()->cancelled_at)->toBeNull()
        ->and((float) DriverSettlement::where('order_id', $order->id)->sole()->delivery_fee)->toBe(10.0);
});

it('offers no correction on a refunded order — the money has moved', function () {
    $order = ccDelivery(['status' => Order::FULFILMENT_REFUNDED, 'refunded_at' => now(), 'refunded_amount' => 46]);

    expect($order->correctableStatuses())->toBe([]);
});

// ─── the wrong driver ───────────────────────────────────────────────────────

it('swaps the driver on the road and moves the fee with him', function () {
    [$wrong, $right] = [ccDriver('خطأ'), ccDriver('صحيح')];
    $order = ccDelivery();

    $board = Livewire::test(CashierBoard::class);
    $board->call('assignDriver', $order->reference, $wrong->id);
    $board->call('assignDriver', $order->reference, $right->id);

    $settlement = DriverSettlement::where('order_id', $order->id)->sole();

    expect($order->fresh()->driver['name'])->toBe('صحيح')
        ->and($settlement->driver_id)->toBe($right->id);
});

it('changes the driver on an order already received, without sending it back out', function () {
    [$wrong, $right] = [ccDriver('خطأ'), ccDriver('صحيح')];
    $order = ccDelivery(['status' => Order::FULFILMENT_RECEIVED, 'driver_id' => $wrong->id]);

    Livewire::test(CashierBoard::class)->call('assignDriver', $order->reference, $right->id);

    expect($order->fresh()->driver_id)->toBe($right->id)
        ->and($order->fresh()->status)->toBe(Order::FULFILMENT_RECEIVED);
});

it('will not rewrite the driver of a delivery he has already been paid for', function () {
    [$paid, $other] = [ccDriver('مدفوع'), ccDriver('آخر')];
    $order = ccDelivery(['status' => Order::FULFILMENT_RECEIVED, 'driver_id' => $paid->id]);

    $payout = DriverPayout::create(['driver_id' => $paid->id, 'amount' => 10, 'paid_at' => now()]);
    DriverSettlement::create([
        'driver_id' => $paid->id, 'order_id' => $order->id, 'order_reference' => $order->reference,
        'order_total' => 46, 'delivery_fee' => 10, 'payment_method' => 'jawwal-manual',
        'payout_id' => $payout->id,
    ]);

    Livewire::test(CashierBoard::class)->call('assignDriver', $order->reference, $other->id);

    expect($order->fresh()->driver_id)->toBe($paid->id);
});

it('shows the change-driver and correct-status buttons on the card', function () {
    $order = ccDelivery(['status' => Order::FULFILMENT_RECEIVED, 'driver_id' => ccDriver()->id]);

    $card = collect($this->getJson(route('receipts.queue'))->json('orders'))->firstWhere('reference', $order->reference);

    expect($card['canChangeDriver'])->toBeTrue()
        ->and($card['canCorrect'])->toBeTrue();
});

// ─── refunding a paid order ─────────────────────────────────────────────────

it('refunds a paid order to the wallet and closes it', function () {
    $order = ccDelivery(['payment_status' => Order::STATUS_PAID, 'paid_at' => now()]);

    Livewire::test(CashierBoard::class)
        ->callAction('refundOrder', ['method' => 'wallet'], ['reference' => $order->reference])
        ->assertHasNoActionErrors();

    expect($order->fresh()->status)->toBe(Order::FULFILMENT_REFUNDED)
        ->and((float) $order->fresh()->refunded_amount)->toBe(46.0)
        ->and(app(WalletService::class)->walletFor($order->customer)->fresh()->balance)->toBe(46.0);
});

it('sends a transfer refund to the refunds list, and closes the order when it is sent', function () {
    $order = ccDelivery(['payment_status' => Order::STATUS_PAID, 'paid_at' => now()]);

    Livewire::test(CashierBoard::class)->callAction('refundOrder', [
        'method' => 'jawwal', 'holder_name' => 'أحمد', 'holder_phone' => '0599123456', 'notes' => 'ألغى الزبون',
    ], ['reference' => $order->reference]);

    $request = ChangeRefundRequest::sole();

    // In the refunds list, for the whole amount, and marked as a whole order.
    expect($request->kind)->toBe(ChangeRefundRequest::KIND_ORDER)
        ->and((float) $request->amount)->toBe(46.0)
        ->and($order->fresh()->status)->toBe(Order::FULFILMENT_CANCELLED)
        ->and($order->fresh()->isRefunded())->toBeFalse();

    // Not mistaken for returned change.
    expect($order->fresh()->refundableChange())->toBe(0.0);

    $request->complete('refund-receipts/slip.png', $this->cashier->id);

    expect($order->fresh()->status)->toBe(Order::FULFILMENT_REFUNDED)
        ->and((float) $order->fresh()->refunded_amount)->toBe(46.0)
        ->and($order->fresh()->refund_method)->toBe(Order::REFUND_TRANSFER);
});

it('closes the order when the refund is marked sent from the refunds page too', function () {
    fakePublicDisk();
    $order = ccDelivery(['payment_status' => Order::STATUS_PAID, 'paid_at' => now()]);

    Livewire::test(CashierBoard::class)->callAction('refundOrder', [
        'method' => 'bop', 'holder_name' => 'أحمد', 'holder_phone' => '0599123456',
    ], ['reference' => $order->reference]);

    Livewire::test(ChangeRefundRequestResource\Pages\ListChangeRefundRequests::class)
        ->callTableAction('markCompleted', ChangeRefundRequest::sole(), [
            'transfer_receipt' => Illuminate\Http\UploadedFile::fake()->image('slip.png'),
        ]);

    expect($order->fresh()->status)->toBe(Order::FULFILMENT_REFUNDED);
});

it('will not refund an order nobody has paid for', function () {
    $order = ccDelivery();

    Livewire::test(CashierBoard::class)->callAction('refundOrder', ['method' => 'cash'], ['reference' => $order->reference]);

    expect($order->fresh()->isRefunded())->toBeFalse()
        ->and(ChangeRefundRequest::count())->toBe(0);
});
