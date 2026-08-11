<?php

namespace App\Http\Controllers\Api;

use App\Models\BlogPost;
use App\Models\Exam;
use App\Models\JobPost;
use App\Models\PdfProduct;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SearchSuggestionController extends BaseController
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return $this->successResponse([
                'exams' => [],
                'job_posts' => [],
                'pdfs' => [],
                'blog_posts' => [],
            ]);
        }

        $cacheKey = 'search_suggest:'.md5(mb_strtolower($q));

        $payload = Cache::remember($cacheKey, 3600, function () use ($q) {
            $like = '%'.$q.'%';

            return [
                'exams' => Exam::query()
                    ->where('status', 'published')
                    ->where('title', 'like', $like)
                    ->limit(5)
                    ->get(['id', 'title', 'slug'])
                    ->map(fn ($e) => ['id' => $e->id, 'title' => $e->title, 'slug' => $e->slug, 'url' => '/exams/'.$e->slug])
                    ->all(),
                'job_posts' => JobPost::query()
                    ->where('status', 'approved')
                    ->where(function ($query) use ($like) {
                        $query->where('title', 'like', $like)->orWhere('company_name', 'like', $like);
                    })
                    ->limit(5)
                    ->get(['id', 'title', 'company_name'])
                    ->map(fn ($j) => [
                        'id' => $j->id,
                        'title' => $j->title,
                        'company_name' => $j->company_name,
                        'url' => '/jobs/'.$j->id,
                    ])
                    ->all(),
                'pdfs' => PdfProduct::query()
                    ->where('is_active', true)
                    ->where('title', 'like', $like)
                    ->limit(5)
                    ->get(['id', 'title'])
                    ->map(fn ($p) => ['id' => $p->id, 'title' => $p->title, 'url' => '/pdfs/'.$p->id])
                    ->all(),
                'blog_posts' => BlogPost::query()
                    ->where('status', 'published')
                    ->where('title', 'like', $like)
                    ->limit(5)
                    ->get(['id', 'title', 'slug'])
                    ->map(fn ($b) => ['id' => $b->id, 'title' => $b->title, 'slug' => $b->slug, 'url' => '/blog/'.$b->slug])
                    ->all(),
            ];
        });

        return $this->successResponse($payload);
    }
}
