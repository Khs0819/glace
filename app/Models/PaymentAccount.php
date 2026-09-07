<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\MediaUrl;

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

    /** Methods the customer transfers to; the rest are taken at the counter. */
    public function isTransferDestination(): bool
    {
        return in_array($this->method, Order::RECEIPT_METHODS, true);
    }

    protected $fillable = [
        'method', 'qr_image', 'holder_name', 'bank_name',
        'primary_label', 'primary_value', 'secondary_label', 'secondary_value',
        'sort_order', 'active',
    ];

    protected $casts = ['active' => 'boolean'];

    /** Absolute, per the media contract in swagger.yaml — never a bare path. */
    public function qrImageUrl(): ?string
    {
        return MediaUrl::resolve($this->qr_image);
    }
}
