<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ResumeCollection extends ResourceCollection
{
    public $collects = ResumeResource::class;

    /**
     * @return list<array<string, mixed>>
     */
    public function toArray(Request $request): array
    {
        return $this->collection->map(function ($resume) {
            $data = is_array($resume->data) ? $resume->data : [];

            return [
                'id' => $resume->id,
                'title' => $resume->title,
                'template_id' => (int) $resume->template_id,
                'is_active' => (bool) $resume->is_active,
                'data' => [
                    'personal' => is_array($data['personal'] ?? null) ? $data['personal'] : [],
                ],
                'created_at' => $resume->created_at?->toIso8601String(),
                'updated_at' => $resume->updated_at?->toIso8601String(),
            ];
        })->values()->all();
    }
}
