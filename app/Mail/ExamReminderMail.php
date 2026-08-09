<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExamReminderMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $title,
        public ?string $name = null,
        public ?string $examDate = null,
        public ?string $url = null
    ) {
        $this->url = $url ?: rtrim(config('app.url'), '/').'/jobs';
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: "یادآوری: آزمون {$this->title} فردا برگزار می‌شود");
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.exam-reminder',
            with: [
                'subject' => "یادآوری: آزمون {$this->title} فردا برگزار می‌شود",
                'title' => $this->title,
                'name' => $this->name,
                'examDate' => $this->examDate,
                'url' => $this->url,
            ]
        );
    }
}
