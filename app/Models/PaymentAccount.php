<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Support\MediaUrl;

/**
 * Where the shop is paid for the manual-transfer methods (handoff 13).
 */
class PaymentAccount extends Model
{
    /** Only the methods where the customer pays out-of-band and uploads proof. */
    public const METHODS = [
        'bop'           => 'بنك فلسطين',
        'jawwal-manual' => 'جوال باي (يدوي)',
        'paypal'        => 'PayPal',
    ];

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
