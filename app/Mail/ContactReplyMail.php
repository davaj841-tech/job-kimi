<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $recipientName,
        public string $trackingCode,
        public string $replyText
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'پاسخ پیام شما — '.$this->trackingCode);
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.contact-reply',
            with: [
                'name' => $this->recipientName,
                'trackingCode' => $this->trackingCode,
                'replyText' => $this->replyText,
            ]
        );
    }
}
