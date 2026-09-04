<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ?string $name,
        public int $amount,
        public string $invoiceNumber,
        public string $description,
        public string $paidAt,
        public string $invoiceUrl,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'رسید پرداخت جاب‌آزمون — '.$this->invoiceNumber);
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.payment-receipt',
            with: [
                'name' => $this->name,
                'amount' => $this->amount,
                'invoiceNumber' => $this->invoiceNumber,
                'description' => $this->description,
                'paidAt' => $this->paidAt,
                'invoiceUrl' => $this->invoiceUrl,
            ]
        );
    }
}
