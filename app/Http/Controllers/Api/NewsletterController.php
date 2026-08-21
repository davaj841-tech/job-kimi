<?php

namespace App\Http\Controllers\Api;

use App\Models\NewsletterSubscription;
use App\Support\IranMobile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class NewsletterController extends BaseController
{
    public function subscribe(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'contact' => ['required', 'string', 'max:191'],
        ], [
            'contact.required' => 'ایمیل یا موبایل الزامی است.',
            'contact.max' => 'طول اطلاعات تماس بیش از حد مجاز است.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $parsed = $this->parseContact((string) $request->input('contact'));

        if ($parsed === null) {
            throw ValidationException::withMessages([
                'contact' => ['ایمیل یا شماره موبایل معتبر وارد کنید.'],
            ]);
        }

        ['type' => $type, 'value' => $value] = $parsed;
        $hash = hash('sha256', $type.':'.$value);

        $existing = NewsletterSubscription::query()->where('contact_hash', $hash)->first();

        if ($existing) {
            return $this->successResponse(
                ['already_subscribed' => true],
                'این ایمیل یا موبایل قبلاً در خبرنامه ثبت شده است.'
            );
        }

        NewsletterSubscription::query()->create([
            'contact_type' => $type,
            'contact_value' => $value,
            'contact_hash' => $hash,
            'ip_address' => (string) $request->ip(),
        ]);

        return $this->successResponse(
            ['already_subscribed' => false],
            'عضویت در خبرنامه با موفقیت ثبت شد.'
        );
    }

    /**
     * @return array{type: string, value: string}|null
     */
    protected function parseContact(string $raw): ?array
    {
        $raw = trim($raw);
        if ($raw === '') {
            return null;
        }

        if (str_contains($raw, '@')) {
            $email = mb_strtolower($raw);
            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return null;
            }

            return ['type' => 'email', 'value' => $email];
        }

        $mobile = IranMobile::normalize($raw);
        if ($mobile === null) {
            return null;
        }

        return ['type' => 'mobile', 'value' => $mobile];
    }
}
