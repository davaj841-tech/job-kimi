<?php

namespace App\Http\Controllers\Api;

use App\Models\CmsPage;
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

        return $this->successResponse($page);
    }
}
