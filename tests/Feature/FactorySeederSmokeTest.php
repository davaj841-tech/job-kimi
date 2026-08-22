<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Resume;
use App\Models\Ticket;
use App\Models\Transaction;
use App\Models\User;
use Database\Factories\ExamCategoryFactory;
use Database\Factories\PaymentFactory;
use Database\Factories\WalletTransactionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class FactorySeederSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_core_factories_create_expected_records(): void
    {
        ExamCategoryFactory::ensureStandardCategories();

        $users = User::factory()->count(3)->create();
        $this->assertCount(3, $users);
        $this->assertMatchesRegularExpression('/^09\d{9}$/', $users->first()->mobile);

        $exam = Exam::factory()->withQuestions(5)->create([
            'created_by' => $users->first()->id,
        ]);
        $this->assertSame(5, $exam->questions()->count());
        $this->assertDatabaseHas('exams', ['id' => $exam->id]);

        Question::factory()->count(2)->create(['exam_id' => $exam->id]);
        $this->assertSame(7, Question::query()->where('exam_id', $exam->id)->count());

        PaymentFactory::new()->count(2)->create(['user_id' => $users->first()->id]);
        WalletTransactionFactory::new()->charge()->create(['user_id' => $users->first()->id]);
        $this->assertGreaterThanOrEqual(3, Transaction::query()->count());

        Resume::factory()->create(['user_id' => $users->first()->id]);
        Ticket::factory()->create(['user_id' => $users->first()->id]);
        $this->assertDatabaseHas('resumes', ['user_id' => $users->first()->id]);
        $this->assertDatabaseHas('tickets', ['user_id' => $users->first()->id]);
    }
}
