<?php

namespace App\Http\Requests;

class StoreOrderRequest extends CartRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return array_merge($this->cartRules(), [
            'customer'       => ['required', 'array'],
            'customer.name'  => ['required', 'string', 'max:120'],
            'customer.phone' => ['required', 'string', 'max:30'],
            'customer.notes' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'customer.required'       => 'بيانات العميل مطلوبة',
            'customer.name.required'  => 'الاسم مطلوب',
            'customer.phone.required' => 'رقم الهاتف مطلوب',
        ]);
    }
}
