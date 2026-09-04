<?php

declare(strict_types=1);

namespace Tests\Feature\Payment;

use App\Models\PdfProduct;
use App\Models\PdfPurchase;
use App\Models\Transaction;
use App\Models\User;
use App\Services\FeatureFlagService;
use App\Services\Payment\FakePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class PdfPaymentFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(FeatureFlagService::class)->enable('pdf-store');
    }

    public function test_full_pdf_gateway_purchase_and_verify(): void
    {
        $user = User::factory()->create(['status' => 'active', 'role' => 'jobseeker']);
        Sanctum::actingAs($user);

        $pdf = PdfProduct::query()->create([
            'title' => 'فایل تست پرداخت',
            'file_path' => 'pdfs/test.pdf',
            'price' => 25000,
            'is_active' => true,
        ]);

        $purchase = $this->postJson('/api/v1/pdf-products/'.$pdf->id.'/purchase', [
            'payment_method' => 'zarinpal',
            'gateway' => 'zarinpal',
        ]);

        $purchase->assertOk();
        $this->assertStringContainsString('pay.fake.test', (string) $purchase->json('data.payment_url'));

        $tx = Transaction::query()
            ->where('user_id', $user->id)
            ->where('payable_id', $pdf->id)
            ->where('type', 'purchase')
            ->first();

        $this->assertNotNull($tx);
        $this->assertSame(Transaction::STATUS_PENDING, $tx->status);
        $this->assertSame(25000, (int) $tx->amount);
        $this->assertNotEmpty($tx->reference_id);
        $this->assertNotEmpty($tx->idempotency_key);
        FakePaymentGateway::seed((string) $tx->reference_id, 25000);

        $verify = $this->postJson('/api/v1/pdf-products/'.$pdf->id.'/verify', [
            'Authority' => $tx->reference_id,
            'Status' => 'OK',
            'ik' => $tx->idempotency_key,
        ]);

        $verify->assertOk();
        $verify->assertJsonPath('data.already_processed', false);

        $this->assertSame(Transaction::STATUS_COMPLETED, $tx->fresh()->status);
        $this->assertTrue(
            PdfPurchase::query()
                ->where('user_id', $user->id)
                ->where('pdf_product_id', $pdf->id)
                ->exists()
        );

        $duplicate = $this->postJson('/api/v1/pdf-products/'.$pdf->id.'/verify', [
            'Authority' => $tx->reference_id,
            'Status' => 'OK',
            'ik' => $tx->idempotency_key,
        ]);

        $duplicate->assertOk();
        $duplicate->assertJsonPath('data.already_processed', true);
        $this->assertSame(1, PdfPurchase::query()->where('user_id', $user->id)->where('pdf_product_id', $pdf->id)->count());
    }

    public function test_pdf_verify_rejects_wrong_product_id(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $pdf = PdfProduct::query()->create([
            'title' => 'فایل A',
            'file_path' => 'pdfs/a.pdf',
            'price' => 10000,
            'is_active' => true,
        ]);
        $otherPdf = PdfProduct::query()->create([
            'title' => 'فایل B',
            'file_path' => 'pdfs/b.pdf',
            'price' => 10000,
            'is_active' => true,
        ]);

        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 10000,
            'type' => 'purchase',
            'gateway' => 'zarinpal',
            'status' => Transaction::STATUS_PENDING,
            'reference_id' => 'AUTH-PDF-TEST',
            'idempotency_key' => 'ik-pdf-test',
            'payable_type' => PdfProduct::class,
            'payable_id' => $pdf->id,
        ]);
        FakePaymentGateway::seed('AUTH-PDF-TEST', 10000);

        $this->postJson('/api/v1/pdf-products/'.$otherPdf->id.'/verify', [
            'Authority' => $tx->reference_id,
            'Status' => 'OK',
            'ik' => $tx->idempotency_key,
        ])->assertStatus(404);
    }
}
