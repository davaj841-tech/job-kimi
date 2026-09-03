<?php

namespace App\Listeners;

use App\Events\PaymentSuccessful;
use App\Services\AuditLogService;
use App\Services\InvoiceService;
use App\Services\MailConfigService;
use Illuminate\Support\Facades\Cache;

class GenerateInvoice
{
    public function __construct(
        protected InvoiceService $invoices,
        protected AuditLogService $audit,
        protected MailConfigService $mail,
    ) {}

    public function handle(PaymentSuccessful $event): void
    {
        $tx = $event->transaction;
        if ($tx->status !== 'success') {
            return;
        }

        $tx = $this->invoices->ensureInvoice($tx);
        $this->audit->log('payment.success', $tx, null, [
            'amount' => (int) $tx->amount,
            'type' => $tx->type,
            'gateway' => $tx->gateway,
        ], $tx->user_id);

        $user = $tx->user ?? $tx->loadMissing('user')->user;
        if (! $user?->email) {
            return;
        }

        // Idempotent: duplicate PaymentSuccessful / callback must not re-mail.
        $lockKey = 'mail:payment_receipt:'.$tx->id;
        if (! Cache::add($lockKey, 1, now()->addDays(30))) {
            return;
        }

        $this->mail->sendPaymentReceipt($user, $tx);
    }
}
