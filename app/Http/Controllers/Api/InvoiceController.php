<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\Transaction;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InvoiceController extends BaseController
{
    public function __construct(
        protected InvoiceService $invoiceService
    ) {}

    public function download(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $tx = Transaction::query()->findOrFail($id);
        $user = $request->user();

        if ($tx->user_id !== $user->id && ! in_array($user->role, ['admin', 'operator'], true)) {
            return $this->errorResponse('دسترسی ندارید.', 403);
        }

        if ($tx->status !== 'success') {
            return $this->errorResponse('فاکتور فقط برای تراکنش موفق صادر می‌شود.', 422);
        }

        $binary = $this->invoiceService->pdfBinary($tx);
        $filename = ($tx->invoice_number ?: 'invoice-'.$tx->id).'.pdf';

        return response()->streamDownload(function () use ($binary) {
            echo $binary;
        }, $filename, ['Content-Type' => 'application/pdf']);
    }

    public function regenerate(Request $request, int $id): JsonResponse
    {
        if (! in_array($request->user()->role, ['admin', 'operator'], true)) {
            return $this->errorResponse('دسترسی ندارید.', 403);
        }

        $tx = Transaction::query()->findOrFail($id);
        $tx->update(['invoice_pdf' => null, 'invoice_number' => $tx->invoice_number]);
        $tx = $this->invoiceService->ensureInvoice($tx->fresh());

        return $this->successResponse([
            'invoice_number' => $tx->invoice_number,
            'invoice_pdf' => $tx->invoice_pdf,
        ], 'فاکتور بازتولید شد.');
    }
}
