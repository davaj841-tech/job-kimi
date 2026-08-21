<?php

namespace App\Services;

use App\Models\Transaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Morilog\Jalali\Jalalian;

class InvoiceService
{
    public function ensureInvoice(Transaction $transaction): Transaction
    {
        if ($transaction->status !== 'success') {
            return $transaction;
        }

        if ($transaction->invoice_number && $transaction->invoice_pdf) {
            return $transaction;
        }

        $transaction->loadMissing('user');

        if (! $transaction->invoice_number) {
            $transaction->invoice_number = $this->generateNumber();
            $transaction->save();
        }

        $html = view('pdf.invoice', [
            'transaction' => $transaction,
            'invoiceNumber' => $transaction->invoice_number,
            'date' => Jalalian::fromCarbon($transaction->created_at ?? now())->format('Y/m/d'),
            'user' => $transaction->user,
            'original' => (int) ($transaction->original_amount ?: $transaction->amount),
            'discount' => (int) ($transaction->discount_amount ?: 0),
            'final' => (int) $transaction->amount,
            'description' => $transaction->description ?: 'خرید از جاب‌آزمون',
        ])->render();

        $pdf = Pdf::loadHTML($html)
            ->setPaper('a4')
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'chroot' => storage_path(),
                'defaultFont' => 'DejaVu Sans',
            ]);

        $path = 'invoices/'.$transaction->invoice_number.'.pdf';
        Storage::disk('local')->put($path, $pdf->output());
        $transaction->update(['invoice_pdf' => $path]);

        return $transaction->fresh();
    }

    public function generateNumber(): string
    {
        $j = Jalalian::now();

        return sprintf(
            'IR-%s%s%s-%04d',
            $j->getYear(),
            str_pad((string) $j->getMonth(), 2, '0', STR_PAD_LEFT),
            str_pad((string) $j->getDay(), 2, '0', STR_PAD_LEFT),
            random_int(1000, 9999)
        );
    }

    public function pdfBinary(Transaction $transaction): string
    {
        $tx = $this->ensureInvoice($transaction);
        $relative = $this->storedRelativePath($tx);
        $binary = $this->readStoredPdf($relative);
        if ($binary !== null) {
            return $binary;
        }

        $tx->update(['invoice_pdf' => null]);
        $tx = $this->ensureInvoice($tx->fresh());

        return $this->readStoredPdf($this->storedRelativePath($tx)) ?? '';
    }

    protected function storedRelativePath(Transaction $tx): ?string
    {
        $stored = (string) $tx->invoice_pdf;
        if ($stored === '') {
            return null;
        }

        if (! str_contains($stored, '://') && ! str_starts_with($stored, '/')) {
            return $stored;
        }

        $relative = str_replace('/storage/', '', parse_url($stored, PHP_URL_PATH) ?: '');

        return $relative !== '' ? $relative : null;
    }

    protected function readStoredPdf(?string $relative): ?string
    {
        if (! $relative) {
            return null;
        }

        foreach (['local', 'public'] as $disk) {
            if (Storage::disk($disk)->exists($relative)) {
                return Storage::disk($disk)->get($relative);
            }
        }

        return null;
    }
}
