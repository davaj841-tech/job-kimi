<?php

namespace App\Jobs;

use App\Services\MailConfigService;
use App\Support\EmailMask;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Mail\Mailable as MailableContract;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public int $timeout = 60;

    public function __construct(
        public string $recipient,
        public MailableContract $mailable
    ) {}

    public function handle(MailConfigService $mailConfig): void
    {
        $mailConfig->applySmtpFromSettings();

        try {
            Mail::to($this->recipient)->send($this->mailable);

            Log::info('Email sent', [
                'recipient' => EmailMask::mask($this->recipient),
                'mailable' => class_basename($this->mailable),
            ]);
        } catch (Throwable $e) {
            Log::error('Email failed', [
                'recipient' => EmailMask::mask($this->recipient),
                'mailable' => class_basename($this->mailable),
                'exception' => class_basename($e),
            ]);

            throw $e;
        }
    }
}
