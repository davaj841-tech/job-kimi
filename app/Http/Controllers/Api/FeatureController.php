<?php

namespace App\Http\Controllers\Api;

use App\Services\FeatureFlagService;
use Illuminate\Http\JsonResponse;

final class FeatureController extends BaseController
{
    public function __construct(
        private readonly FeatureFlagService $features
    ) {}

    public function index(): JsonResponse
    {
        return $this->successResponse($this->features->allForApi());
    }
}
