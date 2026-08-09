<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\JobPostStoreRequest;
use App\Http\Resources\JobPostCollection;
use App\Http\Resources\JobPostResource;
use App\Repositories\JobPostRepository;
use App\Services\JobPostService;
use App\Services\SEOService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobPostController extends BaseController
{
    public function __construct(
        protected JobPostRepository $jobPostRepository,
        protected JobPostService $jobPostService,
        protected SEOService $seoService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['province', 'city', 'job_classification_id', 'is_featured', 'search', 'per_page']);
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
        $data['schema'] = $this->seoService->generateJobPostSchema($jobPost);
        if ($jobPost->seo_tag) {
            $data['seo'] = [
                'tag' => $jobPost->seo_tag,
                'keywords' => str_replace('_', ' ', $jobPost->seo_tag),
            ];
        }

        return $this->successResponse($data);
    }

    public function submit(JobPostStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = 'pending';
        $data['created_by'] = $request->user()->id;
        $data['is_featured'] = false;

        $jobPost = $this->jobPostService->create($data, []);

        return $this->successResponse(
            new JobPostResource($jobPost),
            'آگهی شما ثبت شد و پس از بررسی منتشر خواهد شد.',
            201
        );
    }
}
