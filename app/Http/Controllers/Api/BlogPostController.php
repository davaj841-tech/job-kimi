<?php

namespace App\Http\Controllers\Api;

use App\Http\Resources\BlogPostCollection;
use App\Http\Resources\BlogPostResource;
use App\Repositories\BlogPostRepository;
use App\Services\BlogPostService;
use App\Services\SEOService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogPostController extends BaseController
{
    public function __construct(
        protected BlogPostRepository $blogPostRepository,
        protected BlogPostService $blogPostService,
        protected SEOService $seoService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['search', 'category', 'per_page']);
        $posts = $this->blogPostService->getPublishedList($filters);

        return $this->successResponse(new BlogPostCollection($posts));
    }

    public function show(string $slug): JsonResponse
    {
        $post = $this->blogPostRepository->findBySlug($slug);

        if (! $post) {
            return $this->errorResponse('پست یافت نشد.', 404);
        }

        $nav = $this->blogPostService->getPrevNext($post);
        $post->prev_post = $nav['prev_post'];
        $post->next_post = $nav['next_post'];

        $catalog = $this->blogPostService->relatedCatalog($post);
        $post->catalog_exams = $catalog['exams'];
        $post->catalog_pdfs = $catalog['pdfs'];

        $data = (new BlogPostResource($post))->resolve();
        $data['schema'] = $this->seoService->generateBlogSchema($post);

        return $this->successResponse($data);
    }
}
