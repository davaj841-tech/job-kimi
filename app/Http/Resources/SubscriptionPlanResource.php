<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SubscriptionPlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $features = is_array($this->features) ? $this->features : [];
        $badge = null;
        $clean = [];
        foreach ($features as $f) {
            if (is_string($f) && str_starts_with($f, '__badge:')) {
                $badge = substr($f, 8);
            } else {
                $clean[] = $f;
            }
        }

        return [
            'id' => $this->id,
            'name' => $this->name,
            'duration_days' => $this->duration_days,
            'price' => (int) $this->price,
            'features' => $clean,
            'badge_color' => $badge,
            'is_active' => $this->is_active,
        ];
    }
}
