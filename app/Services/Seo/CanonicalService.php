<?php

namespace App\Services\Seo;

use App\Models\BlogPost;
use App\Models\CmsPage;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\GeneratedContent;
use App\Models\JobPost;
use App\Models\PdfProduct;
use App\Models\Seo\SeoMeta;
use Illuminate\Database\Eloquent\Model;

class CanonicalService
{
    public function getCanonical(Model $model): ?string
    {
        $metaRaw = $model->getRelationValue('seoMeta');
        $meta = $metaRaw instanceof SeoMeta ? $metaRaw : null;
        if ($custom = ($meta !== null ? $meta->canonical : null)) {
            return $custom;
        }

        return $this->generateCanonical($model);
    }

    public function generateCanonical(Model $model): ?string
    {
        if ($model instanceof GeneratedContent) {
            return $model->publicUrl();
        }

        if ($model instanceof JobPost) {
            return url('/jobs/'.$model->getKey());
        }

        if ($model instanceof PdfProduct) {
            return url('/pdfs/'.$model->getKey());
        }

        if ($model instanceof CmsPage) {
            return $this->cmsPageUrl($model);
        }

        $slug = $model->getAttribute('slug');
        if (! $slug) {
            return null;
        }

        $prefix = match (true) {
            $model instanceof BlogPost => 'blog',
            $model instanceof Exam => 'exams',
            $model instanceof ExamCategory => 'categories',
            default => null,
        };

        if (! $prefix) {
            return null;
        }

        return url("/{$prefix}/{$slug}");
    }

    protected function cmsPageUrl(CmsPage $page): string
    {
        if (in_array($page->slug, ['terms', 'privacy', 'about', 'contact'], true)) {
            return url('/'.$page->slug);
        }

        return url('/page/'.$page->slug);
    }
}
