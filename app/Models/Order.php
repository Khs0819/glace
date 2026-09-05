<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\MediaUrl;
use Illuminate\Support\Str;

/**
 * An order, tracked on two independent axes.
 *
 *  `status`         — fulfilment: قيد المراجعة → جاري التحضير → … (handoff 12)
 *  `payment_status` — money: pending → awaiting_payment → paid | failed
 *
 * They are genuinely separate questions. A cash order is unpaid for its whole
 * life and still travels the full fulfilment path; a card order is paid up
 * front and still has to be prepared. Folding them into one column makes one
 * of those two cases unrepresentable.
 */
class Order extends Model
{
    use HasUuids;

    // ─── payment axis ───────────────────────────────────────────────────────

    /** Placed, nothing charged yet. */
    public const STATUS_PENDING = 'pending';

    /** An OTP is out with the customer. */
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';

    public const STATUS_PAID      = 'paid';
    public const STATUS_FAILED    = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_PENDING          => 'بانتظار الدفع',
        self::STATUS_AWAITING_PAYMENT => 'رمز التحقق مُرسل',
        self::STATUS_PAID             => 'مدفوع',
        self::STATUS_FAILED           => 'فشل الدفع',
        self::STATUS_CANCELLED        => 'ملغي',
    ];

    // ─── fulfilment axis ────────────────────────────────────────────────────
    //
    // The storefront renders these strings verbatim; they are the API values,
    // not labels for something else (handoff 12 §5).

    public const FULFILMENT_REVIEW    = 'قيد المراجعة';
    public const FULFILMENT_PREPARING = 'جاري التحضير';
    public const FULFILMENT_READY     = 'جاهز للاستلام';
    public const FULFILMENT_ON_WAY    = 'في الطريق';
    public const FULFILMENT_DELIVERED = 'تم التسليم';
    public const FULFILMENT_RECEIVED  = 'تم الاستلام';
    public const FULFILMENT_CANCELLED = 'ملغي';
    public const FULFILMENT_REFUNDED  = 'مسترد';

    /** Nothing more will happen to the order on its own. */
    public const FINAL_STATUSES = [
        self::FULFILMENT_DELIVERED,
        self::FULFILMENT_RECEIVED,
        self::FULFILMENT_CANCELLED,
        self::FULFILMENT_REFUNDED,
    ];

    /**
     * The ladder each delivery method actually climbs. The dashboard offers
     * only these, so a pickup order can never be pushed to "في الطريق" and
     * leave the storefront's tracker with a step it cannot draw.
     */
    public const FULFILMENT_FLOWS = [
        'dine-in'  => [self::FULFILMENT_REVIEW, self::FULFILMENT_DELIVERED],
        'pickup'   => [self::FULFILMENT_REVIEW, self::FULFILMENT_PREPARING, self::FULFILMENT_READY, self::FULFILMENT_DELIVERED],
        'delivery' => [self::FULFILMENT_REVIEW, self::FULFILMENT_PREPARING, self::FULFILMENT_ON_WAY, self::FULFILMENT_RECEIVED],
    ];

    public const DELIVERY_METHODS = ['delivery', 'pickup', 'dine-in'];

    public const PAYMENT_METHODS = ['jawwal', 'jawwal-manual', 'paypal', 'cash', 'visa', 'wallet', 'bop'];

    /** Paid out-of-band; the customer uploads proof instead (handoff 13). */
    public const RECEIPT_METHODS = ['jawwal-manual', 'paypal', 'bop'];

    /** Taken at the counter, so they cannot be the payment for a delivery. */
    public const IN_STORE_METHODS = ['cash', 'visa'];

    protected $fillable = [
        'customer_id', 'reference', 'public_token', 'customer_name', 'customer_phone', 'notes',
        'status', 'payment_status', 'payment_method', 'delivery_method',
        'address', 'address_id', 'subtotal', 'coupon_code', 'discount', 'delivery_fee',
        'total', 'currency', 'receipt_image', 'receipt_note',
        'preparation_time', 'estimated_delivery_time', 'driver', 'driver_assigned_at',
        'scheduled_for', 'cancel_reason', 'cancelled_at', 'received_at', 'delivered_at', 'paid_at',
        'table_number', 'printed_at', 'print_count', 'print_error',
        'paid_by', 'shift_id', 'refunded_amount', 'refunded_at',
    ];

    protected $casts = [
        'subtotal'           => 'float',
        'discount'           => 'float',
        'delivery_fee'       => 'float',
        'total'              => 'float',
        'address'            => 'array',
        'driver'             => 'array',
        'paid_at'            => 'datetime',
        'driver_assigned_at' => 'datetime',
        'scheduled_for'      => 'datetime',
        'cancelled_at'       => 'datetime',
        'received_at'        => 'datetime',
        'delivered_at'       => 'datetime',
        'printed_at'         => 'datetime',
        'refunded_at'        => 'datetime',
        'refunded_amount'    => 'float',
        'print_count'        => 'integer',
    ];

    /**
     * Set here rather than as a column default so it holds on every connection
     * and every driver — the fulfilment vocabulary changed after the table was
     * created, and a raw default would still hand out the old one.
     */
    protected $attributes = [
        'status'         => self::FULFILMENT_REVIEW,
        'payment_status' => self::STATUS_PENDING,
        'discount'       => 0,
        'delivery_fee'   => 0,
    ];

    /** The token never belongs in a payload that was not the creation response. */
    protected $hidden = ['public_token'];

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class)->orderBy('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Who took the money at the counter, when it was taken by hand. */
    public function paidBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paid_by');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashierShift::class, 'shift_id');
    }

    /** Eaten in, so it belongs to a table rather than to a driver or a bag. */
    public function isDineIn(): bool
    {
        return $this->delivery_method === 'dine-in';
    }

    public function printed(): bool
    {
        return $this->printed_at !== null;
    }

    /**
     * Paid at the counter in person, so the cashier is the one who decides it
     * has been settled — no gateway will ever say so on their behalf.
     */
    public function collectedByHand(): bool
    {
        return in_array($this->payment_method, self::IN_STORE_METHODS, true);
    }

    /** What the shop actually kept: the total less anything given back. */
    public function netTotal(): float
    {
        return round($this->total - $this->refunded_amount, 2);
    }

    /** The payment axis. Kept as the plain name because the gateway code owns it. */
    public function statusLabel(): string
    {
        return self::STATUSES[$this->payment_status] ?? $this->payment_status;
    }

    public function isPaid(): bool
    {
        return $this->payment_status === self::STATUS_PAID;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    public function requiresReceipt(): bool
    {
        return in_array($this->payment_method, self::RECEIPT_METHODS, true);
    }

    /** Absolute URL, per the media contract — never the stored relative path. */
    public function receiptImageUrl(): ?string
    {
        return MediaUrl::resolve($this->receipt_image);
    }

    /** @return array<int, string> The steps this order's tracker will draw. */
    public function fulfilmentFlow(): array
    {
        return self::FULFILMENT_FLOWS[$this->delivery_method] ?? self::FULFILMENT_FLOWS['pickup'];
    }

    /**
     * Where the dashboard may move it next: forward along its own ladder, plus
     * the two exits available from any live status.
     *
     * @return array<int, string>
     */
    public function allowedNextStatuses(): array
    {
        if ($this->isFinal()) {
            return [];
        }

        $flow  = $this->fulfilmentFlow();
        $index = array_search($this->status, $flow, true);

        $forward = $index === false ? $flow : array_slice($flow, $index + 1);

        return array_values(array_merge($forward, [self::FULFILMENT_CANCELLED, self::FULFILMENT_REFUNDED]));
    }

    /**
     * Guest checkout has no session, so this token is the whole authorisation
     * story. Compared in constant time — a leak here is a leak of the order.
     */
    public function tokenMatches(mixed $token): bool
    {
        return is_string($token) && $token !== '' && hash_equals($this->public_token, $token);
    }

    /** Only an unpaid, un-cancelled order can be charged. */
    public function payable(): bool
    {
        return in_array($this->payment_status, [self::STATUS_PENDING, self::STATUS_AWAITING_PAYMENT, self::STATUS_FAILED], true);
    }

    /**
     * Short handle a customer can read down the phone. Ambiguous glyphs are
     * left out so "0/O" and "1/I" never have to be disambiguated out loud.
     */
    public static function newReference(): string
    {
        $alphabet = '23456789ABCDEFGHJKMNPQRSTWXYZ';

        do {
            $suffix = '';

            for ($i = 0; $i < 6; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }

            // "ORD-M3K2A1" — the id the storefront shows and routes on.
            $reference = 'ORD-' . $suffix;
        } while (static::where('reference', $reference)->exists());

        return $reference;
    }

    public static function newPublicToken(): string
    {
        return Str::random(64);
    }
}
