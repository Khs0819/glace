<?php

namespace App\Services\JawwalPay;

/**
 * Wallet identifiers for the Service Bus.
 *
 * The merchant guide describes `receiver` as "05XXXXXXXX" but every worked
 * example — and the test wallet we were issued — is the full international
 * form 00970XXXXXXXXX. Customers type it every way in between, so everything
 * is normalised to the form the examples actually use before it leaves here;
 * a number the gateway would reject with code 56 is caught before the call.
 */
class MobileNumber
{
    /** Country codes serving Palestinian mobiles. */
    private const COUNTRY_CODES = ['970', '972'];

    private const DEFAULT_COUNTRY = '970';

    /**
     * Canonical 00CCXXXXXXXXX form, or null when the input is not a valid
     * Palestinian mobile number.
     */
    public static function normalize(?string $number): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $number) ?? '';

        if ($digits === '') {
            return null;
        }

        foreach (self::COUNTRY_CODES as $code) {
            // 00970XXXXXXXXX / 970XXXXXXXXX
            foreach (['00' . $code, $code] as $prefix) {
                if (str_starts_with($digits, $prefix)) {
                    return self::build($code, substr($digits, strlen($prefix)));
                }
            }
        }

        // 05XXXXXXXX (national) and 5XXXXXXXX (bare subscriber)
        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        return self::build(self::DEFAULT_COUNTRY, $digits);
    }

    public static function valid(?string $number): bool
    {
        return self::normalize($number) !== null;
    }

    /** 00970599002286 → 00970 5••••• 2286, for logs and the dashboard. */
    public static function mask(?string $number): string
    {
        $normalized = self::normalize($number) ?? preg_replace('/\D+/', '', (string) $number);

        if (strlen((string) $normalized) < 6) {
            return '••••';
        }

        return substr($normalized, 0, 6) . str_repeat('•', max(0, strlen($normalized) - 10)) . substr($normalized, -4);
    }

    /** Subscriber part must be the 9 digits of a mobile line, i.e. 5XXXXXXXX. */
    private static function build(string $country, string $subscriber): ?string
    {
        if (strlen($subscriber) !== 9 || ! str_starts_with($subscriber, '5')) {
            return null;
        }

        return '00' . $country . $subscriber;
    }
}
