<?php

namespace App\Services;

use App\Models\BlogPost;
use App\Repositories\BlogPostRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class BlogPostService
{
    public function __construct(
        protected BlogPostRepository $blogPostRepository
    ) {}

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

    public function create(array $data): BlogPost
    {
        if (empty($data['slug'])) {
            $data['slug'] = $this->generateSlug($data['title']);
        }

        $data['status'] = $data['status'] ?? 'draft';

        return BlogPost::query()->create($data);
    }

    public function update(BlogPost $post, array $data): BlogPost
    {
        $post->update($data);

        return $post->fresh(['creator']);
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
