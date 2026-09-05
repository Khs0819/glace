<?php

namespace App\Services\Checkout;

use App\Exceptions\PaymentNotAllowedException;
use App\Models\Order;
use App\Models\Payment;
use App\Services\JawwalPay\JawwalPayClient;
use App\Services\JawwalPay\JawwalPayException;
use App\Services\JawwalPay\JawwalPayResponse;
use App\Services\JawwalPay\MobileNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Drives one order through Jawwal Pay's pay-to-merchant flow: send the customer
 * a code, then charge their wallet with it.
 *
 * The amount is always the order's own total — this class never accepts one.
 */
class OrderPaymentService
{
    public function __construct(private readonly JawwalPayClient $gateway) {}

    /** The code the customer is currently holding, if any. */
    public function activePayment(Order $order): ?Payment
    {
        return $order->payments()
            ->whereIn('status', [Payment::STATUS_OTP_SENT, Payment::STATUS_PAID, Payment::STATUS_UNRESOLVED])
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Step 1 — text the customer a one-time code for this order's total.
     *
     * @throws JawwalPayException when the gateway could not be reached at all
     */
    public function requestOtp(Order $order, string $wallet): Payment
    {
        $this->assertPayable($order);

        $normalized = MobileNumber::normalize($wallet);

        if ($normalized === null) {
            throw ValidationException::withMessages([
                'wallet' => 'رقم محفظة جوال باي غير صالح — مثال: 0599002286',
            ]);
        }

        $this->assertCodeAllowed($order);

        // Only one code may be live at a time, otherwise the customer can
        // confirm against a superseded amount.
        $order->payments()
            ->where('status', Payment::STATUS_OTP_SENT)
            ->update(['status' => Payment::STATUS_EXPIRED]);

        $payment = $order->payments()->create([
            'provider'   => 'jawwalpay',
            'method'     => 'otp',
            'otp_msg_id' => JawwalPayClient::newMessageId(),
            'wallet'     => $normalized,
            'amount'     => $order->total,
            'status'     => Payment::STATUS_INITIATED,
        ]);

        try {
            $response = $this->gateway->sendOtp($normalized, $order->total, $payment->otp_msg_id);
        } catch (JawwalPayException $e) {
            // Nothing was charged and no code went out, so this attempt is
            // simply dead — the customer can try again straight away.
            $payment->update([
                'status'            => Payment::STATUS_FAILED,
                'error_description' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->record($payment, $response);

        if ($response->failed()) {
            $payment->update(['status' => Payment::STATUS_FAILED]);

            return $payment->refresh();
        }

        $payment->update([
            'status'      => Payment::STATUS_OTP_SENT,
            'otp_sent_at' => now(),
        ]);

        $order->update(['payment_status' => Order::STATUS_AWAITING_PAYMENT]);

        return $payment->refresh();
    }

    /**
     * Step 2 — charge the wallet with the code the customer typed.
     *
     * @throws JawwalPayException when we asked and never heard back
     */
    public function confirm(Order $order, string $otp): Payment
    {
        $this->assertPayable($order);

        // The newest attempt a code actually went out for. Looking it up by
        // otp_sent_at rather than by status is what lets the two dead ends be
        // told apart: no code was ever sent, versus a code whose attempts ran
        // out (which flips the row to failed).
        $payment = $order->payments()->whereNotNull('otp_sent_at')->orderByDesc('id')->first();

        if (! $payment || $payment->status === Payment::STATUS_EXPIRED) {
            throw ValidationException::withMessages([
                'otp' => 'اطلب رمز التحقق أولاً',
            ]);
        }

        if (! $payment->awaitingConfirmation()) {
            throw new PaymentNotAllowedException('انتهت محاولات إدخال رمز التحقق — اطلب رمزاً جديداً');
        }

        // A fresh msgId per attempt: a retry after a wrong code is a different
        // charge, and reusing the id would come back as code 46 (duplicate).
        $payment->update([
            'confirm_attempts' => $payment->confirm_attempts + 1,
            'charge_msg_id'    => JawwalPayClient::newMessageId(),
        ]);

        try {
            $response = $this->gateway->payWithOtp(
                $payment->wallet,
                $payment->amount,
                $otp,
                $payment->charge_msg_id,
                [
                    // Travels with the charge so an unresolved attempt can be
                    // matched in their search_trans output later.
                    'additionalReferenceLabel' => $order->reference,
                    'additionalBillNo'         => $order->reference,
                ],
            );
        } catch (JawwalPayException $e) {
            // We asked for the money and the answer never arrived. It may have
            // gone through. Never mark this failed and never charge again
            // without a human checking the provider's records first.
            $payment->update([
                'status'            => Payment::STATUS_UNRESOLVED,
                'error_description' => $e->getMessage(),
            ]);

            throw $e;
        }

        $this->record($payment, $response);

        if ($response->successful()) {
            DB::transaction(function () use ($order, $payment) {
                $payment->update([
                    'status'       => Payment::STATUS_PAID,
                    'confirmed_at' => now(),
                ]);

                $order->update([
                    'payment_status' => Order::STATUS_PAID,
                    'paid_at'        => now(),
                ]);
            });

            return $payment->refresh();
        }

        $exhausted = $payment->confirm_attempts >= Payment::MAX_CONFIRM_ATTEMPTS;

        // A wrong code leaves the code live: the customer can type it again
        // until the attempts run out, and only then is the order marked failed.
        $payment->update(['status' => $exhausted ? Payment::STATUS_FAILED : Payment::STATUS_OTP_SENT]);

        if ($exhausted) {
            $order->update(['payment_status' => Order::STATUS_FAILED]);
        }

        return $payment->refresh();
    }

    /**
     * Ask the provider what actually became of a charge we never heard back on.
     *
     * There is no webhook and no lookup by msgId, which is why every charge
     * carries the order reference as externalReference — this is the only way
     * back to it.
     *
     * @return array{found: int, rows: array<int, mixed>, message: string}
     */
    public function lookup(Payment $payment): array
    {
        $response = $this->gateway->searchTransactions([
            'externalReference' => $payment->order->reference,
            'dateFrom'          => $payment->created_at->subDay()->format('d/m/Y H:i'),
        ]);

        $rows = $response->extraJson('txs') ?? [];

        return [
            'found'   => count($rows),
            'rows'    => $rows,
            'message' => $response->message(),
        ];
    }

    /**
     * Close an unresolved attempt by hand, once someone has checked the
     * provider's records. Deliberately manual: nothing automatic should decide
     * that money did or did not move.
     */
    public function resolveManually(Payment $payment, bool $paid, string $note): Payment
    {
        DB::transaction(function () use ($payment, $paid, $note) {
            $payment->update([
                'status'            => $paid ? Payment::STATUS_PAID : Payment::STATUS_FAILED,
                'confirmed_at'      => $paid ? now() : null,
                'error_description' => trim('تسوية يدوية: ' . $note),
            ]);

            $payment->order->update($paid
                ? ['payment_status' => Order::STATUS_PAID, 'paid_at' => now()]
                : ['payment_status' => Order::STATUS_FAILED]);
        });

        return $payment->refresh();
    }

    private function assertPayable(Order $order): void
    {
        if ($order->isPaid()) {
            throw PaymentNotAllowedException::alreadyPaid();
        }

        if (! $order->payable()) {
            throw PaymentNotAllowedException::closed($order->statusLabel());
        }

        if ($order->total <= 0) {
            throw new PaymentNotAllowedException('لا يمكن دفع طلب بمبلغ صفر');
        }

        // An attempt we never got an answer for could still have taken the
        // money; charging again on top of it is the one unrecoverable mistake.
        if ($order->payments()->where('status', Payment::STATUS_UNRESOLVED)->exists()) {
            throw new PaymentNotAllowedException(
                'هناك محاولة دفع لم تُؤكَّد بعد لهذا الطلب — يرجى التواصل معنا قبل إعادة المحاولة',
            );
        }
    }

    private function assertCodeAllowed(Order $order): void
    {
        if ($order->payments()->count() >= Payment::MAX_OTP_REQUESTS) {
            throw new PaymentNotAllowedException(
                'تم تجاوز عدد مرات طلب رمز التحقق لهذا الطلب — يرجى التواصل معنا',
            );
        }

        $last = $order->payments()->whereNotNull('otp_sent_at')->orderByDesc('id')->first();

        if ($last && $last->otp_sent_at->diffInSeconds(now()) < Payment::OTP_COOLDOWN_SECONDS) {
            $wait = Payment::OTP_COOLDOWN_SECONDS - (int) $last->otp_sent_at->diffInSeconds(now());

            throw new PaymentNotAllowedException("يرجى الانتظار {$wait} ثانية قبل طلب رمز جديد", 429);
        }
    }

    /** Keeps the gateway's answer against the payment, yes or no. */
    private function record(Payment $payment, JawwalPayResponse $response): void
    {
        $payment->update([
            'error_code'         => $response->errorCode(),
            'error_description'  => $response->description() ?: $response->message(),
            'provider_reference' => $response->reference(),
            'last_response'      => $response->raw,
        ]);
    }
}
