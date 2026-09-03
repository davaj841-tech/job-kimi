<?php

namespace App\Http\Controllers\Api;

use App\Models\CmsPage;
use App\Models\TeamMember;
use App\Services\Seo\CanonicalService;
use App\Services\Seo\SeoManager;
use App\Support\HtmlSanitizer;
use App\Support\LegalPages;
use Illuminate\Http\JsonResponse;

class PageController extends BaseController
{
    public function __construct(protected SeoManager $seoManager) {}

    public function show(string $slug): JsonResponse
    {
        if (in_array($slug, ['terms', 'privacy', 'about', 'contact', 'refund'], true)) {
            LegalPages::ensure();
        }

        $page = CmsPage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $payload = $page->toArray();
        $payload['content'] = HtmlSanitizer::clean($page->content);

        $breadcrumbs = [
            ['name' => 'خانه', 'url' => url('/')],
            ['name' => $page->title, 'url' => app(CanonicalService::class)->getCanonical($page) ?? url('/page/'.$page->slug)],
        ];
        $seo = $this->seoManager->buildPublicPayload($page, $breadcrumbs);
        $payload['seo'] = $seo;
        $payload['schema'] = $seo['schemas'];

        if ($slug === 'about') {
            $payload['team'] = TeamMember::query()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get()
                ->map(fn (TeamMember $m) => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'role' => $m->role,
                    'bio' => $m->bio,
                    'photo_url' => $m->photo_url,
                ])
                ->values()
                ->all();
        }

        return $this->successResponse($payload);
    }
}
