<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\JobPostComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobPostCommentAdminController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->input('per_page', 20), 1), 100);

        $query = JobPostComment::query()
            ->with([
                'user:id,name,mobile',
                'jobPost:id,title,status',
            ])
            ->latest();

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        if ($request->filled('job_post_id')) {
            $query->where('job_post_id', (int) $request->input('job_post_id'));
        }

        if ($request->filled('search')) {
            $term = '%'.trim((string) $request->input('search')).'%';
            $query->where(function ($q) use ($term) {
                $q->where('content', 'like', $term)
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $term))
                    ->orWhereHas('jobPost', fn ($j) => $j->where('title', 'like', $term));
            });
        }

        $page = $query->paginate($perPage);

        return $this->successResponse([
            'data' => $page->items(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'from' => $page->firstItem(),
                'to' => $page->lastItem(),
            ],
        ]);
    }

    public function approve(int $id): JsonResponse
    {
        $comment = JobPostComment::query()->findOrFail($id);
        $comment->update(['status' => 'approved']);

        return $this->successResponse(
            $comment->fresh()->load(['user:id,name', 'jobPost:id,title']),
            'نظر تایید شد.'
        );
    }

    public function reject(int $id): JsonResponse
    {
        $comment = JobPostComment::query()->findOrFail($id);
        $comment->update(['status' => 'rejected']);

        return $this->successResponse(
            $comment->fresh()->load(['user:id,name', 'jobPost:id,title']),
            'نظر رد شد.'
        );
    }

    public function destroy(int $id): JsonResponse
    {
        JobPostComment::query()->findOrFail($id)->delete();

        return $this->successResponse(null, 'نظر حذف شد.');
    }
}
