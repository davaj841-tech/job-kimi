<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ResumeStoreRequest;
use App\Http\Requests\Api\ResumeUpdateRequest;
use App\Http\Resources\AiSuggestionResource;
use App\Http\Resources\ResumeCollection;
use App\Http\Resources\ResumeResource;
use App\Repositories\ResumeRepository;
use App\Services\AIService;
use App\Services\ResumePDFService;
use App\Services\ResumeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ResumeController extends BaseController
{
    public function __construct(
        protected ResumeService $resumeService,
        protected ResumeRepository $resumeRepository,
        protected ResumePDFService $resumePDFService,
        protected AIService $aiService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $resumes = $this->resumeRepository->getByUser($request->user());

        return $this->successResponse(new ResumeCollection($resumes));
    }

    public function store(ResumeStoreRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $payload['user_id'] = $request->user()->id;

        $resume = $this->resumeService->create($payload);

        return $this->successResponse(new ResumeResource($resume), 'رزومه ایجاد شد.', 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $resume = $this->resumeRepository->findById($id, $request->user());

        if (! $resume) {
            return $this->errorResponse('رزومه یافت نشد.', 404);
        }

        return $this->successResponse(new ResumeResource($resume));
    }

    public function update(ResumeUpdateRequest $request, int $id): JsonResponse
    {
        $resume = $this->resumeRepository->findById($id, $request->user());

        if (! $resume) {
            return $this->errorResponse('رزومه یافت نشد.', 404);
        }

        $updated = $this->resumeService->update($resume, $request->validated());

        return $this->successResponse(new ResumeResource($updated), 'رزومه به‌روزرسانی شد.');
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $resume = $this->resumeRepository->findById($id, $request->user());

        if (! $resume) {
            return $this->errorResponse('رزومه یافت نشد.', 404);
        }

        $resume->delete();

        return $this->successResponse(null, 'رزومه حذف شد.');
    }

    public function downloadPDF(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $resume = $this->resumeRepository->findById($id, $request->user());

        if (! $resume) {
            return $this->errorResponse('رزومه یافت نشد.', 404);
        }

        if ($this->resumePDFService->needsRegeneration($resume)) {
            $this->resumeService->generatePDF($resume);
            $resume->refresh();
        }

        $path = $this->resumePDFService->absolutePath($resume);

        if (! $path) {
            return $this->errorResponse('فایل PDF یافت نشد.', 404);
        }

        return response()->download($path, 'resume-'.$resume->id.'.pdf', [
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function preview(Request $request, int $id): JsonResponse
    {
        $resume = $this->resumeRepository->findById($id, $request->user());

        if (! $resume) {
            return $this->errorResponse('رزومه یافت نشد.', 404);
        }

        return $this->successResponse([
            'html' => $this->resumePDFService->renderHtml($resume),
            'template_id' => $resume->template_id,
        ]);
    }

    public function updateTemplate(Request $request, int $id): JsonResponse
    {
        $data = $request->validate([
            'template_id' => ['required', 'integer', 'in:1,2,3'],
        ]);

        $resume = $this->resumeRepository->findById($id, $request->user());

        if (! $resume) {
            return $this->errorResponse('رزومه یافت نشد.', 404);
        }

        $updated = $this->resumeService->switchTemplate($resume, (int) $data['template_id']);

        return $this->successResponse([
            'resume' => new ResumeResource($updated),
            'pdf_url' => $updated->pdf_url,
        ], 'قالب رزومه به‌روزرسانی شد.');
    }

    public function aiSuggest(Request $request, int $id): JsonResponse
    {
        $key = 'ai-resume:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return $this->errorResponse('تعداد درخواست‌های AI بیش از حد مجاز است.', 429);
        }
        RateLimiter::hit($key, 3600);

        $resume = $this->resumeRepository->findById($id, $request->user());

        if (! $resume) {
            return $this->errorResponse('رزومه یافت نشد.', 404);
        }

        $targetJob = (string) (data_get($resume->data, 'target_job') ?: $request->input('target_job', 'موقعیت شغلی'));

        try {
            $result = $this->aiService->suggestResumeImprovements($resume->data ?? [], $targetJob);
        } catch (\RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'سقف') ? 429 : 400;

            return $this->errorResponse($e->getMessage(), $code);
        }

        return $this->successResponse([
            'suggestions' => AiSuggestionResource::collection(collect($result['suggestions'])),
            'ai_content_id' => $result['ai_content_id'],
            'note' => 'این پیشنهادها فقط مشورتی هستند و به‌صورت خودکار روی رزومه اعمال نمی‌شوند.',
        ], 'پیشنهادهای بهبود رزومه آماده است.');
    }

    public function aiWriteSummary(Request $request, int $id): JsonResponse
    {
        return $this->runResumeAi($request, $id, function ($resume) use ($request) {
            $context = [
                'title' => (string) ($request->input('title') ?: data_get($resume->data, 'target_job') ?: data_get($resume->data, 'personal.full_name')),
                'target_job' => data_get($resume->data, 'target_job'),
                'experiences' => $request->input('experiences', data_get($resume->data, 'experience', [])),
                'skills' => $request->input('skills', data_get($resume->data, 'skills', [])),
            ];

            return $this->aiService->writeResumeSummary($context);
        }, 'خلاصه حرفه‌ای آماده شد.');
    }

    public function aiEnhanceExperience(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:2000'],
        ]);

        return $this->runResumeAi($request, $id, function () use ($request) {
            return $this->aiService->enhanceExperienceDescription(
                (string) $request->input('title'),
                (string) $request->input('description')
            );
        }, 'توضیحات سابقه شغلی بهبود یافت.');
    }

    public function aiSuggestSkills(Request $request, int $id): JsonResponse
    {
        return $this->runResumeAi($request, $id, function ($resume) use ($request) {
            $context = [
                'title' => (string) ($request->input('title') ?: data_get($resume->data, 'target_job') ?: ''),
                'experiences' => $request->input('experiences', data_get($resume->data, 'experience', [])),
            ];

            return $this->aiService->suggestResumeSkills($context);
        }, 'مهارت‌های پیشنهادی آماده شد.');
    }

    /**
     * @param  callable(\App\Models\Resume): array<string, mixed>  $callback
     */
    protected function runResumeAi(Request $request, int $id, callable $callback, string $message): JsonResponse
    {
        $key = 'ai-resume:'.$request->user()->id;
        if (RateLimiter::tooManyAttempts($key, 10)) {
            return $this->errorResponse('تعداد درخواست‌های AI بیش از حد مجاز است.', 429);
        }
        RateLimiter::hit($key, 3600);

        $resume = $this->resumeRepository->findById($id, $request->user());
        if (! $resume) {
            return $this->errorResponse('رزومه یافت نشد.', 404);
        }

        try {
            $result = $callback($resume);
        } catch (\RuntimeException $e) {
            $code = str_contains($e->getMessage(), 'سقف') ? 429 : 400;

            return $this->errorResponse($e->getMessage(), $code);
        }

        return $this->successResponse($result, $message);
    }
}
