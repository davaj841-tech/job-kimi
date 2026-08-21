<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiSuggestionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'section' => $this['section'] ?? $this->section ?? null,
            'suggestion' => $this['suggestion'] ?? $this->suggestion ?? null,
            'priority' => $this['priority'] ?? $this->priority ?? 'medium',
        ];
    }
}
