<?php

use App\Filament\Resources\DeliveryZoneResource;
use App\Models\Customer;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\User;
use App\Services\Auth\CustomerAuthService;
use App\Services\Printing\ReceiptDocument;
use Livewire\Livewire;
use Tests\Support\CatalogFactory;

/**
 * A zone the shop delivers to for nothing, switched on and off from the
 * dashboard without losing the fee it normally charges.
 */

beforeEach(function () {
    fakePublicDisk();

    $this->zone = DeliveryZone::create(['id' => 'rimal', 'name' => 'الرمال', 'fee' => 10]);

    $this->customer = Customer::create(['name' => 'أحمد', 'phone' => '0599123456']);
    $this->headers  = ['Authorization' => app(CustomerAuthService::class)->issueToken($this->customer)];

    $this->address = $this->customer->addresses()->create([
        'type' => 'home', 'label' => 'المنزل', 'name' => 'أحمد', 'phone' => '0599123456',
        'city' => 'غزة', 'zone_id' => 'rimal', 'street' => 'شارع الجلاء', 'is_default' => true,
    ]);

    $this->product = CatalogFactory::flatList('milkshake', ['name' => 'ميلك شيك']);
    CatalogFactory::item($this->product, 'vanilla', ['label' => 'فانيلا', 'price' => 12]);
});

function freeDeliveryOrder(): Illuminate\Testing\TestResponse
{
    return test()->post('/api/orders', [
        'items' => json_encode([[
            'productId'  => test()->product->id,
            'name'       => 'ميلك شيك',
            'selections' => [['kind' => 'item', 'id' => 'vanilla', 'label' => 'فانيلا', 'qty' => 1]],
            'quantity'   => 2,
        ]]),
        'paymentMethod'     => 'jawwal-manual',
        'deliveryMethod'    => 'delivery',
        'addressId'         => test()->address->id,
        'receiptNote'       => 'حوّلت',
        'senderAccountName' => 'أحمد',
    ], test()->headers);
}

it('charges the zone fee while free delivery is off', function () {
    freeDeliveryOrder()->assertCreated()
        ->assertJsonPath('deliveryFee', fn ($v) => (float) $v === 10.0)
        ->assertJsonPath('freeDelivery', false)
        ->assertJsonPath('total', fn ($v) => (float) $v === 34.0);
});

it('charges nothing for delivery while the zone is on free delivery', function () {
    $this->zone->update(['free_delivery' => true]);

    freeDeliveryOrder()->assertCreated()
        ->assertJsonPath('deliveryFee', fn ($v) => (float) $v === 0.0)
        ->assertJsonPath('freeDelivery', true)
        ->assertJsonPath('total', fn ($v) => (float) $v === 24.0);
});

it('tells the storefront the zone is free, and what it normally costs', function () {
    $this->zone->update(['free_delivery' => true]);

    test()->getJson('/api/addresses/delivery-zones')
        ->assertOk()
        ->assertJsonPath('0.fee', fn ($v) => (float) $v === 0.0)
        ->assertJsonPath('0.freeDelivery', true)
        ->assertJsonPath('0.regularFee', fn ($v) => (float) $v === 10.0);
});

it('keeps the normal fee, so switching the offer off restores it', function () {
    $this->zone->update(['free_delivery' => true]);
    $this->zone->update(['free_delivery' => false]);

    freeDeliveryOrder()->assertCreated()->assertJsonPath('deliveryFee', fn ($v) => (float) $v === 10.0);
});

it('switches free delivery from the zones list in the dashboard', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(DeliveryZoneResource\Pages\ListDeliveryZones::class)
        ->call('updateTableColumnState', 'free_delivery', $this->zone->getKey(), true);

    expect($this->zone->fresh()->free_delivery)->toBeTrue();
});

it('says "توصيل مجاني" on the receipt', function () {
    $this->zone->update(['free_delivery' => true]);
    freeDeliveryOrder()->assertCreated();

    $slip = implode(PHP_EOL, array_column((new ReceiptDocument(Order::sole()))->lines(42), 'text'));

    expect($slip)->toContain('توصيل مجاني');
});

it('heads the receipt with the kind of order alone', function () {
    freeDeliveryOrder()->assertCreated();

    [, $kind] = (new ReceiptDocument(Order::sole(), ['name' => 'جلاسيه الأمير']))->titleLine();

    // The area is on the address line; it no longer crowds the heading.
    expect($kind)->toBe('توصيل');
});
