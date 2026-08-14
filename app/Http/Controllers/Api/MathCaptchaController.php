<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class MathCaptchaController extends BaseController
{
    public function challenge(): JsonResponse
    {
        $a = random_int(2, 9);
        $b = random_int(1, 9);
        $id = (string) Str::uuid();

        Cache::put("math_captcha:{$id}", (string) ($a + $b), now()->addMinutes(10));

        return $this->successResponse([
            'id' => $id,
            'question' => "{$a} + {$b} = ?",
            'ttl' => 600,
        ]);
    }
}
