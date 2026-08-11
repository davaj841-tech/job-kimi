<?php

namespace App\Observers;

use App\Models\Feature;
use App\Services\FeatureFlagService;

final class FeatureObserver
{
    public function __construct(
        private readonly FeatureFlagService $features
    ) {}

    public function saved(Feature $feature): void
    {
        $this->features->forgetCache();
    }

    public function deleted(Feature $feature): void
    {
        $this->features->forgetCache();
    }
}
