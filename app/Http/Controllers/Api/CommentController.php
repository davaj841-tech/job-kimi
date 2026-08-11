<?php

namespace App\Http\Controllers\Api;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommentController extends BaseController
{
    public function index(int $id): JsonResponse
    {
        BlogPost::query()->findOrFail($id);

        $comments = BlogComment::query()
            ->with('user:id,name')
            ->where('blog_post_id', $id)
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
        BlogPost::query()->findOrFail($id);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
        ]);

        $needsApproval = Setting::get('blog_comments_require_approval', 'true') !== 'false';

        $comment = BlogComment::query()->create([
            'blog_post_id' => $id,
            'user_id' => $request->user()->id,
            'content' => $data['content'],
            'status' => $needsApproval ? 'pending' : 'approved',
        ]);

        return $this->successResponse(
            $comment->load('user:id,name'),
            $needsApproval ? 'نظر شما پس از تایید نمایش داده می‌شود.' : 'نظر ثبت شد.',
            201
        );
    }
}
