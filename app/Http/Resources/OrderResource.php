<?php

namespace App\Http\Resources;

use App\Models\Payment;
use App\Services\Checkout\CartItemPresenter;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An order as the storefront sees it (handoff 12 §4).
 *
 * `id` is the short reference — "ORD-M3K2A1" — because that is what the
 * storefront routes on and prints. The internal UUID is never published: it is
 * a database key, not a customer-facing identifier.
 *
 * `token` is deliberately absent: it is returned once, by the create call, and
 * never echoed again — it is the only thing standing between a guest order and
 * anyone who can guess a URL.
 */
class OrderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->reference,
            'reference' => $this->reference,

            // Two independent axes: what the kitchen is doing, and where the
            // money got to. Neither implies the other.
            'status'        => $this->status,
            'paymentStatus' => $this->payment_status,
            'statusLabel'   => $this->statusLabel(),
            'isFinal'       => $this->isFinal(),

            'paymentMethod'  => $this->payment_method,
            'deliveryMethod' => $this->delivery_method,
            'tableNumber'    => $this->table_number,

            'customer' => [
                'name'  => $this->customer_name,
                'phone' => $this->customer_phone,
            ],

            'address' => $this->address,
            'notes'   => $this->notes,

            'items' => $this->whenLoaded('items', fn () => $this->items->map(function ($item) {
                $selection = $item->selection ?? [];

                return [
                    'productId'   => $item->product_id === null ? null : (string) $item->product_id,
                    'productSlug' => $item->product_slug,
                    'name'        => $item->product_name,
                    'image'       => $item->imageUrl(),
                    'type'        => CartItemPresenter::type($selection),
                    'container'   => CartItemPresenter::container($selection),

                    'selections'     => CartItemPresenter::selections($selection),
                    'addonTotal'     => CartItemPresenter::addonTotal($selection),
                    'flatSelections' => CartItemPresenter::flatSelections($selection),
                    'flatAddonTotal' => CartItemPresenter::flatAddonTotal($selection),

                    'unitPrice' => $item->unit_price,
                    'quantity'  => $item->quantity,

                    // Kept alongside the storefront shape: the dashboard and the
                    // kitchen ticket read these, and they are what the pricer
                    // actually resolved.
                    'description' => $item->description,
                    'selection'   => $selection,
                    'addonsTotal' => $item->addons_total,
                    'lineTotal'   => $item->line_total,
                ];
            })->values()),

            'subtotal'    => $this->subtotal,
            'couponCode'  => $this->coupon_code,
            'discount'    => $this->discount,
            'deliveryFee' => $this->delivery_fee,
            'total'       => $this->total,
            'currency'    => $this->currency,

            'receiptImage' => $this->receiptImageUrl(),
            'receiptNote'  => $this->receipt_note,

            'preparationTime'       => $this->preparation_time,
            'estimatedDeliveryTime' => $this->estimated_delivery_time,

            'driver'           => $this->driver,
            'driverAssignedAt' => $this->driver_assigned_at?->toIso8601String(),

            'scheduledFor' => $this->scheduled_for?->toIso8601String(),
            'cancelReason' => $this->cancel_reason,

            'createdAt'   => $this->created_at?->toIso8601String(),
            'paidAt'      => $this->paid_at?->toIso8601String(),
            'cancelledAt' => $this->cancelled_at?->toIso8601String(),
            'receivedAt'  => $this->received_at?->toIso8601String(),
            'deliveredAt' => $this->delivered_at?->toIso8601String(),

            'payment' => $this->paymentSummary(),
        ];
    }

    /**
     * Enough for the storefront to know what to show next, and nothing that
     * would help someone else drive the payment.
     */
    private function paymentSummary(): ?array
    {
        $payment = $this->relationLoaded('payments')
            ? $this->payments->first()
            : $this->payments()->orderByDesc('id')->first();

        if (! $payment) {
            return null;
        }

        return [
            'status'       => $payment->status,
            'statusLabel'  => $payment->statusLabel(),
            'wallet'       => $payment->maskedWallet(),
            'attemptsLeft' => max(0, Payment::MAX_CONFIRM_ATTEMPTS - $payment->confirm_attempts),
            'message'      => $payment->isPaid() ? null : $payment->errorMessage(),
        ];
    }
}
