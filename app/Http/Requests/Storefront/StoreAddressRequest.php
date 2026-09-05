<?php

namespace App\Http\Requests\Storefront;

use App\Models\Address;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Serves both create and update: handoff 10 specifies a full replacement on
 * PUT, so the two take the same body.
 */
class StoreAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'type'  => ['required', Rule::in(array_keys(Address::TYPES))],

            // Required only for `other`, which has no sensible default name.
            // AddressService fills the rest in from the type.
            'label' => ['nullable', 'string', 'max:60', Rule::requiredIf($this->input('type') === 'other')],

            'name'  => ['required', 'string', 'max:120'],

            // 05XXXXXXXX. Checked again in AddressService against the shared
            // normaliser, so "+970…" is accepted and stored in the one form.
            'phone' => ['required', 'string', 'max:20'],

            // Fixed to "غزة" by the storefront today, but stored as free text —
            // handoff 10 is explicit that this is not a city picker.
            'city'  => ['nullable', 'string', 'max:60'],

            // Soft reference: a zone the dashboard later retires should not make
            // the customer's saved address unsavable.
            'zoneId'   => ['nullable', 'string', 'max:60', Rule::exists('delivery_zones', 'id')],
            'street'   => ['required', 'string', 'max:190'],
            'landmark' => ['nullable', 'string', 'max:190'],

            // Optional GPS pin, bounded so a malformed one cannot be stored.
            'location'     => ['nullable', 'array'],
            'location.lat' => ['required_with:location', 'numeric', 'between:-90,90'],
            'location.lng' => ['required_with:location', 'numeric', 'between:-180,180'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'type.required'   => 'نوع العنوان مطلوب',
            'type.in'         => 'نوع العنوان غير صحيح',
            'label.required'  => 'أدخل اسماً للعنوان',
            'name.required'   => 'الاسم مطلوب',
            'phone.required'  => 'رقم الهاتف مطلوب',
            'street.required' => 'الشارع مطلوب',
            'zoneId.exists'   => 'منطقة التوصيل غير موجودة',
        ];
    }
}
