<?php

namespace App\Services\Storefront;

use App\Jobs\PrintOrderReceipt;
use App\Models\Address;
use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OtpCode;
use App\Services\Auth\OtpService;
use App\Services\Checkout\CartItemNormalizer;
use App\Services\Checkout\CartPricer;
use App\Services\Checkout\Money;
use App\Services\Checkout\PricedCart;
use App\Support\PhoneNumber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Places a storefront order (handoff 12).
 *
 * The rule the whole class exists to enforce: **the server prices everything**.
 * `unitPrice`, `addonTotal`, `subtotal`, `discount` and `total` all arrive on
 * the request and are all discarded. Only `productId`, the selection ids,
 * `quantity` and `couponCode` are read — the rest is derived from the catalog
 * as it stands right now. Handoff 12 calls this the most important security
 * item in the file, and it is.
 *
 * Order of operations matters and is deliberate:
 *   price → check deliverability → snapshot the address → apply the coupon →
 *   add the delivery fee → take payment → write the order
 *
 * Payment comes last so that nothing irreversible has happened if the write
 * fails; and the payment and the write share one transaction so a wallet debit
 * cannot survive an order that was never created.
 */
class StorefrontOrderService
{
    public function __construct(
        private readonly CartPricer $pricer,
        private readonly CouponService $coupons,
        private readonly DeliveryRestrictions $restrictions,
        private readonly WalletService $wallet,
        private readonly ReceiptStorage $receipts,
        private readonly OtpService $otp,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  validated by StoreStorefrontOrderRequest
     *
     * @throws ValidationException
     */
    public function place(array $payload, ?Customer $customer, ?UploadedFile $receipt = null): Order
    {
        $deliveryMethod = $payload['deliveryMethod'];
        $paymentMethod  = $payload['paymentMethod'];

        // 1 ── price the cart from the catalog, ignoring every number sent.
        $cart = $this->pricer->price(CartItemNormalizer::normalize($payload['items']));

        if ($cart->subtotalAgorot() <= 0) {
            throw ValidationException::withMessages(['items' => 'لا يمكن إنشاء طلب بمبلغ صفر']);
        }

        // 2 ── refuse a delivery the shop cannot make.
        $this->restrictions->assertDeliverable($cart, $deliveryMethod);

        $this->assertMethodsAgree($paymentMethod, $deliveryMethod);

        // 3 ── freeze the address, including the zone's name as it reads today.
        $address = $this->resolveAddress($payload, $customer, $deliveryMethod);

        $subtotal = $cart->subtotalAgorot();

        // 4 ── the coupon, re-evaluated here rather than believed.
        [$coupon, $discount] = $this->resolveCoupon($payload['couponCode'] ?? null, $subtotal, $customer);

        // 5 ── delivery fee, from the zone the address actually points at.
        $deliveryFee = $deliveryMethod === 'delivery' ? $this->deliveryFee($address) : 0;

        $total = max(0, $subtotal - $discount) + $deliveryFee;

        // 6 ── the receipt, if this method needs one.
        $receiptPath = $this->resolveReceipt($paymentMethod, $payload, $receipt);

        // 7 ── prove the Jawwal code before anything is written. A bad code must
        //      leave no order behind (handoff 12).
        if ($paymentMethod === 'jawwal') {
            $this->verifyJawwalCode($payload, $total);
        }

        return DB::transaction(function () use (
            $payload, $customer, $cart, $address, $coupon, $discount,
            $deliveryFee, $subtotal, $total, $paymentMethod, $deliveryMethod, $receiptPath
        ) {
            $order = Order::create([
                'customer_id'     => $customer?->getKey(),
                'reference'       => Order::newReference(),
                'public_token'    => Order::newPublicToken(),
                'customer_name'   => $address['name'] ?? $customer?->name ?? 'زبون',
                'customer_phone'  => $address['phone'] ?? $customer?->phone ?? '',
                'notes'           => $payload['notes'] ?? null,

                // Always "قيد المراجعة", whatever was paid and however
                // (handoff 12 §4).
                'status'          => Order::FULFILMENT_REVIEW,
                'payment_status'  => Order::STATUS_PENDING,

                'payment_method'  => $paymentMethod,
                'delivery_method' => $deliveryMethod,

                // Dine-in only. The storefront contract has no table field yet,
                // so this is normally set by the cashier when the docket lands
                // — but it is accepted here ready for the day the customer
                // picks their own table in the app.
                'table_number'    => $deliveryMethod === 'dine-in'
                    ? ($payload['tableNumber'] ?? null)
                    : null,
                'address'         => $address,
                'address_id'      => $payload['addressId'] ?? null,

                'subtotal'     => Money::toDecimal($subtotal),
                'coupon_code'  => $coupon?->code,
                'discount'     => Money::toDecimal($discount),
                'delivery_fee' => Money::toDecimal($deliveryFee),
                'total'        => Money::toDecimal($total),
                'currency'     => 'ILS',

                'receipt_image' => $receiptPath,
                'receipt_note'  => $payload['receiptNote'] ?? null,

                'scheduled_for' => $payload['pickupTime'] ?? null,

                // Defaults so the tracker has something to show; the shop sets
                // the real figures from the dashboard.
                'preparation_time' => config('storefront.preparation_time'),
                'estimated_delivery_time' => $deliveryMethod === 'delivery'
                    ? config('storefront.estimated_delivery_time')
                    : null,
            ]);

            foreach ($cart->lines as $line) {
                $order->items()->create($line->toOrderItemAttributes());
            }

            if ($coupon) {
                $this->coupons->redeem($coupon);
            }

            // Inside the transaction on purpose: a debit that succeeded against
            // an order that then failed to write would be money taken for
            // nothing.
            if ($paymentMethod === 'wallet') {
                $this->payFromWallet($order, $customer, $total);
            }

            // After the commit, never inside it: a queued job that ran before
            // the transaction landed would look for an order that is not there
            // yet, and a printer error must never roll back a paid order.
            DB::afterCommit(fn () => PrintOrderReceipt::dispatch($order->getKey()));

            return $order->load('items');
        });
    }

    /**
     * Start the automatic Jawwal flow — text a code for this amount
     * (handoff 12 §4.1).
     *
     * No order exists yet at this point; the code is proved when POST /orders
     * arrives immediately afterwards.
     */
    public function sendJawwalCode(string $phone, float $amount): void
    {
        $this->otp->send($phone, OtpCode::PURPOSE_JAWWAL, [
            'amount' => Money::toAgorot($amount),
        ]);
    }

    // ─── pieces ─────────────────────────────────────────────────────────────

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function resolveAddress(array $payload, ?Customer $customer, string $deliveryMethod): array
    {
        if ($deliveryMethod !== 'delivery') {
            // Picking up or eating in: there is no address, but the shop still
            // needs a name and a number to call when the order is ready.
            $name  = $customer?->name ?? trim((string) ($payload['customer']['name'] ?? ''));
            $phone = $customer?->phone ?? PhoneNumber::normalize($payload['customer']['phone'] ?? null);

            // A guest ordering a collection has neither an account nor an
            // address to take these from. Refusing is better than writing an
            // order nobody can be contacted about.
            if ($name === '' || $phone === null) {
                throw ValidationException::withMessages([
                    'customer' => 'سجّل الدخول أو أدخل اسمك ورقم هاتفك لإتمام الطلب',
                ]);
            }

            return ['name' => $name, 'phone' => $phone];
        }

        if (blank($payload['addressId'] ?? null)) {
            throw ValidationException::withMessages(['addressId' => 'اختر عنوان التوصيل']);
        }

        if (! $customer) {
            throw ValidationException::withMessages([
                'addressId' => 'يجب تسجيل الدخول لاستخدام عنوان محفوظ',
            ]);
        }

        /** @var Address|null $address */
        $address = $customer->addresses()->with('zone')->find($payload['addressId']);

        if (! $address) {
            throw ValidationException::withMessages(['addressId' => 'العنوان غير موجود']);
        }

        // A snapshot, not a reference: the customer may edit or delete this
        // address tomorrow and the order must still read correctly.
        return $address->toOrderSnapshot();
    }

    /**
     * @return array{0: ?Coupon, 1: int}
     */
    private function resolveCoupon(?string $code, int $subtotal, ?Customer $customer): array
    {
        if (blank($code)) {
            return [null, 0];
        }

        $result = $this->coupons->evaluate($code, $subtotal, $customer);

        // A code that has expired between the cart screen and here is a 422,
        // not a silently ignored discount — the customer is about to be charged
        // more than the screen said.
        if (! $result['valid']) {
            throw ValidationException::withMessages(['couponCode' => $result['message']]);
        }

        return [$result['coupon'], $result['discount']];
    }

    /** @param array<string, mixed> $address */
    private function deliveryFee(array $address): int
    {
        $zoneId = $address['zoneId'] ?? null;

        if ($zoneId === null) {
            return 0;
        }

        $zone = \App\Models\DeliveryZone::find($zoneId);

        return $zone === null ? 0 : Money::toAgorot($zone->fee);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function resolveReceipt(string $paymentMethod, array $payload, ?UploadedFile $receipt): ?string
    {
        if (! in_array($paymentMethod, Order::RECEIPT_METHODS, true)) {
            return null;
        }

        $path = $this->receipts->store($receipt, 'receipts');

        // handoff 12: a free-text note is an accepted substitute for the image
        // — the customer may simply have failed to upload it.
        if ($path === null && blank($payload['receiptNote'] ?? null)) {
            throw ValidationException::withMessages([
                'receiptImage' => 'أرفق صورة إيصال التحويل أو اكتب ملاحظة توضح التحويل',
            ]);
        }

        return $path;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function verifyJawwalCode(array $payload, int $total): void
    {
        if (blank($payload['jawwalPhone'] ?? null) || blank($payload['jawwalCode'] ?? null)) {
            throw ValidationException::withMessages([
                'jawwalCode' => 'أدخل رمز التأكيد المرسل إلى رقمك',
            ]);
        }

        $record = $this->otp->verify(
            $payload['jawwalPhone'],
            $payload['jawwalCode'],
            OtpCode::PURPOSE_JAWWAL,
        );

        // The code was issued against an amount. If the cart has changed since,
        // the customer approved a different number than they are about to be
        // charged, and has to approve the new one.
        $approved = (int) ($record->payload['amount'] ?? 0);

        if ($approved !== $total) {
            throw ValidationException::withMessages([
                'jawwalCode' => 'تغيّرت قيمة الطلب — يرجى طلب رمز تأكيد جديد',
            ]);
        }
    }

    private function payFromWallet(Order $order, ?Customer $customer, int $total): void
    {
        if (! $customer) {
            throw ValidationException::withMessages([
                'paymentMethod' => 'يجب تسجيل الدخول للدفع من المحفظة',
            ]);
        }

        try {
            $this->wallet->debit($customer, $total, 'دفع طلب #' . $order->reference, $order);
        } catch (RuntimeException $e) {
            // Rolls the whole transaction back, so no order is left behind.
            throw ValidationException::withMessages([
                'paymentMethod' => $e->getMessage(),
            ])->status(409);
        }

        // Wallet credit is real money already held, so this one is settled the
        // moment it is taken.
        $order->update([
            'payment_status' => Order::STATUS_PAID,
            'paid_at'        => now(),
        ]);
    }

    private function assertMethodsAgree(string $paymentMethod, string $deliveryMethod): void
    {
        // Cash and card are taken at the counter (handoff 13), so they cannot
        // be the payment for something being driven to a house.
        if ($deliveryMethod === 'delivery' && in_array($paymentMethod, Order::IN_STORE_METHODS, true)) {
            throw ValidationException::withMessages([
                'paymentMethod' => 'الدفع نقداً أو بالبطاقة متاح داخل المحل فقط',
            ]);
        }
    }
}
