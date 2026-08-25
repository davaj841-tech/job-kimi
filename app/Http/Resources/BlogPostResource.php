<?php

namespace App\Http\Resources;

use App\Models\BlogPost;
use App\Support\HtmlSanitizer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin BlogPost
 *
 * @property-read BlogPost $resource
 */
class BlogPostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => HtmlSanitizer::clean($this->content),
            'excerpt' => $this->excerpt,
            'featured_image' => $this->featured_image,
            'featured_image_url' => $this->featured_image_url,
            'category' => $this->category,
            'job_classification_id' => $this->job_classification_id,
            'auto_catalog' => (bool) ($this->auto_catalog ?? true),
            'exam_ids' => $this->exam_ids ?? [],
            'pdf_ids' => $this->pdf_ids ?? [],
            'catalog_exams' => $this->when(isset($this->catalog_exams), $this->catalog_exams),
            'catalog_pdfs' => $this->when(isset($this->catalog_pdfs), $this->catalog_pdfs),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'status' => $this->status,
            'author_name' => $this->when($this->relationLoaded('creator'), $this->creator?->name),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            'prev_post' => $this->when(isset($this->prev_post), $this->prev_post),
            'next_post' => $this->when(isset($this->next_post), $this->next_post),
        ];
    }
}
