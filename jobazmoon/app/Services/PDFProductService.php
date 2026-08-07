<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\PdfProduct;
use App\Models\PdfPurchase;
use App\Models\Transaction;
use App\Models\User;
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
        protected InvoiceService $invoiceService
    ) {}

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

        return DB::transaction(function () use ($user, $pdf, $amount, $original, $discount, $coupon) {
            $locked = User::query()->whereKey($user->id)->lockForUpdate()->first();

            if ($amount > 0) {
                if (! $locked || (int) $locked->wallet_balance < $amount) {
                    return ['success' => false, 'message' => 'موجودی کیف پول کافی نیست.', 'error' => 'insufficient_balance'];
                }
                $locked->decrement('wallet_balance', $amount);
            }

            PdfPurchase::query()->create([
                'user_id' => $locked->id,
                'pdf_product_id' => $pdf->id,
                'price_paid' => $amount,
                'purchased_at' => now(),
            ]);

            $tx = Transaction::query()->create([
                'user_id' => $locked->id,
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

            if ($coupon) {
                $this->couponService->redeem($coupon);
            }

            $this->invoiceService->ensureInvoice($tx);
            event(new \App\Events\PaymentSuccessful($tx));

            $locked->notify(new \App\Notifications\GenericDatabaseNotification(
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

        $transaction = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => $amount,
            'original_amount' => $original,
            'discount_amount' => $discount,
            'coupon_id' => $coupon?->id,
            'type' => 'purchase',
            'gateway' => $gateway,
            'status' => 'pending',
            'description' => 'خرید PDF: '.$pdf->title,
            'payable_type' => PdfProduct::class,
            'payable_id' => $pdf->id,
        ]);

        $callback = url('/payment/pdf?pdf_id='.$pdf->id);
        $result = $this->paymentService->initiate(
            $gateway,
            $amount,
            'خرید PDF '.$pdf->title.' — JobAzmoon',
            $callback,
            ['order_id' => (string) $transaction->id]
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
        ];
    }

    public function completeZarinPalPurchase(Transaction $transaction): bool
    {
        if ($transaction->status === 'success') {
            return true;
        }

        if ($transaction->payable_type !== PdfProduct::class || ! $transaction->payable_id) {
            return false;
        }

        return DB::transaction(function () use ($transaction) {
            $exists = PdfPurchase::query()
                ->where('user_id', $transaction->user_id)
                ->where('pdf_product_id', $transaction->payable_id)
                ->exists();

            if (! $exists) {
                PdfPurchase::query()->create([
                    'user_id' => $transaction->user_id,
                    'pdf_product_id' => $transaction->payable_id,
                    'price_paid' => $transaction->amount,
                    'purchased_at' => now(),
                ]);
            }

            $transaction->update(['status' => 'success']);

            if ($transaction->coupon_id) {
                $coupon = Coupon::query()->find($transaction->coupon_id);
                if ($coupon) {
                    $this->couponService->redeem($coupon);
                }
            }

            $this->invoiceService->ensureInvoice($transaction->fresh());
            event(new \App\Events\PaymentSuccessful($transaction->fresh()));

            $user = User::query()->find($transaction->user_id);
            $pdf = PdfProduct::query()->find($transaction->payable_id);
            if ($user && $pdf) {
                $user->notify(new \App\Notifications\GenericDatabaseNotification(
                    'pdf_purchased',
                    'خرید PDF موفق',
                    'فایل «'.$pdf->title.'» خریداری شد.',
                    '/my-purchases'
                ));
            }

            return true;
        });
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
        $path = $pdf->file_path;

        if (Storage::disk('local')->exists($path)) {
            return Storage::disk('local')->path($path);
        }

        if (Storage::disk('local')->exists('pdfs/'.$path)) {
            return Storage::disk('local')->path('pdfs/'.$path);
        }

        return null;
    }
}
