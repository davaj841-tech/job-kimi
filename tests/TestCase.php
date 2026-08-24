<?php

namespace Tests;

use App\Services\Payment\FakePaymentGateway;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

abstract class TestCase extends BaseTestCase
{
    /**
     * Most feature tests assume the app is already installed.
     * Installer tests must opt out so /install routes remain registered.
     */
    protected bool $ensureInstalledMarker = true;

    protected function setUp(): void
    {
        parent::setUp();
        FakePaymentGateway::reset();

        if ($this->ensureInstalledMarker && ! is_file(storage_path('installed'))) {
            @file_put_contents(storage_path('installed'), 'test-bootstrap');
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function withAuthCaptcha(array $payload = []): array
    {
        $id = (string) Str::uuid();
        Cache::put("math_captcha:{$id}", '7', now()->addMinutes(10));

        return array_merge($payload, [
            'captcha_id' => $id,
            'captcha_answer' => '7',
        ]);
    }
}
