<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\GeneratedContent;
use App\Services\SEOService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GeneratedContentPublicController extends BaseController
{
    public function __construct(
        protected SEOService $seoService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $perPage = min(50, max(1, (int) $request->input('per_page', 12)));
        $q = GeneratedContent::query()->published()->latest('published_at');

        if ($request->filled('content_type')) {
            $q->where('content_type', $request->string('content_type')->toString());
        }
        if ($request->filled('search')) {
            $s = $request->string('search')->toString();
            $q->where(function ($w) use ($s) {
                $w->where('title', 'like', "%{$s}%")->orWhere('excerpt', 'like', "%{$s}%");
            });
        }

        $rows = $q->paginate($perPage);

        return $this->successResponse([
            'data' => collect($rows->items())->map(fn (GeneratedContent $c) => $this->serialize($c))->values(),
            'meta' => [
                'current_page' => $rows->currentPage(),
                'last_page' => $rows->lastPage(),
                'per_page' => $rows->perPage(),
                'total' => $rows->total(),
            ],
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $c = GeneratedContent::query()
            ->with(['jobPost:id,title,company_name,province,city,status', 'jobPost.source:id,name,domain'])
            ->where('slug', $slug)
            ->first();

        if (! $c || ! $c->isPubliclyVisible()) {
            return $this->errorResponse('مقاله یافت نشد.', 404);
        }

        $data = $this->serialize($c, true);
        $data['schema'] = $this->seoService->generateGeneratedContentSchema($c);
        $data['canonical_url'] = $c->publicUrl();
        $data['meta'] = [
            'title' => $c->title,
            'description' => $c->excerpt,
            'og_title' => $c->title,
            'og_description' => $c->excerpt,
            'canonical' => $c->publicUrl(),
        ];

        return $this->successResponse($data);
    }

    protected function serialize(GeneratedContent $c, bool $full = false): array
    {
        $row = [
            'id' => $c->id,
            'title' => $c->title,
            'slug' => $c->slug,
            'excerpt' => $c->excerpt,
            'content_type' => $c->content_type?->value ?? $c->content_type,
            'content_type_label' => $c->content_type?->label() ?? null,
            'published_at' => $c->published_at?->toIso8601String(),
            'url' => $c->publicUrl(),
            'job_post_id' => $c->job_post_id,
            'job_title' => $c->jobPost?->title,
            'company_name' => $c->jobPost?->company_name,
            'source_name' => $c->jobPost?->source?->name,
        ];
        if ($full) {
            $row['content'] = $c->content;
            $row['job'] = $c->jobPost ? [
                'id' => $c->jobPost->id,
                'title' => $c->jobPost->title,
                'company_name' => $c->jobPost->company_name,
                'province' => $c->jobPost->province,
                'city' => $c->jobPost->city,
                'url' => url('/jobs/'.$c->jobPost->id),
            ] : null;
        }

        return $row;
    }
}
