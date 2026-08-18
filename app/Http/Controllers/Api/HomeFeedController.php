<?php

namespace App\Http\Controllers\Api;

use App\Models\BlogPost;
use App\Models\Exam;
use App\Models\GeneratedContent;
use App\Models\JobClassification;
use App\Models\JobPost;
use App\Models\PdfProduct;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class HomeFeedController extends BaseController
{
    public function __invoke(): JsonResponse
    {
        $payload = Cache::remember('home_feed_v3', 45, function () {
            $jobs = JobPost::query()
                ->with(['classification:id,name,parent_id'])
                ->where('status', 'approved')
                ->latest('id')
                ->limit(80)
                ->get(['id', 'title', 'company_name', 'province', 'city', 'status', 'job_classification_id', 'created_at', 'registration_deadline']);

            $exams = Exam::query()
                ->where('status', 'published')
                ->latest('id')
                ->limit(12)
                ->get();

            $files = PdfProduct::query()
                ->where('is_active', true)
                ->latest('id')
                ->limit(12)
                ->get(['id', 'title', 'price', 'category', 'thumbnail', 'is_active', 'created_at']);

            $blog = BlogPost::query()
                ->with('creator:id,name')
                ->where('status', 'published')
                ->latest('id')
                ->limit(8)
                ->get(['id', 'title', 'slug', 'excerpt', 'category', 'status', 'created_by', 'created_at']);

            $articles = GeneratedContent::query()
                ->published()
                ->latest('published_at')
                ->limit(8)
                ->get();

            $plans = SubscriptionPlan::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->get(['id', 'name', 'price', 'duration_days', 'features', 'is_active']);

            $classifications = JobClassification::query()
                ->with(['children' => fn ($q) => $q->where('is_active', true)->select('id', 'parent_id', 'name')])
                ->where('is_active', true)
                ->whereNull('parent_id')
                ->orderBy('sort_order')
                ->orderBy('id')
                ->limit(24)
                ->get(['id', 'name', 'parent_id']);

            return [
                'jobs' => $jobs->map(function (JobPost $j) {
                    return [
                        'id' => $j->id,
                        'title' => $j->title,
                        'company_name' => $j->company_name,
                        'province' => $j->province,
                        'city' => $j->city,
                        'job_classification_id' => $j->job_classification_id,
                        'classification_parent_id' => $j->classification?->parent_id,
                        'classification_name' => $j->classification?->name,
                        'created_at' => $j->created_at?->toIso8601String(),
                        'registration_deadline' => $j->registration_deadline?->toIso8601String(),
                    ];
                })->values()->all(),
                'exams' => $exams->map(fn (Exam $e) => [
                    'id' => $e->id,
                    'title' => $e->title,
                    'slug' => $e->slug,
                    'duration_minutes' => $e->duration_minutes,
                    'total_questions' => $e->total_questions,
                    'is_free' => (bool) $e->is_free,
                    'price' => $e->price,
                    'avg_rating' => (float) ($e->getAttribute('avg_rating') ?? 0),
                    'ratings_count' => (int) ($e->getAttribute('ratings_count') ?? 0),
                    'has_negative_marking' => (bool) $e->has_negative_marking,
                ])->values()->all(),
                'files' => $files->map(fn (PdfProduct $p) => [
                    'id' => $p->id,
                    'title' => $p->title,
                    'price' => $p->price,
                    'category' => $p->category,
                    'cover' => $p->thumbnail_url,
                    'thumbnail_url' => $p->thumbnail_url,
                    'is_new' => $p->created_at && $p->created_at->gt(now()->subDays(14)),
                ])->values()->all(),
                'blog_posts' => $blog->map(fn (BlogPost $b) => [
                    'id' => $b->id,
                    'title' => $b->title,
                    'slug' => $b->slug,
                    'excerpt' => $b->excerpt,
                    'category' => $b->category,
                    'author_name' => $b->creator?->name,
                ])->values()->all(),
                'articles' => $articles->map(fn (GeneratedContent $a) => [
                    'id' => $a->id,
                    'slug' => $a->slug,
                    'title' => $a->title,
                    'excerpt' => $a->excerpt,
                    'content_type_label' => $a->content_type?->label(),
                    'company_name' => data_get($a->metadata, 'company_name'),
                ])->values()->all(),
                'plans' => $plans->map(fn (SubscriptionPlan $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => $p->price,
                    'duration_days' => $p->duration_days,
                    'features' => $p->features,
                ])->values()->all(),
                'classifications' => $classifications->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'parent_id' => $c->parent_id,
                    'child_ids' => $c->children?->pluck('id')->map(fn ($id) => (int) $id)->values()->all() ?? [],
                ])->values()->all(),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'ok',
            'data' => $payload,
        ])->header('Cache-Control', 'public, max-age=30');
    }
}
