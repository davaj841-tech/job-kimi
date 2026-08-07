<?php

namespace App\Jobs;

use App\Services\AIService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateQuestionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public string $subject,
        public string $difficulty,
        public int $count,
        public int $examId
    ) {}

    public function handle(AIService $aiService): void
    {
        try {
            $result = $aiService->generateQuestions(
                $this->subject,
                $this->difficulty,
                $this->count,
                $this->examId
            );

            Log::info('GenerateQuestionsJob completed', [
                'exam_id' => $this->examId,
                'ai_content_id' => $result['ai_content_id'],
                'count' => $result['count'],
            ]);
        } catch (\Throwable $e) {
            Log::error('GenerateQuestionsJob failed', [
                'exam_id' => $this->examId,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
