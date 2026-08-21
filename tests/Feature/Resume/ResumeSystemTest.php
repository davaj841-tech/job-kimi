<?php

declare(strict_types=1);

namespace Tests\Feature\Resume;

use App\Models\Resume;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ResumeSystemTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private User $otherUser;

    private array $validData;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'jobseeker']);
        $this->otherUser = User::factory()->create(['role' => 'jobseeker']);

        $this->validData = [
            'template_id' => 1,
            'data' => [
                'personal' => [
                    'full_name' => 'علی محمدی',
                    'mobile' => '09121234567',
                    'email' => 'ali@example.com',
                ],
                'education' => [
                    ['degree' => 'کارشناسی', 'field' => 'مهندسی نرم‌افزار', 'university' => 'دانشگاه تهران', 'start_year' => 1396, 'end_year' => 1400],
                ],
                'experience' => [
                    ['title' => 'برنامه‌نویس', 'company' => 'شرکت نرم‌افزاری', 'start_date' => '1400/01', 'end_date' => '1402/06', 'description' => 'توسعه وب'],
                ],
                'skills' => [
                    ['name' => 'PHP', 'level' => 'حرفه‌ای'],
                    ['name' => 'Laravel', 'level' => 'حرفه‌ای'],
                ],
                'languages' => [
                    ['name' => 'انگلیسی', 'level' => 'B2'],
                ],
                'summary' => 'برنامه‌نویس وب با ۵ سال تجربه',
                'target_job' => 'مهندس نرم‌افزار',
            ],
        ];
    }

    // ─── Requirement 1: User only manages own resume ───

    public function test_user_only_sees_own_resumes(): void
    {
        Resume::create(['user_id' => $this->user->id, 'template_id' => 1, 'title' => 'Mine', 'data' => []]);
        Resume::create(['user_id' => $this->otherUser->id, 'template_id' => 1, 'title' => 'Theirs', 'data' => []]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/resumes');
        $response->assertOk();

        $titles = collect($response->json('data'))->pluck('title');
        $this->assertContains('Mine', $titles);
        $this->assertNotContains('Theirs', $titles);
    }

    public function test_user_cannot_access_others_resume(): void
    {
        $other = Resume::create(['user_id' => $this->otherUser->id, 'template_id' => 1, 'title' => 'X', 'data' => []]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/resumes/{$other->id}");
        $response->assertStatus(404);
    }

    // ─── Requirement 2: Authorization ───

    public function test_unauthenticated_cannot_access_resumes(): void
    {
        $this->getJson('/api/v1/resumes')->assertUnauthorized();
    }

    public function test_cannot_update_others_resume(): void
    {
        $other = Resume::create(['user_id' => $this->otherUser->id, 'template_id' => 1, 'title' => 'X', 'data' => ['personal' => []]]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/resumes/{$other->id}", [
            'data' => ['personal' => ['full_name' => 'Hacked']],
        ]);
        $response->assertStatus(404);
    }

    public function test_cannot_delete_others_resume(): void
    {
        $other = Resume::create(['user_id' => $this->otherUser->id, 'template_id' => 1, 'title' => 'X', 'data' => []]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/resumes/{$other->id}");
        $response->assertStatus(404);
    }

    // ─── Requirement 3: Autosave (partial update) ───

    public function test_partial_update_works(): void
    {
        $resume = Resume::create(['user_id' => $this->user->id, 'template_id' => 1, 'title' => 'Draft', 'data' => ['personal' => ['full_name' => 'Ali']]]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/resumes/{$resume->id}", [
            'title' => 'Updated Draft',
        ]);
        $response->assertOk();

        $resume->refresh();
        $this->assertEquals('Updated Draft', $resume->title);
        $this->assertEquals('Ali', data_get($resume->data, 'personal.full_name'));
    }

    // ─── Requirement 4: Persian validation ───

    public function test_persian_degree_validation(): void
    {
        $data = $this->validData;
        $data['data']['education'][0]['degree'] = 'Bachelor';

        $response = $this->actingAs($this->user)->postJson('/api/v1/resumes', $data);
        $response->assertStatus(422);
    }

    public function test_persian_skill_level_validation(): void
    {
        $data = $this->validData;
        $data['data']['skills'][0]['level'] = 'expert';

        $response = $this->actingAs($this->user)->postJson('/api/v1/resumes', $data);
        $response->assertStatus(422);
    }

    public function test_gpa_max_20(): void
    {
        $data = $this->validData;
        $data['data']['education'][0]['gpa'] = '21.0';

        $response = $this->actingAs($this->user)->postJson('/api/v1/resumes', $data);
        $response->assertStatus(422);
    }

    public function test_valid_data_passes(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/resumes', $this->validData);
        $response->assertStatus(201);
    }

    // ─── Requirement 5 & 6: PDF RTL/UTF-8 + Persian font ───

    public function test_pdf_html_contains_persian_support(): void
    {
        $resume = Resume::create([
            'user_id' => $this->user->id,
            'template_id' => 1,
            'title' => 'PDF Test',
            'data' => $this->validData['data'],
        ]);

        $service = app(\App\Services\ResumePDFService::class);
        $html = $service->renderHtml($resume);

        // DomPDF uses visual LTR with text-align:right for Persian (reshaper handles RTL)
        $this->assertStringContainsString('text-align: right', $html);
        $this->assertStringContainsString('vazirmatn', $html);
        // Verify Persian text was reshaped (presentation forms)
        $this->assertStringNotContainsString('علی', $html, 'Text should be reshaped to presentation forms');
    }

    // ─── Requirement 7: Sensitive info not exposed ───

    public function test_pdf_path_not_exposed_in_response(): void
    {
        $resume = Resume::create([
            'user_id' => $this->user->id,
            'template_id' => 1,
            'title' => 'Test',
            'data' => ['personal' => []],
            'pdf_path' => 'resumes/1/secret.pdf',
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/resumes/{$resume->id}");
        $response->assertOk();
        $response->assertJsonMissing(['pdf_path' => 'resumes/1/secret.pdf']);
        $response->assertJsonPath('data.has_pdf', true);
    }

    // ─── Requirement 8: Templates ───

    public function test_template_switch(): void
    {
        $resume = Resume::create([
            'user_id' => $this->user->id,
            'template_id' => 1,
            'title' => 'Tpl Test',
            'data' => $this->validData['data'],
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/resumes/{$resume->id}/template", [
            'template_id' => 5,
        ]);
        $response->assertOk();

        $resume->refresh();
        $this->assertEquals(5, $resume->template_id);
    }

    public function test_template_id_clamped(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/resumes', array_merge($this->validData, [
            'template_id' => 99,
        ]));
        $response->assertStatus(422);
    }

    // ─── Requirement 9: Download authorization ───

    public function test_cannot_download_others_pdf(): void
    {
        $other = Resume::create([
            'user_id' => $this->otherUser->id,
            'template_id' => 1,
            'title' => 'X',
            'data' => ['personal' => []],
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/resumes/{$other->id}/pdf");
        $response->assertStatus(404);
    }

    // ─── CRUD completeness ───

    public function test_create_resume(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/resumes', $this->validData);
        $response->assertStatus(201);
        $response->assertJsonPath('data.template_id', 1);
    }

    public function test_update_resume(): void
    {
        $resume = Resume::create([
            'user_id' => $this->user->id,
            'template_id' => 1,
            'title' => 'Old',
            'data' => $this->validData['data'],
        ]);

        $response = $this->actingAs($this->user)->putJson("/api/v1/resumes/{$resume->id}", [
            'data' => array_merge($this->validData['data'], ['summary' => 'متن جدید']),
        ]);
        $response->assertOk();

        $resume->refresh();
        $this->assertEquals('متن جدید', data_get($resume->data, 'summary'));
    }

    public function test_delete_resume(): void
    {
        $resume = Resume::create([
            'user_id' => $this->user->id,
            'template_id' => 1,
            'title' => 'To Delete',
            'data' => [],
        ]);

        $response = $this->actingAs($this->user)->deleteJson("/api/v1/resumes/{$resume->id}");
        $response->assertOk();

        $this->assertSoftDeleted('resumes', ['id' => $resume->id]);
    }

    public function test_list_resumes(): void
    {
        Resume::create(['user_id' => $this->user->id, 'template_id' => 1, 'title' => 'R1', 'data' => []]);
        Resume::create(['user_id' => $this->user->id, 'template_id' => 2, 'title' => 'R2', 'data' => []]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/resumes');
        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }
}
