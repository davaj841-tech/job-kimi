<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TicketReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $ticketSubject,
        public string $replyMessage,
        public string $ticketUrl,
        public ?string $name = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'پاسخ پشتیبانی — '.$this->ticketSubject);
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.ticket-reply',
            with: [
                'name' => $this->name,
                'ticketSubject' => $this->ticketSubject,
                'replyMessage' => $this->replyMessage,
                'ticketUrl' => $this->ticketUrl,
            ]
        );
    }
}
