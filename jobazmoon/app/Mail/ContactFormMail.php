<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ContactFormMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $senderName,
        public string $senderEmail,
        public string $subjectKey,
        public string $messageText
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'پیام جدید از فرم تماس — جاب‌آزمون');
    }

    public function content(): Content
    {
        $labels = [
            'support' => 'پشتیبانی',
            'complaint' => 'شکایت',
            'suggestion' => 'پیشنهاد',
            'partnership' => 'همکاری',
        ];

        return new Content(
            html: 'emails.contact-form',
            with: [
                'name' => $this->senderName,
                'email' => $this->senderEmail,
                'subjectLabel' => $labels[$this->subjectKey] ?? $this->subjectKey,
                'messageText' => $this->messageText,
            ]
        );
    }
}
