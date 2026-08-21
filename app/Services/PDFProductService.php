<?php

namespace App\Services;

use App\Events\PaymentSuccessful;
use App\Exceptions\IdempotencyException;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Coupon;
use App\Models\PdfProduct;
use App\Models\PdfPurchase;
use App\Models\Transaction;
use App\Models\User;
use App\Models\WalletLedger;
use App\Notifications\GenericDatabaseNotification;
use App\Repositories\PDFProductRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PDFProductService
{
    public function __construct(
        protected PDFProductRepository $pdfProductRepository,
        protected WalletService $walletService,
        protected PaymentService $paymentService,
        protected CouponService $couponService,
        protected InvoiceService $invoiceService,
        protected IdempotencyService $idempotencyService
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getAvailable(array $filters): LengthAwarePaginator
    {
        return $this->pdfProductRepository->getActive($filters);
    }

    /**
     * @return array{success: bool, message: string, payment_url?: string, error?: string}
     */
    public function purchase(User $user, PdfProduct $pdf, string $method, ?string $couponCode = null, ?string $gateway = null): array
    {
        if ($this->canDownload($user, $pdf)) {
            return [
                'success' => false,
                'message' => 'شما قبلاً این فایل را خریداری کرده‌اید',
                'error' => 'already_purchased',
            ];
        }

        $original = (int) $pdf->price;
        $amount = $original;
        $coupon = null;
        $discount = 0;

        if ($couponCode) {
            $check = $this->couponService->validate($couponCode, $original, 'pdf');
            if (! $check['valid']) {
                return ['success' => false, 'message' => $check['message'], 'error' => 'invalid_coupon'];
            }
            $discount = (int) $check['discount_amount'];
            $amount = (int) $check['final_amount'];
            $coupon = $check['coupon'];
        }

        if ($method === 'wallet') {
            return $this->purchaseWithWallet($user, $pdf, $amount, $original, $discount, $coupon);
        }

        $gateway = $this->paymentService->resolveGatewayName($gateway ?: $method);

        return $this->purchaseWithGateway($user, $pdf, $amount, $original, $discount, $coupon, $gateway);
    }

    protected function purchaseWithWallet(
        User $user,
        PdfProduct $pdf,
        int $amount,
        int $original,
        int $discount,
        ?Coupon $coupon
    ): array {
        if ($amount > 0 && ! $this->walletService->hasEnough($user, $amount)) {
            return ['success' => false, 'message' => 'موجودی کیف پول کافی نیست.', 'error' => 'insufficient_balance'];
        }

        try {
            return DB::transaction(function () use ($user, $pdf, $amount, $original, $discount, $coupon) {
                $tx = Transaction::query()->create([
                    'user_id' => $user->id,
                    'amount' => $amount,
                    'original_amount' => $original,
                    'discount_amount' => $discount,
                    'coupon_id' => $coupon?->id,
                    'type' => 'purchase',
                    'gateway' => 'wallet',
                    'status' => 'success',
                    'description' => 'خرید PDF: '.$pdf->title,
                    'payable_type' => PdfProduct::class,
                    'payable_id' => $pdf->id,
                ]);

                if ($amount > 0) {
                    $this->walletService->debit($user, $amount, [
                        'source_key' => 'pdf:'.$tx->id,
                        'transaction' => $tx,
                        'type' => WalletLedger::TYPE_PURCHASE,
                        'tx_type' => 'purchase',
                        'description' => $tx->description,
                        'gateway' => 'wallet',
                    ]);
                }

                PdfPurchase::query()->create([
                    'user_id' => $user->id,
                    'pdf_product_id' => $pdf->id,
                    'price_paid' => $amount,
                    'purchased_at' => now(),
                ]);

                if ($coupon) {
                    $this->couponService->redeem($coupon);
                }

                $this->invoiceService->ensureInvoice($tx);
                event(new PaymentSuccessful($tx));

                $user->notify(new GenericDatabaseNotification(
                    'pdf_purchased',
                    'خرید PDF موفق',
                    'فایل «'.$pdf->title.'» خریداری شد.',
                    '/my-purchases'
                ));

                return [
                    'success' => true,
                    'message' => 'خرید با موفقیت انجام شد.',
                ];
            });
        } catch (InsufficientBalanceException) {
            return ['success' => false, 'message' => 'موجودی کیف پول کافی نیست.', 'error' => 'insufficient_balance'];
        }
    }

    protected function purchaseWithGateway(
        User $user,
        PdfProduct $pdf,
        int $amount,
        int $original,
        int $discount,
        ?Coupon $coupon,
        string $gateway
    ): array {
        if ($amount <= 0) {
            return $this->purchaseWithWallet($user, $pdf, 0, $original, $discount, $coupon);
        }

        $idempotency = $this->idempotencyService;
        $idempotencyKey = $idempotency->generateKey();

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'original_amount' => $original,
            'discount_amount' => $discount,
            'coupon_id' => $coupon?->id,
            'type' => 'purchase',
            'gateway' => $gateway,
            'status' => Transaction::STATUS_PENDING,
            'idempotency_key' => $idempotencyKey,
            'description' => 'خرید PDF: '.$pdf->title,
            'payable_type' => PdfProduct::class,
            'payable_id' => $pdf->id,
        ]);

        $callback = $idempotency->appendKeyToCallback(url('/payment/pdf?pdf_id='.$pdf->id), $idempotencyKey);
        $result = $this->paymentService->initiate(
            $gateway,
            $amount,
            'خرید PDF '.$pdf->title.' — JobAzmoon',
            $callback,
            ['order_id' => (string) $transaction->id, 'idempotency_key' => $idempotencyKey]
        );

        if ($result['error'] || ! $result['authority']) {
            $transaction->update(['status' => 'failed']);

            return [
                'success' => false,
                'message' => $result['error'] ?? 'خطا در اتصال به درگاه.',
                'error' => 'gateway_error',
            ];
        }

        $transaction->update(['reference_id' => $result['authority']]);

        return [
            'success' => true,
            'message' => 'در حال انتقال به درگاه پرداخت',
            'payment_url' => $result['payment_url'],
            'idempotency_key' => $idempotencyKey,
        ];
    }

    public function completeZarinPalPurchase(Transaction $transaction): bool
    {
        if ($transaction->payable_type !== PdfProduct::class || ! $transaction->payable_id) {
            return false;
        }

        try {
            $outcome = $this->idempotencyService->completeOnce($transaction, function (Transaction $locked) {
                $exists = PdfPurchase::query()
                    ->where('user_id', $locked->user_id)
                    ->where('pdf_product_id', $locked->payable_id)
                    ->lockForUpdate()
                    ->exists();

                if (! $exists) {
                    PdfPurchase::query()->create([
                        'user_id' => $locked->user_id,
                        'pdf_product_id' => $locked->payable_id,
                        'price_paid' => $locked->amount,
                        'purchased_at' => now(),
                    ]);
                }

                if ($locked->coupon_id) {
                    $coupon = Coupon::query()->find($locked->coupon_id);
                    if ($coupon) {
                        $this->couponService->redeem($coupon);
                    }
                }

                return true;
            });
        } catch (IdempotencyException) {
            return $transaction->fresh()?->status === Transaction::STATUS_COMPLETED;
        }

        if (! $outcome['already_processed']) {
            $fresh = $outcome['transaction']->fresh();
            $this->invoiceService->ensureInvoice($fresh);

            event(new PaymentSuccessful($fresh->fresh()));

            $user = User::query()->find($fresh->user_id);
            $pdf = PdfProduct::query()->find($fresh->payable_id);
            if ($user && $pdf) {
                $user->notify(new GenericDatabaseNotification(
                    'pdf_purchased',
                    'خرید PDF موفق',
                    'فایل «'.$pdf->title.'» خریداری شد.',
                    '/my-purchases'
                ));
            }
        }

        return true;
    }

    public function canDownload(User $user, PdfProduct $pdf): bool
    {
        return PdfPurchase::query()
            ->where('user_id', $user->id)
            ->where('pdf_product_id', $pdf->id)
            ->exists();
    }

    public function getDownloadUrl(PdfProduct $pdf): string
    {
        return url('/api/v1/pdf-products/'.$pdf->id.'/download');
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function storeUploaded(array $data, $file, $thumbnail = null): PdfProduct
    {
        $uuid = (string) Str::uuid();
        $filePath = $file->storeAs('pdfs', $uuid.'.pdf', 'local');

        $thumbnailPath = null;
        if ($thumbnail) {
            $ext = $thumbnail->getClientOriginalExtension() ?: 'jpg';
            $thumbnailPath = $thumbnail->storeAs('pdf_thumbnails', $uuid.'.'.$ext, 'public');
        }

        return PdfProduct::query()->create([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'price' => $data['price'],
            'category' => $data['category'] ?? null,
            'file_path' => $filePath,
            'thumbnail' => $thumbnailPath,
            'is_active' => $data['is_active'] ?? true,
            'job_post_id' => $data['job_post_id'] ?? null,
        ]);
    }

    public function absoluteFilePath(PdfProduct $pdf): ?string
    {
        $path = str_replace('\\', '/', (string) $pdf->file_path);
        $path = ltrim($path, '/');
        if ($path === '' || str_contains($path, '..')) {
            return null;
        }
        if (! str_starts_with($path, 'pdfs/')) {
            $path = 'pdfs/'.basename($path);
        }

        if (Storage::disk('local')->exists($path)) {
            $full = str_replace('\\', '/', Storage::disk('local')->path($path));
            $root = str_replace('\\', '/', Storage::disk('local')->path('pdfs'));
            if (str_starts_with($full, rtrim($root, '/').'/') || $full === $root) {
                return Storage::disk('local')->path($path);
            }
        }

        return null;
    }
}
