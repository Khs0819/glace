<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class SendOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        // Shape only. Whether the digits form a real Palestinian mobile is
        // decided by PhoneNumber inside OtpService, so one definition of
        // "valid number" serves the whole system.
        return [
            'phone' => ['required', 'string', 'max:20'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.required' => 'رقم الهاتف مطلوب',
            'phone.max'      => 'رقم الهاتف غير صحيح',
        ];
    }
}
