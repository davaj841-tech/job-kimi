<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\BlogPost;
use App\Models\Exam;
use App\Models\JobPost;
use App\Models\PdfProduct;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends BaseController
{
    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $type = $request->query('type', 'all');
        $limit = min(10, max(1, (int) $request->query('limit', 5)));

        if (mb_strlen($q) < 2) {
            return $this->successResponse([
                'exams' => [],
                'job_posts' => [],
                'pdfs' => [],
                'blog_posts' => [],
                'popular' => $this->popularSearches(),
            ]);
        }

        $like = '%'.$q.'%';

        $exams = [];
        $jobs = [];
        $pdfs = [];
        $blogs = [];

        if (in_array($type, ['all', 'exams'], true)) {
            $exams = Exam::query()
                ->where('status', 'published')
                ->where(function ($query) use ($like) {
                    $query->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like);
                })
                ->limit($limit)
                ->get(['id', 'title', 'slug'])
                ->map(fn ($e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'slug' => $e->slug,
                    'type' => 'exam',
                    'url' => '/exams/'.$e->slug,
                ]);
        }

        if (in_array($type, ['all', 'jobs'], true)) {
            $jobs = JobPost::query()
                ->where('status', 'approved')
                ->where(function ($query) use ($like) {
                    $query->where('title', 'like', $like)
                        ->orWhere('company_name', 'like', $like)
                        ->orWhere('description', 'like', $like);
                })
                ->limit($limit)
                ->get(['id', 'title', 'company_name'])
                ->map(fn ($j) => [
                    'id' => $j->id,
                    'title' => $j->title,
                    'company_name' => $j->company_name,
                    'type' => 'job',
                    'url' => '/jobs/'.$j->id,
                ]);
        }

        if (in_array($type, ['all', 'pdfs'], true)) {
            $pdfs = PdfProduct::query()
                ->where('is_active', true)
                ->where(function ($query) use ($like) {
                    $query->where('title', 'like', $like)
                        ->orWhere('description', 'like', $like);
                })
                ->limit($limit)
                ->get(['id', 'title'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'type' => 'pdf',
                    'url' => '/pdfs/'.$p->id,
                ]);
        }

        if (in_array($type, ['all', 'blog'], true)) {
            $blogs = BlogPost::query()
                ->where('status', 'published')
                ->where(function ($query) use ($like) {
                    $query->where('title', 'like', $like)
                        ->orWhere('content', 'like', $like)
                        ->orWhere('excerpt', 'like', $like);
                })
                ->limit($limit)
                ->get(['id', 'title', 'slug'])
                ->map(fn ($b) => [
                    'id' => $b->id,
                    'title' => $b->title,
                    'slug' => $b->slug,
                    'type' => 'blog',
                    'url' => '/blog/'.$b->slug,
                ]);
        }

        return $this->successResponse([
            'exams' => $exams,
            'job_posts' => $jobs,
            'pdfs' => $pdfs,
            'blog_posts' => $blogs,
            'popular' => $this->popularSearches(),
        ]);
    }

    protected function popularSearches(): array
    {
        $raw = Setting::get('popular_searches', '[]');
        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : ['آزمون استخدامی', 'بانک', 'رزومه', 'PDF'];
    }
}
