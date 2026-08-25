<?php

namespace Tests\Feature\Security;

use App\Filament\Resources\UserResource;
use App\Models\Exam;
use App\Models\JobClassification;
use App\Models\JobPost;
use App\Models\PdfProduct;
use App\Models\PdfPurchase;
use App\Models\Resume;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AIService;
use App\Services\FeatureFlagService;
use App\Services\PDFProductService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use ReflectionMethod;
use Tests\TestCase;

class SecurityAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_user_cannot_update_exam(): void
    {
        $user = User::factory()->create(['role' => 'jobseeker', 'status' => 'active']);
        $exam = Exam::factory()->create(['title' => 'آزمون اصلی']);
        Sanctum::actingAs($user);

        $this->putJson('/api/v1/exams/'.$exam->id, ['title' => 'هک شده'])
            ->assertForbidden();

        $this->assertSame('آزمون اصلی', $exam->fresh()->title);
    }

    public function test_operator_cannot_access_admin_only_routes(): void
    {
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'operator_permissions' => ['exams', 'users', 'tickets'],
        ]);
        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/admin/settings')->assertForbidden();
        $this->getJson('/api/v1/admin/backups')->assertForbidden();
        $this->getJson('/api/v1/admin/analytics/visits')->assertForbidden();
        $this->getJson('/api/v1/admin/crawler-runs')->assertForbidden();
    }

    public function test_ticket_idor_does_not_reveal_existence(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);
        $ticket = Ticket::query()->create([
            'user_id' => $owner->id,
            'subject' => 'خصوصی',
            'message' => 'متن',
        ]);
        Sanctum::actingAs($other);

        $this->getJson('/api/v1/tickets/'.$ticket->id)->assertNotFound();
    }

    public function test_resume_idor_is_denied(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);
        $resume = Resume::query()->create([
            'user_id' => $owner->id,
            'title' => 'رزومه من',
            'template_id' => 1,
            'data' => [],
        ]);
        Sanctum::actingAs($other);

        $this->getJson('/api/v1/resumes/'.$resume->id)->assertNotFound();
    }

    public function test_invoice_idor_is_denied(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $other = User::factory()->create(['status' => 'active']);
        $tx = Transaction::query()->create([
            'user_id' => $owner->id,
            'amount' => 10000,
            'type' => 'deposit',
            'status' => 'success',
        ]);
        Sanctum::actingAs($other);

        $this->getJson('/api/v1/transactions/'.$tx->id.'/invoice')->assertNotFound();
    }

    public function test_profile_rejects_role_and_wallet_mass_assignment(): void
    {
        $user = User::factory()->create([
            'role' => 'jobseeker',
            'status' => 'active',
            'wallet_balance' => 0,
        ]);
        $token = $user->createToken('api')->plainTextToken;

        $this->withToken($token)->putJson('/api/v1/auth/profile', [
            'name' => 'نام امن',
            'role' => 'admin',
            'status' => 'active',
            'wallet_balance' => 999999,
        ])->assertOk();

        $user->refresh();
        $this->assertSame('jobseeker', $user->role);
        $this->assertSame(0, (int) $user->wallet_balance);
        $this->assertSame('نام امن', $user->name);
    }

    public function test_contact_requires_captcha(): void
    {
        $this->postJson('/api/v1/contact', [
            'name' => 'علی',
            'mobile' => '09123456789',
            'email' => 'ali@example.com',
            'subject' => 'support',
            'message' => 'سلام',
        ])->assertStatus(422);
    }

    public function test_wallet_charge_rejects_excessive_amount(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        app(FeatureFlagService::class)->enable('wallet');
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/wallet/charge', [
            'amount' => 9_000_000_000,
            'gateway' => 'zarinpal',
        ])->assertStatus(422);
    }

    public function test_payment_callback_requires_authority(): void
    {
        $this->getJson('/api/v1/wallet/verify')->assertStatus(422);
    }

    public function test_operator_cannot_mutate_admin_user(): void
    {
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'operator_permissions' => ['users'],
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'status' => 'active',
            'mobile' => '09121111111',
        ]);
        Sanctum::actingAs($operator);

        $this->putJson('/api/v1/admin/users/'.$admin->id, [
            'name' => 'هک‌شده',
            'mobile' => '09121111111',
            'role' => 'jobseeker',
            'status' => 'blocked',
        ])->assertForbidden();

        $this->putJson('/api/v1/admin/users/'.$admin->id.'/status', [
            'status' => 'blocked',
        ])->assertForbidden();

        $this->deleteJson('/api/v1/admin/users/'.$admin->id)->assertForbidden();

        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertSame('active', $admin->fresh()->status ?? 'active');
        $this->assertNotNull($admin->fresh());
    }

    public function test_filament_user_resource_is_super_admin_only(): void
    {
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'operator_permissions' => ['users'],
        ]);
        $admin = User::factory()->create(['role' => 'admin', 'status' => 'active']);
        $super = User::factory()->create(['role' => 'super_admin', 'status' => 'active']);

        $this->actingAs($operator);
        $this->assertFalse(UserResource::canViewAny());

        $this->actingAs($admin);
        $this->assertFalse(UserResource::canViewAny());

        $this->actingAs($super);
        $this->assertTrue(UserResource::canViewAny());
    }

    public function test_operator_without_questions_cannot_use_user_question_api(): void
    {
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'operator_permissions' => ['exams'],
        ]);
        Sanctum::actingAs($operator);

        $this->postJson('/api/v1/questions', [])->assertForbidden();
    }

    public function test_operator_without_tickets_cannot_read_other_ticket(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'operator_permissions' => ['users'],
        ]);
        $ticket = Ticket::query()->create([
            'user_id' => $owner->id,
            'subject' => 'خصوصی',
            'message' => 'متن',
        ]);
        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/tickets/'.$ticket->id)->assertNotFound();
    }

    public function test_operator_without_transactions_cannot_download_invoice(): void
    {
        $owner = User::factory()->create(['status' => 'active']);
        $operator = User::factory()->create([
            'role' => 'operator',
            'status' => 'active',
            'operator_permissions' => ['users'],
        ]);
        $tx = Transaction::query()->create([
            'user_id' => $owner->id,
            'amount' => 10000,
            'type' => 'deposit',
            'status' => 'success',
        ]);
        Sanctum::actingAs($operator);

        $this->getJson('/api/v1/transactions/'.$tx->id.'/invoice')->assertNotFound();
    }

    public function test_job_submit_strips_catalog_and_featured_fields(): void
    {
        $user = User::factory()->create(['role' => 'employer', 'status' => 'active']);
        $classification = JobClassification::query()->create([
            'name' => 'طبقه امنیتی '.uniqid(),
        ]);
        $exam = Exam::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/job-posts/submit', [
            'title' => 'آگهی تست امنیت',
            'job_classification_id' => $classification->id,
            'description' => '<script>alert(1)</script><p>شرح آگهی</p>',
            'registration_deadline' => now()->addDay()->toDateString(),
            'exam_ids' => [$exam->id],
            'status' => 'approved',
            'is_featured' => true,
        ])->assertCreated();

        $post = JobPost::query()->latest('id')->first();
        $this->assertNotNull($post);
        $this->assertSame('pending', $post->status);
        $this->assertFalse((bool) $post->is_featured);
        $this->assertEmpty($post->exam_ids ?? []);
        $this->assertStringNotContainsString('script', strtolower((string) $post->description));
    }

    public function test_ai_crawler_does_not_fetch_private_urls(): void
    {
        $service = app(AIService::class);
        $method = new ReflectionMethod(AIService::class, 'fetchPageContent');
        $method->setAccessible(true);

        $this->assertSame('', $method->invoke($service, 'http://127.0.0.1/'));
        $this->assertSame('', $method->invoke($service, 'http://169.254.169.254/latest/meta-data/'));
    }

    public function test_resume_photo_rejects_absolute_env_path(): void
    {
        $resume = new Resume([
            'data' => ['personal' => ['photo' => base_path('.env')]],
        ]);

        $this->assertNull($resume->photoAbsolutePath());
    }

    public function test_pdf_purchase_verify_is_idempotent(): void
    {
        $user = User::factory()->create(['status' => 'active']);
        $pdf = PdfProduct::query()->create([
            'title' => 'فایل تست',
            'file_path' => 'pdfs/test.pdf',
            'price' => 5000,
            'is_active' => true,
        ]);
        $tx = Transaction::query()->create([
            'user_id' => $user->id,
            'amount' => 5000,
            'type' => 'purchase',
            'status' => Transaction::STATUS_PENDING,
            'payable_type' => PdfProduct::class,
            'payable_id' => $pdf->id,
        ]);

        $service = app(PDFProductService::class);
        $this->assertTrue($service->completeZarinPalPurchase($tx));
        $this->assertTrue($service->completeZarinPalPurchase($tx->fresh()));

        $this->assertSame(1, PdfPurchase::query()->where('user_id', $user->id)->where('pdf_product_id', $pdf->id)->count());
        $this->assertSame(Transaction::STATUS_COMPLETED, $tx->fresh()->status);
        $this->assertNotEmpty($tx->fresh()->invoice_pdf);
        $this->assertStringNotContainsString('/storage/', (string) $tx->fresh()->invoice_pdf);
    }
}
