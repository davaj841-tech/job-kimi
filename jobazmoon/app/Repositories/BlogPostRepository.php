<?php

namespace App\Repositories;

use App\Models\BlogPost;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class BlogPostRepository
{
    public function getPublished(array $filters): LengthAwarePaginator
    {
        $query = BlogPost::query()
            ->with('creator:id,name')
            ->where('status', 'published');

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    public function findBySlug(string $slug): ?BlogPost
    {
        return BlogPost::query()
            ->with('creator:id,name')
            ->where('slug', $slug)
            ->where('status', 'published')
            ->first();
    }

    public function findById(int $id): ?BlogPost
    {
        return BlogPost::query()->with('creator:id,name')->find($id);
    }

    public function getAdminList(array $filters): LengthAwarePaginator
    {
        $query = BlogPost::query()->with('creator:id,name');

        if (! empty($filters['status']) && in_array($filters['status'], ['draft', 'published'], true)) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        return $query->latest()->paginate($filters['per_page'] ?? 15);
    }

    public function getRelated(BlogPost $post, int $limit = 5): Collection
    {
        return BlogPost::query()
            ->where('status', 'published')
            ->where('id', '!=', $post->id)
            ->when($post->category, fn ($q) => $q->where('category', $post->category))
            ->latest()
            ->limit($limit)
            ->get();
    }
}
