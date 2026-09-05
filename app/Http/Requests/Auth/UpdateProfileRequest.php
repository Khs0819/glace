<?php

namespace App\Http\Requests\Auth;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The profile form has exactly three fields (handoff 09). No avatar, no
 * password — the storefront draws initials, and passwords do not exist here.
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],

            // Optional in the strong sense: absent, null and "" are all fine.
            // It is only validated — and only checked for uniqueness — when the
            // customer actually sent an address.
            'email' => [
                'nullable',
                'email:rfc',
                'max:190',
                Rule::unique('customers', 'email')->ignore($this->user()?->getKey()),
            ],

            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'name.required' => 'الاسم مطلوب',
            'email.email'   => 'البريد الإلكتروني غير صالح',
            'email.unique'  => 'البريد الإلكتروني مستخدم مسبقاً',
        ];
    }
}
