<?php

namespace App\Support;

use App\Services\JawwalPay\MobileNumber;

/**
 * Palestinian mobile numbers in the national form the storefront uses.
 *
 * Two forms exist in this system and they are not interchangeable:
 *   `00970599002286` — what Jawwal Pay's Service Bus expects (MobileNumber)
 *   `0599002286`     — what the customer types, and what handoff 08/10 stores
 *
 * Validity is decided once, by MobileNumber, so a number the payment gateway
 * would reject with code 56 cannot be saved against an account or an address
 * in the first place. This class only changes the presentation.
 */
class PhoneNumber
{
    /**
     * Canonical 05XXXXXXXX, or null when the input is not a Palestinian mobile.
     *
     * Accepts everything customers actually type — +970, 00970, 970, 05, or the
     * bare subscriber digits — with any spacing or dashes in between.
     */
    public static function normalize(?string $number): ?string
    {
        $international = MobileNumber::normalize($number);

        if ($international === null) {
            return null;
        }

        // 00970 5XXXXXXXX → 0 5XXXXXXXX
        return '0' . substr($international, 5);
    }

    public static function valid(?string $number): bool
    {
        return self::normalize($number) !== null;
    }

    /** The form the payment gateway wants, from anything the customer typed. */
    public static function international(?string $number): ?string
    {
        return MobileNumber::normalize($number);
    }

    /** 0599002286 → 059•••2286, for anywhere a full number should not be printed. */
    public static function mask(?string $number): string
    {
        $national = self::normalize($number);

        if ($national === null) {
            return '••••';
        }

        return substr($national, 0, 3) . str_repeat('•', 3) . substr($national, -4);
    }
}
