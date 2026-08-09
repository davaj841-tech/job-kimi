<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PasswordResetMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $resetUrl,
        public ?string $name = null,
        public int $expiresMinutes = 60
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'درخواست بازنشانی رمز عبور');
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.password-reset',
            with: [
                'name' => $this->name,
                'resetUrl' => $this->resetUrl,
                'expiresMinutes' => $this->expiresMinutes,
            ]
        );
    }
}
