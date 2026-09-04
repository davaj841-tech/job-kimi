<?php

namespace App\Jobs;

use App\Services\Sms\SmsManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendSmsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    /** @var list<int> */
    public array $backoff;

    public function __construct(
        public readonly string $mobile,
        public readonly string $message,
        public readonly string $messageType = 'transactional',
    ) {
        $this->tries = max(1, (int) config('sms.queue.tries', 3));
        $this->backoff = config('sms.queue.backoff', [30, 120, 300]);
        $this->onQueue((string) config('sms.queue.queue', 'default'));

        $connection = config('sms.queue.connection');
        if (is_string($connection) && $connection !== '') {
            $this->onConnection($connection);
        }
    }

    public function handle(SmsManager $sms): void
    {
        $result = $sms->sendDetailed($this->mobile, $this->message, $this->messageType);

        if (! $result->success && ! $result->skipped) {
            $this->fail(new \RuntimeException($result->errorMessage ?? 'SMS delivery failed'));
        }
    }
}
