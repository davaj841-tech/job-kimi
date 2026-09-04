<?php

declare(strict_types=1);

namespace Tests\Feature\Wallet;

use App\Models\AuditLog;
use App\Models\PdfProduct;
use App\Models\PdfPurchase;
use App\Models\User;
use App\Services\FeatureFlagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PdfWalletPurchaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(FeatureFlagService::class)->enable('pdf-store');
        app(FeatureFlagService::class)->enable('wallet');
    }

    public function test_pdf_purchase_with_wallet_debits_once_and_grants_access(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'wallet_balance' => 50000,
        ]);
        Sanctum::actingAs($user);

        $pdf = PdfProduct::query()->create([
            'title' => 'فایل تست کیف پول',
            'file_path' => 'pdfs/wallet-test.pdf',
            'price' => 25000,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/pdf-products/'.$pdf->id.'/purchase', [
            'payment_method' => 'wallet',
        ]);

        $response->assertOk();
        $this->assertSame(25000, (int) $user->fresh()->wallet_balance);
        $this->assertTrue(
            PdfPurchase::query()
                ->where('user_id', $user->id)
                ->where('pdf_product_id', $pdf->id)
                ->exists()
        );

        $duplicate = $this->postJson('/api/v1/pdf-products/'.$pdf->id.'/purchase', [
            'payment_method' => 'wallet',
        ]);
        $duplicate->assertStatus(400);
        $this->assertSame(25000, (int) $user->fresh()->wallet_balance);
    }

    public function test_pdf_wallet_purchase_fails_with_insufficient_balance(): void
    {
        $user = User::factory()->create([
            'status' => 'active',
            'wallet_balance' => 1000,
        ]);
        Sanctum::actingAs($user);

        $pdf = PdfProduct::query()->create([
            'title' => 'فایل گران',
            'file_path' => 'pdfs/expensive.pdf',
            'price' => 25000,
            'is_active' => true,
        ]);

        $this->postJson('/api/v1/pdf-products/'.$pdf->id.'/purchase', [
            'payment_method' => 'wallet',
        ])->assertStatus(400);

        $this->assertSame(1000, (int) $user->fresh()->wallet_balance);
        $this->assertSame(0, PdfPurchase::query()->where('user_id', $user->id)->count());
    }
}
