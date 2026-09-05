<?php

namespace App\Services\Storefront;

use App\Models\Address;
use App\Models\Customer;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Saved delivery addresses (handoff 10).
 *
 * The one invariant worth naming: a customer has at most one default address,
 * and at least one whenever they have any addresses at all. Every write goes
 * through here so that stays true — including the awkward case of deleting the
 * default, which has to hand the flag to a survivor.
 */
class AddressService
{
    /** @param array<string, mixed> $data */
    public function create(Customer $customer, array $data): Address
    {
        return DB::transaction(function () use ($customer, $data) {
            $attributes = $this->attributes($data);

            // handoff 10: the first address a customer saves becomes the
            // default on its own — they should not have to ask for that.
            $attributes['is_default'] = ! $customer->addresses()->exists();

            $address = $customer->addresses()->create($attributes);

            if ($attributes['is_default']) {
                $this->clearOthers($customer, $address);
            }

            return $address->load('zone');
        });
    }

    /**
     * A full replacement, not a patch (handoff 10). `is_default` is not part of
     * the body: moving the flag is what POST /addresses/{id}/default is for.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(Address $address, array $data): Address
    {
        $address->update($this->attributes($data));

        return $address->fresh(['zone']);
    }

    /**
     * Delete, and make sure the customer is not left with addresses but no
     * default — the checkout screen preselects one and would otherwise show
     * none at all.
     */
    public function delete(Address $address): void
    {
        DB::transaction(function () use ($address) {
            $customer  = $address->customer;
            $wasDefault = $address->is_default;

            $address->delete();

            if (! $wasDefault || ! $customer) {
                return;
            }

            $next = $customer->addresses()->orderBy('created_at')->first();

            $next?->update(['is_default' => true]);
        });
    }

    public function makeDefault(Address $address): Address
    {
        return DB::transaction(function () use ($address) {
            $address->update(['is_default' => true]);

            $this->clearOthers($address->customer, $address);

            return $address->fresh(['zone']);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        $type = $data['type'] ?? 'home';

        // A free-text label is required for `other` and optional otherwise,
        // where the type's own name is the sensible default (handoff 10).
        $label = trim((string) ($data['label'] ?? '')) ?: Address::defaultLabel($type);

        if ($label === null) {
            throw ValidationException::withMessages([
                'label' => 'أدخل اسماً للعنوان',
            ]);
        }

        $phone = PhoneNumber::normalize($data['phone'] ?? null);

        // Checked here as well as in the form request: this is the number a
        // driver rings, and the storefront is not the only caller.
        if ($phone === null) {
            throw ValidationException::withMessages([
                'phone' => 'رقم الهاتف يجب أن يكون بصيغة 05XXXXXXXX',
            ]);
        }

        return [
            'type'     => $type,
            'label'    => $label,
            'name'     => $data['name'],
            'phone'    => $phone,
            'city'     => trim((string) ($data['city'] ?? '')) ?: 'غزة',
            'zone_id'  => $data['zoneId'] ?? null,
            'street'   => $data['street'],
            'landmark' => $data['landmark'] ?? null,
            'lat'      => $data['location']['lat'] ?? null,
            'lng'      => $data['location']['lng'] ?? null,
        ];
    }

    private function clearOthers(?Customer $customer, Address $keep): void
    {
        $customer?->addresses()
            ->whereKeyNot($keep->getKey())
            ->update(['is_default' => false]);
    }
}
