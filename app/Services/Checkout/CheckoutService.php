<?php

namespace App\Services\Checkout;

use App\Models\Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Turns a priced cart into an order.
 *
 * Nothing here reads a price off the request. The cart is priced first, and the
 * order is written from that result — so the total a payment is raised against
 * is one this server computed from the catalog.
 */
class CheckoutService
{
    public function __construct(private readonly CartPricer $pricer) {}

    /**
     * Price a cart without keeping anything — what the cart screen shows.
     *
     * @param  array<int, array<string, mixed>>  $items
     */
    public function quote(array $items): PricedCart
    {
        return $this->pricer->price($items);
    }

    /**
     * @param  array<string, mixed>  $payload  validated by StoreOrderRequest
     */
    public function place(array $payload): Order
    {
        $cart = $this->pricer->price($payload['items']);

        if ($cart->totalAgorot() <= 0) {
            throw ValidationException::withMessages([
                'items' => 'لا يمكن إنشاء طلب بمبلغ صفر',
            ]);
        }

        return DB::transaction(function () use ($payload, $cart) {
            $order = Order::create([
                'reference'      => Order::newReference(),
                'public_token'   => Order::newPublicToken(),
                'customer_name'  => $payload['customer']['name'],
                'customer_phone' => $payload['customer']['phone'],
                'notes'          => $payload['customer']['notes'] ?? null,
                'status'         => Order::STATUS_PENDING,
                'subtotal'       => $cart->subtotal(),
                'total'          => $cart->total(),
                'currency'       => 'ILS',
            ]);

            foreach ($cart->lines as $line) {
                $order->items()->create($line->toOrderItemAttributes());
            }

            return $order->load('items');
        });
    }
}
