<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Mailable as MailableContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $recipient,
        public MailableContract $mailable
    ) {}

    public function handle(): void
    {
        try {
            Mail::to($this->recipient)->send($this->mailable);

            Log::info('Email sent', [
                'recipient' => $this->recipient,
                'mailable' => $this->mailable::class,
            ]);
        } catch (\Throwable $e) {
            Log::error('Email failed', [
                'recipient' => $this->recipient,
                'mailable' => $this->mailable::class,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
