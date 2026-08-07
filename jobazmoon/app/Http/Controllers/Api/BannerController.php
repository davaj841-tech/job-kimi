<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends BaseController
{
    public function index(Request $request): JsonResponse
    {
        $query = Banner::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderByDesc('id');

        if ($request->filled('position')) {
            $query->where('position', $request->query('position'));
        }

        return $this->successResponse($query->get());
    }
}
