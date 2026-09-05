<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The `AuthUser` shape (handoff 08), used identically by /auth/otp/verify,
 * /auth/me and /auth/profile — the storefront parses one interface everywhere.
 *
 * `email` is a string, never null: the frontend types it as `string` and an
 * account may genuinely have none, so an absent address is "".
 */
class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'    => $this->id,
            'name'  => $this->name,
            'email' => $this->email ?? '',
            'phone' => $this->phone,
        ];
    }
}
