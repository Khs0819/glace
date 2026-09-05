<?php

namespace App\Http\Requests\Storefront;

use App\Models\TopUpRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TopUpRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:10000'],
            'method' => ['required', Rule::in(TopUpRequest::METHODS)],

            // Type and size are checked properly in ReceiptStorage, which
            // sniffs the file rather than trusting its declared type. `file` is
            // only here so a non-upload is rejected before it gets that far.
            'receiptImage' => ['nullable', 'file'],

            // Either a receipt or a note — a transfer with no evidence at all
            // cannot be matched by the staff member reviewing it.
            'receiptNote' => ['nullable', 'string', 'max:1000', 'required_without:receiptImage'],

            // Only meaningful for the automatic Jawwal flow.
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'amount.required'            => 'المبلغ مطلوب',
            'amount.min'                 => 'أقل مبلغ للشحن هو 1 ₪',
            'method.required'            => 'طريقة الشحن مطلوبة',
            'method.in'                  => 'طريقة الشحن غير مدعومة',
            'receiptNote.required_without' => 'أرفق صورة الإيصال أو اكتب ملاحظة توضح التحويل',
        ];
    }
}
