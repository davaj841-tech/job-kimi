<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PdfProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'thumbnail_url' => $this->thumbnail_url,
            'price' => (int) $this->price,
            'category' => $this->category,
            'job_post_id' => $this->job_post_id,
            'job_classification_id' => $this->job_classification_id,
            'classification' => $this->whenLoaded('classification', fn () => [
                'id' => $this->classification?->id,
                'name' => $this->classification?->name,
            ]),
            'download_count' => $this->download_count,
            'is_active' => $this->is_active,
            'is_purchased' => $this->when(isset($this->is_purchased), $this->is_purchased),
            'purchase_date' => $this->when(isset($this->purchase_date), $this->purchase_date),
            'download_url' => $this->when(isset($this->download_url), $this->download_url),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
