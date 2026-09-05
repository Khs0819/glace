<?php

namespace App\Http\Requests\Storefront;

use App\Models\Order;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The checkout request (handoff 12 §4).
 *
 * It arrives as `multipart/form-data`, not JSON, because `receiptImage` is a
 * real file. That has one awkward consequence: `items` cannot be a nested array
 * in a multipart body, so the storefront sends it as a **JSON string** in a
 * single field. It is decoded in prepareForValidation() before any rule sees it.
 *
 * The price fields the storefront also sends — `unitPrice`, `addonTotal`,
 * `subtotal`, `total` — are deliberately absent from these rules. They are not
 * validated because they are not used: StorefrontOrderService re-prices the
 * whole cart from the catalog.
 */
class StoreStorefrontOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $items = $this->input('items');

        // JSON.stringify'd by the frontend to survive multipart. A body that is
        // genuinely JSON (a test, or a future client) already has the array and
        // is left alone.
        if (is_string($items)) {
            $decoded = json_decode($items, true);

            $this->merge(['items' => is_array($decoded) ? $decoded : []]);
        }
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'items'             => ['required', 'array', 'min:1', 'max:50'],
            'items.*.productId' => ['required', 'string', 'max:64'],
            'items.*.quantity'  => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.notes'     => ['nullable', 'string', 'max:500'],

            // Selections are shape-checked only. Whether an id exists, is
            // available and belongs to this product is decided by CartPricer
            // against the live catalog.
            'items.*.selections'       => ['nullable', 'array', 'max:40'],
            'items.*.selections.*.id'  => ['required', 'string', 'max:100'],
            'items.*.selections.*.kind' => ['nullable', 'string', 'max:30'],
            'items.*.selections.*.qty' => ['nullable', 'integer', 'min:1', 'max:50'],

            'items.*.flatSelections'      => ['nullable', 'array', 'max:40'],
            'items.*.flatSelections.*.id' => ['required', 'string', 'max:100'],
            'items.*.flatSelections.*.qty' => ['nullable', 'integer', 'min:1', 'max:50'],

            // Display labels the pricer falls back to when no id was sent.
            'items.*.type'      => ['nullable', 'string', 'max:100'],
            'items.*.container' => ['nullable', 'string', 'max:100'],

            'paymentMethod'  => ['required', Rule::in(Order::PAYMENT_METHODS)],
            'deliveryMethod' => ['required', Rule::in(Order::DELIVERY_METHODS)],

            // Required for delivery, which StorefrontOrderService enforces —
            // it also has to check the address belongs to this customer.
            'addressId'  => ['nullable', 'string', 'max:40'],

            // Only for a guest collecting in person, who has neither an account
            // nor a saved address to take a name and number from. A signed-in
            // customer's own details always win over anything sent here.
            'customer'       => ['nullable', 'array'],
            'customer.name'  => ['nullable', 'string', 'max:120'],
            'customer.phone' => ['nullable', 'string', 'max:20'],

            // Dine-in only, and optional even then — the cashier can set it
            // from the dashboard when the order arrives.
            'tableNumber' => ['nullable', 'string', 'max:20'],

            'couponCode' => ['nullable', 'string', 'max:40'],

            'pickupTime' => ['nullable', 'date'],
            'notes'      => ['nullable', 'string', 'max:1000'],

            // Sniffed and size-capped in ReceiptStorage, which does not trust
            // the declared content type.
            'receiptImage' => ['nullable', 'file'],
            'receiptNote'  => ['nullable', 'string', 'max:1000'],

            // Automatic Jawwal Pay only.
            'jawwalPhone' => ['nullable', 'string', 'max:20'],
            'jawwalCode'  => ['nullable', 'string', 'regex:/^\d{6}$/'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required'                => 'السلة فارغة',
            'items.min'                     => 'السلة فارغة',
            'items.max'                     => 'عدد الأصناف في السلة كبير جداً',
            'items.*.productId.required'    => 'الصنف غير محدد',
            'items.*.quantity.required'     => 'الكمية مطلوبة',
            'items.*.quantity.min'          => 'الكمية يجب أن تكون 1 على الأقل',
            'items.*.quantity.max'          => 'الحد الأقصى للكمية هو 50',
            'paymentMethod.required'        => 'اختر طريقة الدفع',
            'paymentMethod.in'              => 'طريقة الدفع غير مدعومة',
            'deliveryMethod.required'       => 'اختر طريقة الاستلام',
            'deliveryMethod.in'             => 'طريقة الاستلام غير مدعومة',
            'jawwalCode.regex'              => 'رمز التأكيد غير صحيح',
        ];
    }
}
