<?php

namespace App\Services\Auth;

use App\Models\OtpCode;
use App\Services\Sms\SmsSender;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Issues and checks the one-time codes the whole login rests on (handoff 08).
 *
 * Four things keep six digits from being a short walk:
 *   · the code is hashed, never stored in the clear
 *   · it expires in minutes
 *   · it is single-use — verifying consumes it
 *   · wrong guesses are counted, and the code burns before the space is walked
 *
 * The resend cooldown is enforced here, against the database, rather than by a
 * route throttle alone: a throttle is per IP and this has to be per phone
 * number, or one client can burn another customer's SMS budget.
 */
class OtpService
{
    /** How long a code stays good for. */
    public const TTL_MINUTES = 5;

    /** Minimum gap between two codes to the same number, in seconds. */
    public const RESEND_COOLDOWN = 60;

    /** Codes per number per hour, whatever the cooldown says. */
    public const HOURLY_LIMIT = 5;

    public function __construct(private readonly SmsSender $sms) {}

    /**
     * Text a fresh code to this number.
     *
     * @param  array<string, mixed>  $payload  carried through to verification
     *
     * @throws ValidationException on an invalid number or a too-soon resend
     */
    public function send(string $phone, string $purpose = OtpCode::PURPOSE_LOGIN, array $payload = []): OtpCode
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'phone' => 'رقم الهاتف غير صحيح',
            ]);
        }

        $this->assertNotTooSoon($normalized, $purpose);

        // Only one code may be live per number and purpose, otherwise an older
        // code stays valid alongside the one the customer is actually reading.
        OtpCode::where('phone', $normalized)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->update(['consumed_at' => now()]);

        $code = OtpCode::newCode();

        $record = OtpCode::create([
            'phone'      => $normalized,
            'purpose'    => $purpose,
            'code_hash'  => Hash::make($code),
            'expires_at' => now()->addMinutes(self::TTL_MINUTES),
            'payload'    => $payload === [] ? null : $payload,
        ]);

        $this->sms->send($normalized, $this->message($code));

        return $record;
    }

    /**
     * Check a code and consume it.
     *
     * Returns the record so a caller can read its `payload` — the Jawwal Pay
     * flow needs the amount the code was issued against.
     *
     * @throws ValidationException on a wrong, expired or already-used code
     */
    public function verify(string $phone, string $code, string $purpose = OtpCode::PURPOSE_LOGIN): OtpCode
    {
        $normalized = PhoneNumber::normalize($phone);

        if ($normalized === null) {
            throw ValidationException::withMessages(['phone' => 'رقم الهاتف غير صحيح']);
        }

        $record = OtpCode::where('phone', $normalized)
            ->where('purpose', $purpose)
            ->whereNull('consumed_at')
            ->latest('id')
            ->first();

        // One message for every failure mode. "No code was requested", "it
        // expired" and "wrong digits" are all answered identically, so the
        // response cannot be used to learn which numbers have accounts.
        if (! $record || ! $record->usable()) {
            throw ValidationException::withMessages(['code' => 'رمز التحقق غير صحيح']);
        }

        if (! $record->matches($code)) {
            $record->registerFailedAttempt();

            throw ValidationException::withMessages(['code' => 'رمز التحقق غير صحيح']);
        }

        $record->consume();

        return $record;
    }

    /**
     * @throws ValidationException
     */
    private function assertNotTooSoon(string $phone, string $purpose): void
    {
        $last = OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->latest('id')
            ->first();

        if ($last && $last->created_at->diffInSeconds(now()) < self::RESEND_COOLDOWN) {
            $this->tooSoon();
        }

        $recent = OtpCode::where('phone', $phone)
            ->where('purpose', $purpose)
            ->where('created_at', '>=', now()->subHour())
            ->count();

        if ($recent >= self::HOURLY_LIMIT) {
            $this->tooSoon();
        }
    }

    /**
     * 429, not 422: the request was well formed, it simply came too fast. The
     * storefront reads the status code to decide whether to keep the resend
     * button disabled.
     */
    private function tooSoon(): never
    {
        throw ValidationException::withMessages([
            'phone' => 'الرجاء الانتظار قبل إعادة الإرسال',
        ])->status(429);
    }

    private function message(string $code): string
    {
        return "رمز التحقق الخاص بك في جلاسيه الأمير: {$code}\nصالح لمدة " . self::TTL_MINUTES . ' دقائق.';
    }
}
