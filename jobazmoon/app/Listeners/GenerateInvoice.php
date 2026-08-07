<?php

namespace App\Listeners;

use App\Events\PaymentSuccessful;
use App\Services\AuditLogService;
use App\Services\InvoiceService;

class GenerateInvoice
{
    public function __construct(
        protected InvoiceService $invoices,
        protected AuditLogService $audit
    ) {}

    public function handle(PaymentSuccessful $event): void
    {
        $tx = $event->transaction;
        if ($tx->status !== 'success') {
            return;
        }

        $this->invoices->ensureInvoice($tx);
        $this->audit->log('payment.success', $tx, null, [
            'amount' => (int) $tx->amount,
            'type' => $tx->type,
            'gateway' => $tx->gateway,
        ], $tx->user_id);
    }
}
