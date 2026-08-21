<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\PdfProductResource;
use App\Models\PdfProduct;
use App\Models\Transaction;
use App\Repositories\PDFProductRepository;
use App\Services\Payment\GatewayCallbackService;
use App\Services\PaymentService;
use App\Services\PDFProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class PDFProductController extends BaseController
{
    public function __construct(
        protected PDFProductService $pdfProductService,
        protected PDFProductRepository $pdfProductRepository,
        protected PaymentService $paymentService,
        protected GatewayCallbackService $gatewayCallback,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['category', 'search', 'price_min', 'price_max', 'per_page', 'sort']);
        $products = $this->pdfProductService->getAvailable($filters);

        $purchasedIds = [];
        $user = $request->user('sanctum');
        if ($user) {
            $purchasedIds = \App\Models\PdfPurchase::query()
                ->where('user_id', $user->id)
                ->pluck('pdf_product_id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        $items = collect($products->items())->map(function (PdfProduct $pdf) use ($purchasedIds) {
            $row = (new PdfProductResource($pdf))->resolve();
            $row['is_purchased'] = in_array((int) $pdf->id, $purchasedIds, true);

            return $row;
        });

        return $this->successResponse([
            'data' => $items,
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
                'per_page' => $products->perPage(),
                'total' => $products->total(),
            ],
            'categories' => $this->pdfProductRepository->getCategories(),
        ]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $pdf = $this->pdfProductRepository->findActive($id);

        if (! $pdf) {
            return $this->errorResponse('محصول یافت نشد.', 404);
        }

        $user = $request->user('sanctum');
        $isPurchased = $user ? $this->pdfProductService->canDownload($user, $pdf) : false;
        $purchase = $user && $isPurchased
            ? $this->pdfProductRepository->getPurchase($user, $pdf)
            : null;

        $pdf->is_purchased = $isPurchased;
        $pdf->purchase_date = $purchase?->purchased_at?->toIso8601String();
        $pdf->download_url = $isPurchased ? $this->pdfProductService->getDownloadUrl($pdf) : null;

        return $this->successResponse(new PdfProductResource($pdf));
    }

    public function purchase(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'payment_method' => ['required', 'in:wallet,zarinpal,nextpay,idpay'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
            'gateway' => ['nullable', 'string', 'in:zarinpal,nextpay,idpay,mellat,shaparak'],
        ]);

        $pdf = $this->pdfProductRepository->findActive($id);

        if (! $pdf) {
            return $this->errorResponse('محصول یافت نشد.', 404);
        }

        $gateway = $data['payment_method'] === 'wallet'
            ? null
            : ($data['gateway'] ?? $data['payment_method']);

        $result = $this->pdfProductService->purchase(
            $request->user(),
            $pdf,
            $data['payment_method'] === 'wallet' ? 'wallet' : ($gateway ?: 'zarinpal'),
            $data['coupon_code'] ?? null,
            $gateway
        );

        if (! $result['success']) {
            return $this->errorResponse($result['message'], 400, ['code' => $result['error'] ?? null]);
        }

        return $this->successResponse([
            'payment_url' => $result['payment_url'] ?? null,
        ], $result['message']);
    }

    public function verifyPurchase(Request $request, int $id): JsonResponse
    {
        $result = $this->gatewayCallback->complete(
            $request,
            fn (Transaction $tx) => $tx->type === 'purchase'
                && $tx->payable_type === PdfProduct::class
                && (int) $tx->payable_id === $id,
            function (Transaction $locked) {
                $this->pdfProductService->completeZarinPalPurchase($locked);
            }
        );

        if (! $result->ok) {
            return $this->errorResponse($result->message, $result->status);
        }

        return $this->successResponse([
            'already_processed' => $result->alreadyProcessed,
        ], 'خرید با موفقیت انجام شد.');
    }

    public function download(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $pdf = $this->pdfProductRepository->findActive($id);

        if (! $pdf) {
            return $this->errorResponse('محصول یافت نشد.', 404);
        }

        if (! $this->pdfProductService->canDownload($request->user(), $pdf)) {
            return $this->errorResponse('دسترسی غیرمجاز.', 403);
        }

        $path = $this->pdfProductService->absoluteFilePath($pdf);

        if (! $path || ! file_exists($path)) {
            return $this->errorResponse('فایل یافت نشد.', 404);
        }

        $pdf->increment('download_count');

        $filename = (Str::slug($pdf->title) ?: 'download').'.pdf';

        return response()->download($path, $filename, [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function myPurchases(Request $request): JsonResponse
    {
        $products = $this->pdfProductRepository->getPurchasedByUser($request->user());

        $items = $products->map(function (PdfProduct $pdf) {
            $purchase = $pdf->purchases->first();
            $pdf->is_purchased = true;
            $pdf->purchase_date = $purchase?->purchased_at?->toIso8601String();
            $pdf->download_url = $this->pdfProductService->getDownloadUrl($pdf);

            return new PdfProductResource($pdf);
        });

        return $this->successResponse($items);
    }
}
