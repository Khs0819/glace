<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Shape checks for an incoming cart, shared by the quote and the order calls.
 *
 * This layer only says the payload is well formed. Whether a size belongs to
 * the chosen container, whether a flavour is offered, whether a mix has the
 * right number of picks — all of that needs the catalog and lives in
 * CartPricer, which raises the same 422 with the same field paths.
 */
abstract class CartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, array<int, string>> */
    protected function cartRules(): array
    {
        return [
            'items'             => ['required', 'array', 'min:1', 'max:50'],
            'items.*.productId' => ['required', 'string', 'max:64'],
            'items.*.quantity'  => ['required', 'integer', 'min:1', 'max:50'],
            'items.*.notes'     => ['nullable', 'string', 'max:500'],

            // flat-list: a single variant, or a mix built from several
            'items.*.itemId'       => ['nullable', 'string', 'max:100'],
            'items.*.mixId'        => ['nullable', 'string', 'max:100'],
            'items.*.mixItemIds'   => ['nullable', 'array', 'max:10'],
            'items.*.mixItemIds.*' => ['string', 'max:100'],

            // builder: type, size and the balls
            'items.*.containerId' => ['nullable', 'string', 'max:100'],
            'items.*.sizeId'      => ['nullable', 'string', 'max:100'],
            'items.*.flavorIds'   => ['nullable', 'array', 'max:20'],
            'items.*.flavorIds.*' => ['string', 'max:100'],

            // One entry per ordered unit, because a line of four milkshakes can
            // carry four different sets of extras.
            'items.*.units'                      => ['nullable', 'array', 'max:50'],
            'items.*.units.*.addons'             => ['nullable', 'array', 'max:20'],
            'items.*.units.*.addons.*.id'        => ['required', 'string', 'max:100'],
            'items.*.units.*.addons.*.quantity'  => ['nullable', 'integer', 'min:1', 'max:99'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'items.required'             => 'السلة فارغة',
            'items.min'                  => 'السلة فارغة',
            'items.max'                  => 'عدد الأصناف في السلة كبير جداً',
            'items.*.productId.required' => 'الصنف غير محدد',
            'items.*.quantity.required'  => 'الكمية مطلوبة',
            'items.*.quantity.min'       => 'الكمية يجب أن تكون 1 على الأقل',
            'items.*.quantity.max'       => 'الحد الأقصى للكمية هو 50',
        ];
    }
}
