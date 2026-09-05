<?php

namespace App\Models;

use App\Services\JawwalPay\ErrorCode;
use App\Services\JawwalPay\MobileNumber;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** Row created, nothing sent yet. */
    public const STATUS_INITIATED = 'initiated';

    /** send_otp accepted — the customer has a code in hand. */
    public const STATUS_OTP_SENT = 'otp_sent';

    public const STATUS_PAID = 'paid';

    /** The gateway answered, and the answer was no. */
    public const STATUS_FAILED = 'failed';

    /**
     * We asked for the charge and never heard back. The money may or may not
     * have moved — this is the one status that needs a human, and it is why
     * the order reference travels with every charge as externalReference.
     */
    public const STATUS_UNRESOLVED = 'unresolved';

    /** Superseded by a newer code the customer asked for. */
    public const STATUS_EXPIRED = 'expired';

    public const STATUSES = [
        self::STATUS_INITIATED  => 'قيد البدء',
        self::STATUS_OTP_SENT   => 'رمز التحقق مُرسل',
        self::STATUS_PAID       => 'مدفوع',
        self::STATUS_FAILED     => 'فشل',
        self::STATUS_UNRESOLVED => 'غير مؤكد — يحتاج مراجعة',
        self::STATUS_EXPIRED    => 'منتهي',
    ];

    /** How many codes one order may burn before we stop asking the gateway. */
    public const MAX_OTP_REQUESTS = 5;

    /** How many wrong codes before this attempt is closed off. */
    public const MAX_CONFIRM_ATTEMPTS = 3;

    /** Seconds a customer must wait before asking for another code. */
    public const OTP_COOLDOWN_SECONDS = 60;

    protected $fillable = [
        'order_id', 'provider', 'method', 'otp_msg_id', 'charge_msg_id',
        'wallet', 'amount', 'status', 'error_code', 'error_description',
        'provider_reference', 'confirm_attempts', 'otp_sent_at', 'confirmed_at',
        'last_response',
    ];

    protected $casts = [
        'amount'           => 'float',
        'confirm_attempts' => 'integer',
        'otp_sent_at'      => 'datetime',
        'confirmed_at'     => 'datetime',
        'last_response'    => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    public function isOtpSent(): bool
    {
        return $this->status === self::STATUS_OTP_SENT;
    }

    /** Still holding a code the customer can enter. */
    public function awaitingConfirmation(): bool
    {
        return $this->status === self::STATUS_OTP_SENT
            && $this->confirm_attempts < self::MAX_CONFIRM_ATTEMPTS;
    }

    public function needsReview(): bool
    {
        return $this->status === self::STATUS_UNRESOLVED;
    }

    /** Full wallet number is never needed on screen. */
    public function maskedWallet(): string
    {
        return MobileNumber::mask($this->wallet);
    }

    /** What the gateway said, in Arabic, for the dashboard. */
    public function errorMessage(): ?string
    {
        return $this->hasError() ? ErrorCode::message($this->error_code) : null;
    }

    public function errorLabel(): ?string
    {
        return $this->hasError() ? ErrorCode::label($this->error_code) : null;
    }

    private function hasError(): bool
    {
        return $this->error_code !== null && $this->error_code !== ErrorCode::SUCCESS;
    }
}
