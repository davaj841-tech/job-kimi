<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SubscriptionExpiryMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $expiresAt,
        public ?string $name = null,
        public ?string $renewUrl = null
    ) {
        $this->renewUrl = $renewUrl ?: rtrim(config('app.url'), '/').'/subscription';
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'اشتراک شما رو به انقضاست');
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.subscription-expiry',
            with: [
                'name' => $this->name,
                'expiresAt' => $this->expiresAt,
                'renewUrl' => $this->renewUrl,
            ]
        );
    }
}
