<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public ?string $name = null,
        public ?string $examUrl = null
    ) {
        $this->examUrl = $examUrl ?: rtrim(config('app.url'), '/').'/exams';
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'به جاب‌آزمون خوش آمدید');
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.welcome',
            with: [
                'name' => $this->name,
                'examUrl' => $this->examUrl,
            ]
        );
    }
}
