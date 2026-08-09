<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class ResumeCollection extends ResourceCollection
{
    public $collects = ResumeResource::class;

    public function toArray(Request $request): array
    {
        return $this->collection->map(function ($resume) {
            return [
                'id' => $resume->id,
                'title' => $resume->title,
                'template_id' => (int) $resume->template_id,
                'created_at' => $resume->created_at?->toIso8601String(),
                'updated_at' => $resume->updated_at?->toIso8601String(),
            ];
        })->values()->all();
    }
}
