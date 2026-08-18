<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Repositories\BlogPostRepository;
use App\Services\CatalogAttachService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class BlogPostService
{
    public function __construct(
        protected BlogPostRepository $blogPostRepository
    ) {}

    /**
     * @param  array<string, mixed>  $filters
     */
    public function getPublishedList(array $filters): LengthAwarePaginator
    {
        return $this->blogPostRepository->getPublished($filters);
    }

    public function generateSlug(string $title): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'post';
        }

        $slug = $base;
        $i = 1;
        while (BlogPost::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }

    /**
     * @return array{prev_post: ?array{slug: string, title: string}, next_post: ?array{slug: string, title: string}}
     */
    public function getPrevNext(BlogPost $post): array
    {
        $prev = BlogPost::query()
            ->where('status', 'published')
            ->where('created_at', '<', $post->created_at)
            ->orderByDesc('created_at')
            ->first(['slug', 'title']);

        $next = BlogPost::query()
            ->where('status', 'published')
            ->where('created_at', '>', $post->created_at)
            ->orderBy('created_at')
            ->first(['slug', 'title']);

        return [
            'prev_post' => $prev ? ['slug' => $prev->slug, 'title' => $prev->title] : null,
            'next_post' => $next ? ['slug' => $next->slug, 'title' => $next->title] : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): BlogPost
    {
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['title']);
        }

        $data['status'] = $data['status'] ?? 'draft';
        $data = $this->normalizeCatalog($data);

        return BlogPost::query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(BlogPost $post, array $data): BlogPost
    {
        $data = $this->normalizeCatalog($data);
        $post->update($data);

        return $post->fresh(['creator']);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeCatalog(array $data): array
    {
        $attach = app(CatalogAttachService::class);
        if (array_key_exists('exam_ids', $data)) {
            $data['exam_ids'] = $attach->intIds($data['exam_ids']);
        }
        if (array_key_exists('pdf_ids', $data)) {
            $data['pdf_ids'] = $attach->intIds($data['pdf_ids']);
        }

        return $data;
    }

    public function relatedCatalog(BlogPost $post): array
    {
        return app(CatalogAttachService::class)->resolve(
            $post->job_classification_id ? (int) $post->job_classification_id : null,
            (bool) ($post->auto_catalog ?? true),
            $post->exam_ids ?? [],
            $post->pdf_ids ?? []
        );
    }

    public function publish(int $id): BlogPost
    {
        $post = $this->blogPostRepository->findById($id);

        if (! $post) {
            throw new \RuntimeException('پست یافت نشد.');
        }

        $post->update(['status' => 'published']);

        return $post->fresh(['creator']);
    }

    public function draft(int $id): BlogPost
    {
        $post = $this->blogPostRepository->findById($id);

        if (! $post) {
            throw new \RuntimeException('پست یافت نشد.');
        }

        $post->update(['status' => 'draft']);

        return $post->fresh(['creator']);
    }
}
