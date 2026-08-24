<?php

namespace App\Http\Resources;

use App\Models\PdfProduct;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin PdfProduct
 *
 * @property-read PdfProduct $resource
 */
class PdfProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $price = (int) $this->price;
        $isFree = $price <= 0;
        $createdAt = $this->created_at;
        $isNew = $createdAt !== null && $createdAt->greaterThanOrEqualTo(now()->subDays(14));
        $thumbUrl = $this->thumbnail_url;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => HtmlSanitizer::clean($this->description),
            'thumbnail' => $this->thumbnail,
            'thumbnail_url' => $thumbUrl,
            'cover' => $thumbUrl,
            'price' => $price,
            'is_free' => $isFree,
            'is_new' => $isNew,
            'category' => $this->category,
            'job_post_id' => $this->job_post_id,
            'job_classification_id' => $this->job_classification_id,
            'classification' => $this->whenLoaded('classification', fn () => [
                'id' => $this->classification?->id,
                'name' => $this->classification?->name,
            ]),
            'download_count' => $this->download_count,
            'purchases_count' => ($count = $this->resource->getAttribute('purchases_count')) !== null
                ? (int) $count
                : null,
            'is_active' => $this->is_active,
            'is_purchased' => $this->when(isset($this->is_purchased), (bool) $this->is_purchased),
            'purchase_date' => $this->when(isset($this->purchase_date), $this->purchase_date),
            'download_url' => $this->when(isset($this->download_url), $this->download_url),
            'created_at' => $createdAt?->toIso8601String(),
        ];
    }
}
