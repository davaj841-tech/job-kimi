<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\JobPostStoreRequest;
use App\Http\Resources\JobPostCollection;
use App\Http\Resources\JobPostResource;
use App\Models\JobPost;
use App\Repositories\JobPostRepository;
use App\Services\JobPostService;
use App\Services\Seo\SeoManager;
use App\Support\HtmlSanitizer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobPostController extends BaseController
{
    public function __construct(
        protected JobPostRepository $jobPostRepository,
        protected JobPostService $jobPostService,
        protected SeoManager $seoManager
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'province',
            'city',
            'job_classification_id',
            'is_featured',
            'search',
            'per_page',
            'employment_type',
            'sort',
        ]);
        $posts = $this->jobPostService->getPublicList($filters);

        return $this->successResponse(new JobPostCollection($posts));
    }

    public function filters(): JsonResponse
    {
        return $this->successResponse($this->jobPostRepository->getFilterOptions());
    }

    public function show(int $id): JsonResponse
    {
        $jobPost = $this->jobPostRepository->findApproved($id);

        if (! $jobPost) {
            return $this->errorResponse('آگهی یافت نشد.', 404);
        }

        $this->jobPostService->incrementViews($id);
        $jobPost->refresh();
        $jobPost->load([
            'classification',
            'attachments',
            'exams:id,job_post_id,title,slug,is_free,price,duration_minutes,total_questions,job_classification_id',
            'pdfProducts:id,job_post_id,title,price,thumbnail,job_classification_id',
        ]);

        $catalog = $this->jobPostService->relatedCatalog($jobPost);
        $jobPost->catalog_exams = $catalog['exams'];
        $jobPost->catalog_pdfs = $catalog['pdfs'];

        $data = (new JobPostResource($jobPost))->resolve();
        $breadcrumbs = [
            ['name' => 'خانه', 'url' => url('/')],
            ['name' => 'آگهی‌ها', 'url' => url('/jobs')],
            ['name' => $jobPost->title, 'url' => url('/jobs/'.$jobPost->getKey())],
        ];
        $seo = $this->seoManager->buildPublicPayload($jobPost, $breadcrumbs);
        $data['seo'] = $seo;
        $data['schema'] = $seo['schema'];
        if ($jobPost->seo_tag) {
            $data['seo']['tag'] = $jobPost->seo_tag;
            $data['seo']['keywords'] = str_replace('_', ' ', $jobPost->seo_tag);
        }

        return $this->successResponse($data);
    }

    public function submit(JobPostStoreRequest $request): JsonResponse
    {
        $user = $request->user();

        $recentCount = JobPost::query()
            ->where('created_by', $user->id)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($recentCount >= 3) {
            return $this->errorResponse('شما حداکثر ۳ آگهی در روز می‌توانید ثبت کنید.', 429);
        }

        $data = $request->validated();
        unset($data['exam_ids'], $data['pdf_ids']);

        $duplicate = JobPost::query()
            ->where('created_by', $user->id)
            ->where('title', $data['title'])
            ->whereIn('status', ['pending', 'approved'])
            ->exists();

        if ($duplicate) {
            return $this->errorResponse('آگهی با این عنوان قبلاً توسط شما ثبت شده است.', 422);
        }

        $data['status'] = 'pending';
        $data['created_by'] = $user->id;
        $data['is_featured'] = false;
        $data['description'] = HtmlSanitizer::clean($data['description'] ?? '');
        if (array_key_exists('requirements', $data)) {
            $data['requirements'] = HtmlSanitizer::clean($data['requirements']);
        }

        $jobPost = $this->jobPostService->create($data, []);

        return $this->successResponse(
            new JobPostResource($jobPost),
            'آگهی شما ثبت شد و پس از بررسی منتشر خواهد شد.',
            201
        );
    }
}
