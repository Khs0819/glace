<?php

use App\Filament\Pages\CashierBoard;
use App\Models\CashierShift;
use App\Models\ChangeRefundRequest;
use App\Models\Customer;
use App\Models\Order;
use App\Models\Product;
use App\Models\TopUpRequest;
use App\Models\User;
use App\Models\Wallet;
use App\Services\Storefront\WalletService;
use Livewire\Livewire;
use Tests\Support\CatalogFactory;

/**
 * Going live: the trading history from the trial period is cleared, the shop
 * itself is not, and the counter's money ceilings hold.
 */

beforeEach(function () {
    $this->staff = User::factory()->create();
    $this->actingAs($this->staff);
});

function tradingHistory(): Customer
{
    $customer = Customer::create(['name' => 'أحمد', 'phone' => '0599123456']);

    app(WalletService::class)->credit($customer, 25000, 'رصيد تجربة');

    $customer->topUpRequests()->create([
        'amount' => 100, 'method' => 'bop', 'status' => TopUpRequest::STATUS_PENDING,
        'receipt_note' => 'تجربة',
    ]);

    $order = $customer->orders()->create([
        'reference'       => Order::newReference(),
        'public_token'    => Order::newPublicToken(),
        'customer_name'   => 'أحمد',
        'customer_phone'  => '0599123456',
        'delivery_method' => 'pickup',
        'payment_method'  => 'cash',
        'subtotal'        => 36,
        'total'           => 36,
        'currency'        => 'ILS',
    ]);

    $order->items()->create([
        'product_slug' => 'x', 'product_name' => 'ميلك شيك', 'kind' => 'flat',
        'selection' => [], 'description' => '',
        'unit_price' => 36, 'quantity' => 1, 'addons_total' => 0, 'line_total' => 36,
    ]);

    ChangeRefundRequest::create([
        'order_id' => $order->getKey(), 'order_reference' => $order->reference, 'amount' => 4,
        'holder_name' => 'أحمد', 'holder_phone' => '0599123456', 'refund_method' => 'cash',
        'created_by' => test()->staff->id,
    ]);

    CashierShift::create([
        'user_id' => test()->staff->id, 'opened_at' => now()->subHour(), 'opening_float' => 0,
    ]);

    CatalogFactory::flatList('milkshake', ['name' => 'ميلك شيك']);

    return $customer;
}

it('clears the trial trading and zeroes every wallet', function () {
    $customer = tradingHistory();

    expect(Wallet::sum('balance'))->toBeGreaterThan(0);

    $this->artisan('launch:reset --force')->assertSuccessful();

    expect(Order::count())->toBe(0)
        ->and(TopUpRequest::count())->toBe(0)
        ->and(ChangeRefundRequest::count())->toBe(0)
        ->and(CashierShift::count())->toBe(0)
        ->and(Wallet::count())->toBe(0)
        ->and(app(WalletService::class)->walletFor($customer->fresh())->balance)->toBe(0.0);
});

it('keeps the shop itself — customers, menu and staff', function () {
    $customer = tradingHistory();

    $this->artisan('launch:reset --force')->assertSuccessful();

    expect(Customer::whereKey($customer->getKey())->exists())->toBeTrue()
        ->and(Product::count())->toBeGreaterThan(0)
        ->and(User::count())->toBeGreaterThan(0);
});

it('does nothing without an answer to the confirmation', function () {
    tradingHistory();

    $this->artisan('launch:reset')
        ->expectsConfirmation('هل أخذت نسخة احتياطية من قاعدة البيانات وتريد المتابعة؟', 'no')
        ->assertFailed();

    expect(Order::count())->toBe(1);
});

// ─── the counter's ceiling on change ────────────────────────────────────────

function ceilingOrder(): Order
{
    CashierShift::create([
        'user_id' => test()->staff->id, 'opened_at' => now()->subHour(), 'opening_float' => 0,
    ]);

    return Customer::firstOrCreate(['phone' => '0599123456'], ['name' => 'أحمد'])
        ->orders()->create([
            'reference'       => Order::newReference(),
            'public_token'    => Order::newPublicToken(),
            'customer_name'   => 'أحمد',
            'customer_phone'  => '0599123456',
            'delivery_method' => 'pickup',
            'payment_method'  => 'cash',
            'subtotal'        => 36,
            'total'           => 36,
            'currency'        => 'ILS',
        ]);
}

it('refuses to bank change over the ceiling into a wallet', function () {
    $order = ceilingOrder();

    // 300 against a 36 ₪ order is 264 change — a typo, not a banknote.
    Livewire::test(CashierBoard::class)->call('markPaidToWallet', $order->reference, 300);

    expect($order->fresh()->isPaid())->toBeFalse();
});

it('still banks change up to the ceiling', function () {
    $order = ceilingOrder();

    Livewire::test(CashierBoard::class)->call('markPaidToWallet', $order->reference, 235);

    expect($order->fresh()->isPaid())->toBeTrue()
        ->and((float) $order->fresh()->change_credited)->toBe(199.0);
});

it('refuses a refund request over the ceiling', function () {
    $order = ceilingOrder();
    $order->update(['tendered_amount' => 300, 'payment_status' => Order::STATUS_PAID, 'paid_at' => now()]);

    Livewire::test(CashierBoard::class)->call('createStandaloneRefund', $order->reference, [
        'refund_method' => 'jawwal', 'holder_name' => 'أحمد', 'holder_phone' => '0599123456',
    ]);

    expect(ChangeRefundRequest::count())->toBe(0);
});
