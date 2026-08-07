<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Api\BaseController;
use App\Jobs\SendEmailJob;
use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Services\MailConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class PasswordResetController extends BaseController
{
    public function __construct(
        protected MailConfigService $mail
    ) {}

    public function forgot(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $user = User::query()->where('email', $data['email'])->first();

        // Always return success to avoid email enumeration
        if ($user) {
            $token = Str::random(64);
            DB::table('password_reset_tokens')->updateOrInsert(
                ['email' => $user->email],
                ['token' => Hash::make($token), 'created_at' => now()]
            );

            $url = rtrim(config('app.url'), '/').'/reset-password?token='.$token.'&email='.urlencode($user->email);
            $this->mail->queueTo($user->email, new PasswordResetMail($url, $user->name, 60));
        }

        return $this->successResponse(null, 'لینک بازنشانی به ایمیل شما ارسال شد');
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $row = DB::table('password_reset_tokens')->where('email', $data['email'])->first();

        if (! $row || ! Hash::check($data['token'], $row->token)) {
            return $this->errorResponse('لینک بازنشانی نامعتبر است.', 422);
        }

        if ($row->created_at && \Illuminate\Support\Carbon::parse($row->created_at)->lt(now()->subMinutes(60))) {
            return $this->errorResponse('لینک بازنشانی منقضی شده است.', 422);
        }

        $user = User::query()->where('email', $data['email'])->first();
        if (! $user) {
            return $this->errorResponse('کاربر یافت نشد.', 404);
        }

        $user->update(['password' => $data['password']]);
        DB::table('password_reset_tokens')->where('email', $data['email'])->delete();

        return $this->successResponse(null, 'رمز عبور با موفقیت تغییر کرد.');
    }
}
