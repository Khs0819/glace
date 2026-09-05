<?php

namespace App\Http\Requests;

class QuoteCartRequest extends CartRequest
{
    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return $this->cartRules();
    }
}
