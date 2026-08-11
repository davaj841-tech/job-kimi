<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Resources\AiContentResource;
use App\Jobs\CrawlJobsJob;
use App\Jobs\GenerateQuestionsJob;
use App\Models\AiContent;
use App\Models\Exam;
use App\Models\Setting;
use App\Repositories\AiContentRepository;
use App\Services\AIService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class AIContentController extends BaseController
{
    public function __construct(
        protected AIService $aiService,
        protected AiContentRepository $aiContentRepository
    ) {}

    public function crawlJobs(Request $request): JsonResponse
    {
        if ($limited = $this->throttleAi($request)) {
            return $limited;
        }

        $data = $request->validate([
            'source_urls' => ['required', 'array', 'min:1'],
            'source_urls.*' => ['required', 'url'],
            'keywords' => ['nullable', 'array'],
            'keywords.*' => ['string', 'max:100'],
        ]);

        if (! $this->aiService->isEnabled()) {
            return $this->errorResponse('سرویس هوش مصنوعی غیرفعال است.', 403);
        }

        CrawlJobsJob::dispatch(
            $data['source_urls'],
            $data['keywords'] ?? []
        );

        return $this->successResponse(null, 'Job crawling started. Results will be available in admin panel for review.');
    }

    public function generateBlog(Request $request): JsonResponse
    {
        if ($limited = $this->throttleAi($request)) {
            return $limited;
        }

        $data = $request->validate([
            'topic' => ['required', 'string', 'max:255'],
        ]);

        try {
            $result = $this->aiService->generateAndStoreBlogPost($data['topic'], $request->user()->id);
        } catch (\RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'سقف') ? 429 : 400;

            return $this->errorResponse($e->getMessage(), $code);
        }

        return $this->successResponse([
            'preview' => $result['preview'],
            'ai_content_id' => $result['ai_content_id'],
            'blog_post_id' => $result['blog_post_id'],
        ], 'محتوای بلاگ تولید شد و در انتظار بررسی است.');
    }

    public function generateQuestions(Request $request): JsonResponse
    {
        if ($limited = $this->throttleAi($request)) {
            return $limited;
        }

        $data = $request->validate([
            'subject' => ['required', 'string', 'in:math,literature,islamic,english,chemistry,physics,iq,general'],
            'difficulty' => ['required', 'in:easy,medium,hard'],
            'count' => ['required', 'integer', 'min:1', 'max:20'],
            'exam_id' => ['required', 'integer', 'exists:exams,id'],
        ]);

        if (! $this->aiService->isEnabled()) {
            return $this->errorResponse('سرویس هوش مصنوعی غیرفعال است.', 403);
        }

        try {
            $this->aiService->ensureWithinDailyLimit(
                'exam_question',
                (int) Setting::get('ai_question_limit_per_day', 20)
            );
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 429);
        }

        Exam::query()->findOrFail($data['exam_id']);

        $job = new GenerateQuestionsJob(
            $data['subject'],
            $data['difficulty'],
            (int) $data['count'],
            (int) $data['exam_id']
        );

        dispatch($job);

        return $this->successResponse([
            'job_id' => null,
            'message' => 'Questions are being generated. Check admin panel for approval.',
        ], 'Questions are being generated. Check admin panel for approval.');
    }

    public function stats(): JsonResponse
    {
        $today = $this->aiContentRepository->getTodayCount();
        $limit = (int) Setting::get('ai_daily_limit', 50);

        return $this->successResponse([
            'generated_today' => $today,
            'daily_limit' => $limit,
            'pending' => AiContent::query()->where('status', 'pending')->count(),
            'by_type' => [
                'exam_question' => AiContent::query()->where('type', 'exam_question')->count(),
                'blog_post' => AiContent::query()->where('type', 'blog_post')->count(),
                'job_crawl' => AiContent::query()->where('type', 'job_crawl')->count(),
                'resume_tip' => AiContent::query()->where('type', 'resume_tip')->count(),
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $type = $request->query('type');
        $status = $request->query('status');

        $query = AiContent::query()->latest();

        if ($type) {
            $query->where('type', $type);
        }

        if ($status) {
            $query->where('status', $status);
        }

        $items = $query->paginate((int) $request->query('per_page', 15));

        return $this->successResponse([
            'data' => AiContentResource::collection($items->items()),
            'meta' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $content = AiContent::query()->findOrFail($id);

        return $this->successResponse(new AiContentResource($content));
    }

    public function destroy(int $id): JsonResponse
    {
        $content = AiContent::query()->findOrFail($id);
        $content->delete();

        return $this->successResponse(null, 'محتوا حذف شد.');
    }

    public function approve(Request $request, int $id): JsonResponse
    {
        try {
            $content = $this->aiContentRepository->approve($id, $request->user()->id);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->successResponse(new AiContentResource($content), 'محتوای AI تایید شد.');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        try {
            $content = $this->aiContentRepository->reject($id, $request->user()->id);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->successResponse(new AiContentResource($content), 'محتوای AI رد شد.');
    }

    protected function throttleAi(Request $request): ?JsonResponse
    {
        $key = 'ai-admin:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return $this->errorResponse('تعداد درخواست‌های AI بیش از حد مجاز است.', 429);
        }
        RateLimiter::hit($key, 3600);

        return null;
    }
}
