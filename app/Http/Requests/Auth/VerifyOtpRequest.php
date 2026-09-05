<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'max:20'],

            // Exactly six digits. Not an integer: "012345" must survive the
            // trip, and an integer cast would turn it into 12345.
            'code'  => ['required', 'string', 'regex:/^\d{6}$/'],

            // Required in practice only for a number with no account yet, which
            // is a question the database answers — not this layer.
            'fullName' => ['nullable', 'string', 'max:120'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'phone.required' => 'رقم الهاتف مطلوب',
            'code.required'  => 'رمز التحقق مطلوب',
            'code.regex'     => 'رمز التحقق غير صحيح',
        ];
    }
}
