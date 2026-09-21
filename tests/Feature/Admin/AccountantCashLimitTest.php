<?php

use App\Filament\Pages\CashierBoard;
use App\Models\CashierShift;
use App\Models\ChangeRefundRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

/**
 * An accountant takes at most 200 ₪ in cash for one order; above that, the
 * manager takes it. The change ceiling holds for everyone, on every button.
 */

function cashOrder(User $cashier, float $total, array $attributes = []): Order
{
    CashierShift::firstOrCreate(
        ['user_id' => $cashier->id, 'closed_at' => null],
        ['opened_at' => now()->subHour(), 'opening_float' => 0],
    );

    return Customer::firstOrCreate(['phone' => '0599123456'], ['name' => 'أحمد'])
        ->orders()->create(array_merge([
            'reference'       => Order::newReference(),
            'public_token'    => Order::newPublicToken(),
            'customer_name'   => 'أحمد',
            'customer_phone'  => '0599123456',
            'delivery_method' => 'pickup',
            'payment_method'  => 'cash',
            'subtotal'        => $total,
            'total'           => $total,
            'currency'        => 'ILS',
        ], $attributes));
}

it('lets an accountant take cash up to 200 ₪', function () {
    $accountant = User::factory()->accountant()->create();
    $this->actingAs($accountant);

    $order = cashOrder($accountant, 200);

    Livewire::test(CashierBoard::class)->call('markPaid', $order->reference);

    expect($order->fresh()->isPaid())->toBeTrue();
});

it('refuses an accountant a cash order over 200 ₪', function () {
    $accountant = User::factory()->accountant()->create();
    $this->actingAs($accountant);

    $order = cashOrder($accountant, 250);

    Livewire::test(CashierBoard::class)
        ->call('markPaid', $order->reference)
        ->assertNotified('لا يمكن استلام هذا المبلغ');

    expect($order->fresh()->isPaid())->toBeFalse();
});

it('counts what is handed over, not only the total', function () {
    // A 150 ₪ order paid with 220: the accountant would be holding 220.
    $accountant = User::factory()->accountant()->create();
    $this->actingAs($accountant);

    $order = cashOrder($accountant, 150);

    Livewire::test(CashierBoard::class)->call('markPaidToWallet', $order->reference, 220);

    expect($order->fresh()->isPaid())->toBeFalse();

    Livewire::test(CashierBoard::class)->call('markPaidToWallet', $order->reference, 200);

    expect($order->fresh()->isPaid())->toBeTrue()
        ->and((float) $order->fresh()->change_credited)->toBe(50.0);
});

it('holds on the take-cash-and-refund-the-change button too', function () {
    $accountant = User::factory()->accountant()->create();
    $this->actingAs($accountant);

    $order = cashOrder($accountant, 150);

    Livewire::test(CashierBoard::class)->call('markPaidWithChange', $order->reference, 250, [
        'refund_method' => 'jawwal', 'holder_name' => 'أحمد', 'holder_phone' => '0599123456',
    ]);

    expect($order->fresh()->isPaid())->toBeFalse()
        ->and(ChangeRefundRequest::count())->toBe(0);
});

it('lets a manager take a cash order of any size', function () {
    $manager = User::factory()->create();
    $this->actingAs($manager);

    $order = cashOrder($manager, 450);

    Livewire::test(CashierBoard::class)->call('markPaid', $order->reference);

    expect($order->fresh()->isPaid())->toBeTrue();
});

it('still holds the manager to the change ceiling', function () {
    $manager = User::factory()->create();
    $this->actingAs($manager);

    $order = cashOrder($manager, 36);

    // 300 for 36 is 264 change — refused for anyone, on the refund button as
    // well: that button used to create a refund of any size.
    Livewire::test(CashierBoard::class)->call('markPaidWithChange', $order->reference, 300, [
        'refund_method' => 'jawwal', 'holder_name' => 'أحمد', 'holder_phone' => '0599123456',
    ]);

    expect($order->fresh()->isPaid())->toBeFalse()
        ->and(ChangeRefundRequest::count())->toBe(0);
});

it('leaves card payments alone', function () {
    $accountant = User::factory()->accountant()->create();
    $this->actingAs($accountant);

    $order = cashOrder($accountant, 350, ['payment_method' => 'visa']);

    Livewire::test(CashierBoard::class)->call('markPaid', $order->reference);

    expect($order->fresh()->isPaid())->toBeTrue();
});
