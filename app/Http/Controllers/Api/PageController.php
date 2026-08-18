<?php

namespace App\Http\Controllers\Api;

use App\Models\CmsPage;
use App\Models\TeamMember;
use App\Support\LegalPages;
use Illuminate\Http\JsonResponse;

class PageController extends BaseController
{
    public function show(string $slug): JsonResponse
    {
        if (in_array($slug, ['terms', 'privacy', 'about', 'contact'], true)) {
            LegalPages::ensure();
        }

        $page = CmsPage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        $payload = $page->toArray();
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
