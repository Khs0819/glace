<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\QuoteCartRequest;
use App\Http\Requests\StoreOrderRequest;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Checkout\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    public function __construct(private readonly CheckoutService $checkout) {}

    /**
     * Price a cart without keeping it — what the cart screen totals with.
     *
     * Same rules as placing the order, so the customer never sees one number
     * here and a different one at checkout.
     */
    public function quote(QuoteCartRequest $request): JsonResponse
    {
        return response()->json($this->checkout->quote($request->validated()['items'])->toArray());
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        $order = $this->checkout->place($request->validated());

        return response()->json([
            // Handed over exactly once. The storefront has to keep it: every
            // later call about this order is authorised by it and nothing else.
            'token' => $order->public_token,
            'order' => new OrderResource($order),
        ], 201);
    }

    /**
     * Accepts either the public reference the response publishes as `id`
     * ("ORD-M3K2A1") or the internal UUID, so a client that stored the older
     * identifier keeps working.
     */
    public function show(Request $request, string $order): JsonResponse
    {
        $record = Order::where('reference', $order)->orWhere('id', $order)->first();

        // 404, not 403 — a wrong or missing token must not confirm the order
        // exists at all.
        abort_if($record === null || ! $record->tokenMatches($request->input('token')), 404);

        return response()->json(new OrderResource($record->load('items', 'payments')));
    }
}
