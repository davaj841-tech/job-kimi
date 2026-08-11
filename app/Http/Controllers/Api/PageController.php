<?php

namespace App\Http\Controllers\Api;

use App\Models\CmsPage;
use Illuminate\Http\JsonResponse;

class PageController extends BaseController
{
    public function show(string $slug): JsonResponse
    {
        $page = CmsPage::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->firstOrFail();

        return $this->successResponse($page);
    }
}
