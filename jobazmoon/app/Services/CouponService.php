<?php

namespace App\Services;

use App\Models\Coupon;
use Illuminate\Support\Facades\DB;

class CouponService
{
    /**
     * @return array{valid: bool, message: string, discount_amount?: int, final_amount?: int, coupon?: Coupon}
     */
    public function validate(string $code, int $amount, string $type): array
    {
        $coupon = Coupon::query()
            ->where('code', strtoupper(trim($code)))
            ->first();

        if (! $coupon || ! $coupon->is_active) {
            return ['valid' => false, 'message' => 'کد تخفیف نامعتبر یا منقضی شده است'];
        }

        if ($coupon->starts_at && $coupon->starts_at->isFuture()) {
            return ['valid' => false, 'message' => 'کد تخفیف نامعتبر یا منقضی شده است'];
        }

        if ($coupon->expires_at && $coupon->expires_at->isPast()) {
            return ['valid' => false, 'message' => 'کد تخفیف نامعتبر یا منقضی شده است'];
        }

        if ($coupon->max_uses !== null && $coupon->used_count >= $coupon->max_uses) {
            return ['valid' => false, 'message' => 'کد تخفیف نامعتبر یا منقضی شده است'];
        }

        if ($coupon->min_purchase !== null && $amount < (int) $coupon->min_purchase) {
            return ['valid' => false, 'message' => 'مبلغ خرید کمتر از حداقل مجاز کد تخفیف است'];
        }

        if ($coupon->applicable_to !== 'both' && $coupon->applicable_to !== $type) {
            return ['valid' => false, 'message' => 'این کد برای این نوع خرید قابل استفاده نیست'];
        }

        $discount = $this->calculateDiscount($coupon, $amount);

        return [
            'valid' => true,
            'message' => 'کد تخفیف اعمال شد',
            'discount_amount' => $discount,
            'final_amount' => max(0, $amount - $discount),
            'coupon' => $coupon,
        ];
    }

    public function calculateDiscount(Coupon $coupon, int $amount): int
    {
        if ($coupon->type === 'percentage') {
            return (int) floor($amount * ((float) $coupon->value / 100));
        }

        return min((int) $coupon->value, $amount);
    }

    public function redeem(Coupon $coupon): void
    {
        DB::table('coupons')->where('id', $coupon->id)->increment('used_count');
    }
}
