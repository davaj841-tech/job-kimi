<?php

namespace App\Http\Controllers\Api;

use App\Models\JobPost;
use App\Models\JobPostComment;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobPostCommentController extends BaseController
{
    public function index(int $id): JsonResponse
    {
        JobPost::query()
            ->where('status', 'approved')
            ->findOrFail($id);

        $comments = JobPostComment::query()
            ->with('user:id,name')
            ->where('job_post_id', $id)
            ->where('status', 'approved')
            ->latest()
            ->paginate(20);

        return $this->successResponse([
            'data' => $comments->items(),
            'meta' => [
                'current_page' => $comments->currentPage(),
                'last_page' => $comments->lastPage(),
                'total' => $comments->total(),
            ],
        ]);
    }

    public function store(Request $request, int $id): JsonResponse
    {
        JobPost::query()
            ->where('status', 'approved')
            ->findOrFail($id);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ], [
            'content.required' => 'متن نظر الزامی است.',
            'content.max' => 'نظر نباید بیشتر از ۲۰۰۰ کاراکتر باشد.',
        ]);

        $needsApproval = Setting::get('job_comments_require_approval', 'true') !== 'false';

        $comment = JobPostComment::query()->create([
            'job_post_id' => $id,
            'user_id' => $request->user()->id,
            'content' => strip_tags($data['content']),
            'status' => $needsApproval ? 'pending' : 'approved',
        ]);

        return $this->successResponse(
            $comment->load('user:id,name'),
            $needsApproval ? 'نظر شما پس از تایید نمایش داده می‌شود.' : 'نظر ثبت شد.',
            201
        );
    }
}
