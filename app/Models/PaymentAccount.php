<?php

namespace App\Models;

use App\Support\MediaUrl;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Where the shop is paid, per payment method (handoff 13).
 *
 * Two kinds of row live here now, and they are not the same thing:
 *
 *   A destination — bank, PalPay, manual Jawwal Pay. The customer transfers
 *   out-of-band to the number on the row and uploads proof, so `primary_value`
 *   is the whole point of the record.
 *
 *   A counter method — cash, card, automatic Jawwal Pay. Nothing is
 *   transferred anywhere: the money is taken at the till or by the gateway.
 *   The row exists so the shop can turn the method on and off and give it an
 *   order in the list, and `primary_value` is a label rather than an account.
 *
 * Anything the storefront tells a customer to pay INTO must come from the
 * first group; RECEIPT_METHODS on Order is what draws that line.
 */
class PaymentAccount extends Model
{
    /**
     * PalPay is a Palestinian payment company. It is NOT PayPal, and the two
     * were conflated here — which pointed customers at the wrong service
     * entirely.
     */
    public const METHODS = [
        'bop'           => 'بنك فلسطين',
        'jawwal-manual' => 'جوال باي (يدوي)',
        'jawwal'        => 'جوال باي (آلي)',
        'palpay'        => 'PalPay',
        'visa'          => 'فيزا (داخل المحل)',
        'cash'          => 'كاش (داخل المحل)',
    ];

    /**
     * Whether customers may pay with this method right now.
     *
     * The dashboard's «مفعّل» switch is the shop's way of taking a method off
     * the storefront. A method with no account row at all has never been
     * configured and stays available — that is how cash and card behaved
     * before there was anything to switch.
     */
    public static function methodEnabled(string $method): bool
    {
        return static::enabledMethods()[$method] ?? true;
    }

    /**
     * Every payment method the storefront can offer, and whether it is on.
     *
     * @return array<string, bool>
     */
    public static function enabledMethods(): array
    {
        $accounts = static::query()->pluck('active', 'method');

        $methods = [];

        foreach (Order::PAYMENT_METHODS as $method) {
            // The wallet is not an account the shop is paid into, so it has no
            // row and is always available to a signed-in customer.
            $methods[$method] = $accounts->has($method) ? (bool) $accounts[$method] : true;
        }

        return $methods;
    }

    /** Methods the customer transfers to; the rest are taken at the counter. */
    public function isTransferDestination(): bool
    {
        return in_array($this->method, Order::RECEIPT_METHODS, true);
    }

    protected $fillable = [
        'method', 'qr_image', 'holder_name', 'bank_name', 'account_number',
        'primary_label', 'primary_value', 'secondary_label', 'secondary_value',
        'sort_order', 'active',
    ];

    protected $casts = ['active' => 'boolean'];

    /**
     * Absolute, per the media contract in swagger.yaml — never a bare path.
     *
     * Null when the stored file is gone (a lost volume, a failed upload): a
     * customer scans this to send money, and a link to nothing is worse than
     * no code at all.
     */
    public function qrImageUrl(): ?string
    {
        return $this->qrImageStatus() === 'ok' ? MediaUrl::resolve($this->qr_image) : null;
    }

    /**
     * Where the QR image stands, for diagnosis.
     *
     * @return 'ok'|'none'|'missing-file'
     */
    public function qrImageStatus(): string
    {
        $path = (string) $this->qr_image;

        if ($path === '') {
            return 'none';
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return 'ok';
        }

        return Storage::disk('public')->exists($path) ? 'ok' : 'missing-file';
    }
}
