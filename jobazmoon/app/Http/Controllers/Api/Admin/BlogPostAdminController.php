<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\BlogPostStoreRequest;
use App\Http\Resources\BlogPostCollection;
use App\Http\Resources\BlogPostResource;
use App\Repositories\BlogPostRepository;
use App\Services\BlogPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BlogPostAdminController extends BaseController
{
    public function __construct(
        protected BlogPostRepository $blogPostRepository,
        protected BlogPostService $blogPostService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['status', 'category', 'search', 'per_page']);
        $posts = $this->blogPostRepository->getAdminList($filters);

        return $this->successResponse(new BlogPostCollection($posts));
    }

    public function show(int $id): JsonResponse
    {
        $post = $this->blogPostRepository->findById($id);

        if (! $post) {
            return $this->errorResponse('پست یافت نشد.', 404);
        }

        return $this->successResponse(new BlogPostResource($post));
    }

    public function store(BlogPostStoreRequest $request): JsonResponse
    {
        $data = $request->validated();

        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image')->store('blog', 'public');
        }

        $data['created_by'] = $request->user()->id;
        $data['status'] = $data['status'] ?? 'draft';

        $post = $this->blogPostService->create($data);

        return $this->successResponse(new BlogPostResource($post->load('creator')), 'پست ایجاد شد.', 201);
    }

    public function update(BlogPostStoreRequest $request, int $id): JsonResponse
    {
        $post = $this->blogPostRepository->findById($id);

        if (! $post) {
            return $this->errorResponse('پست یافت نشد.', 404);
        }

        $data = $request->validated();

        if ($request->hasFile('featured_image')) {
            $data['featured_image'] = $request->file('featured_image')->store('blog', 'public');
        }

        $updated = $this->blogPostService->update($post, $data);

        return $this->successResponse(new BlogPostResource($updated), 'پست به‌روزرسانی شد.');
    }

    public function destroy(int $id): JsonResponse
    {
        $post = $this->blogPostRepository->findById($id);

        if (! $post) {
            return $this->errorResponse('پست یافت نشد.', 404);
        }

        $post->delete();

        return $this->successResponse(null, 'پست حذف شد.');
    }

    public function publish(int $id): JsonResponse
    {
        try {
            $post = $this->blogPostService->publish($id);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->successResponse(new BlogPostResource($post), 'پست منتشر شد.');
    }

    public function draft(int $id): JsonResponse
    {
        try {
            $post = $this->blogPostService->draft($id);
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->successResponse(new BlogPostResource($post), 'پست به پیش‌نویس تغییر کرد.');
    }
}
