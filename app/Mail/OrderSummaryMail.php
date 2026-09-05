<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The order summary a customer can have sent to any address they like
 * (handoff 12 §6) — it is not tied to the account's e-mail.
 */
class OrderSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Order $order) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'ملخص طلبك ' . $this->order->reference . ' — جلاسيه الأمير');
    }

    public function content(): Content
    {
        return new Content(markdown: 'mail.order-summary', with: ['order' => $this->order]);
    }
}
