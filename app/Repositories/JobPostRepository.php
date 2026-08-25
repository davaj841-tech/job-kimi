<?php

namespace App\Repositories;

use App\Models\JobClassification;
use App\Models\JobPost;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class JobPostRepository
{
    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, JobPost>
     */
    public function getApproved(array $filters): LengthAwarePaginator
    {
        $query = JobPost::query()
            ->with(['classification'])
            ->withCount(['exams as related_exams_count', 'pdfProducts as related_pdfs_count'])
            ->where('status', 'approved');

        $this->applyLocationFilters($query, $filters);

        if (! empty($filters['job_classification_id'])) {
            $query->where('job_classification_id', $filters['job_classification_id']);
        }

        if (isset($filters['is_featured']) && $filters['is_featured'] !== '') {
            $query->where('is_featured', filter_var($filters['is_featured'], FILTER_VALIDATE_BOOLEAN));
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        if (! empty($filters['employment_type'])) {
            $query->where('employment_type', $filters['employment_type']);
        }

        $query->where(function ($q) {
            $q->whereNull('registration_deadline')
                ->orWhereDate('registration_deadline', '>=', now()->toDateString());
        });

        $sort = $filters['sort'] ?? 'newest';
        if ($sort === 'deadline') {
            $query->orderByRaw('registration_deadline IS NULL')
                ->orderBy('registration_deadline')
                ->orderByDesc('is_featured');
        } else {
            $query->orderByDesc('is_featured')
                ->orderByDesc('created_at');
        }

        $perPage = (int) ($filters['per_page'] ?? 30);
        $perPage = max(10, min(50, $perPage ?: 30));

        return $query->paginate($perPage);
    }

    public function findApproved(int $id): ?JobPost
    {
        return JobPost::query()
            ->with([
                'classification',
                'exams:id,job_post_id,title,slug,is_free,duration_minutes,total_questions',
                'pdfProducts:id,job_post_id,title,price,thumbnail',
            ])
            ->withCount(['exams as related_exams_count', 'pdfProducts as related_pdfs_count'])
            ->where('status', 'approved')
            ->where(function ($q) {
                $q->whereNull('registration_deadline')
                    ->orWhereDate('registration_deadline', '>=', now()->toDateString());
            })
            ->find($id);
    }

    public function findById(int $id): ?JobPost
    {
        return JobPost::query()
            ->with(['creator:id,name,mobile', 'approver:id,name,mobile', 'classification', 'attachments'])
            ->with(['exams', 'pdfProducts'])
            ->find($id);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, JobPost>
     */
    public function getAdminList(array $filters): LengthAwarePaginator
    {
        $query = JobPost::query()
            ->with(['creator:id,name,mobile', 'approver:id,name,mobile', 'classification', 'source:id,name,domain,slug,reliability_level'])
            ->withCount(['exams as related_exams_count', 'pdfProducts as related_pdfs_count']);

        $status = $filters['status'] ?? 'all';
        if ($status !== 'all' && $status !== '' && in_array($status, ['pending', 'approved', 'rejected', 'draft', 'expired'], true)) {
            $query->where('status', $status);
        }

        if (! empty($filters['aggregated_only'])) {
            $query->whereNotNull('job_source_id');
        }

        if (! empty($filters['job_source_id'])) {
            $query->where('job_source_id', (int) $filters['job_source_id']);
        }

        $this->applyLocationFilters($query, $filters);

        if (! empty($filters['job_classification_id'])) {
            $query->where('job_classification_id', $filters['job_classification_id']);
        }

        if (! empty($filters['deadline_from'])) {
            $query->whereDate('registration_deadline', '>=', $filters['deadline_from']);
        }

        if (! empty($filters['deadline_to'])) {
            $query->whereDate('registration_deadline', '<=', $filters['deadline_to']);
        }

        if (! empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        return $query
            ->orderByDesc('created_at')
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAdminFilterOptions(): array
    {
        $provinceValues = JobPost::query()
            ->whereNotNull('provinces')
            ->pluck('provinces')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $legacy = JobPost::query()->whereNotNull('province')->distinct()->orderBy('province')->pluck('province')->all();
        $provinces = collect($provinceValues)->merge($legacy)->unique()->sort()->values()->all();

        return [
            'provinces' => $provinces,
            'cities' => JobPost::query()->whereNotNull('city')->distinct()->orderBy('city')->pluck('city')->values()->all(),
            'classifications' => JobClassification::query()
                ->with('parent:id,name')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id', 'icon', 'color', 'logo_path', 'show_on_home'])
                ->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'name' => $c->parent_id ? (($c->parent?->name ?: '').' › '.$c->name) : $c->name,
                        'raw_name' => $c->name,
                        'parent_id' => $c->parent_id,
                        'icon' => $c->icon,
                        'color' => $c->color,
                        'logo_url' => $c->logo_url,
                        'show_on_home' => (bool) $c->show_on_home,
                    ];
                })
                ->values()
                ->all(),
            'home_classifications' => JobClassification::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->where('show_on_home', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id', 'icon', 'color', 'logo_path'])
                ->map(function ($c) {
                    $childIds = JobClassification::query()->where('parent_id', $c->id)->pluck('id')->map(fn ($id) => (int) $id)->all();

                    return [
                        'id' => $c->id,
                        'name' => $c->name,
                        'parent_id' => null,
                        'icon' => $c->icon,
                        'color' => $c->color,
                        'logo_url' => $c->logo_url,
                        'child_ids' => $childIds,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, JobPost>
     */
    public function getByCreator(User $user): Collection
    {
        return JobPost::query()
            ->where('created_by', $user->id)
            ->latest()
            ->get();
    }

    /**
     * @return array<string, mixed>
     */
    public function getFilterOptions(): array
    {
        $base = JobPost::query()->where('status', 'approved');

        $provinceValues = (clone $base)
            ->whereNotNull('provinces')
            ->pluck('provinces')
            ->flatten()
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        $legacy = (clone $base)->whereNotNull('province')->distinct()->orderBy('province')->pluck('province')->all();

        return [
            'provinces' => collect($provinceValues)->merge($legacy)->unique()->sort()->values()->all(),
            'cities' => (clone $base)->whereNotNull('city')->distinct()->orderBy('city')->pluck('city')->values()->all(),
            'classifications' => JobClassification::query()
                ->with('parent:id,name')
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get(['id', 'name', 'parent_id', 'icon', 'color', 'logo_path', 'show_on_home'])
                ->map(function ($c) {
                    return [
                        'id' => $c->id,
                        'name' => $c->parent_id ? (($c->parent?->name ?: '').' › '.$c->name) : $c->name,
                        'raw_name' => $c->name,
                        'parent_id' => $c->parent_id,
                        'icon' => $c->icon,
                        'color' => $c->color,
                        'logo_url' => $c->logo_url,
                        'show_on_home' => (bool) $c->show_on_home,
                    ];
                })
                ->values()
                ->all(),
            'home_classifications' => JobClassification::query()
                ->whereNull('parent_id')
                ->where('is_active', true)
                ->where('show_on_home', true)
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id', 'icon', 'color', 'logo_path'])
                ->map(function ($c) {
                    $childIds = JobClassification::query()->where('parent_id', $c->id)->pluck('id')->map(fn ($id) => (int) $id)->all();

                    return [
                        'id' => $c->id,
                        'name' => $c->name,
                        'parent_id' => null,
                        'icon' => $c->icon,
                        'color' => $c->color,
                        'logo_url' => $c->logo_url,
                        'child_ids' => $childIds,
                    ];
                })
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, JobPost>
     */
    public function getPending(): Collection
    {
        return JobPost::query()
            ->with(['creator:id,name,mobile', 'classification'])
            ->where('status', 'pending')
            ->latest()
            ->get();
    }

    /**
     * @param  Builder<JobPost>  $query
     * @param  array<string, mixed>  $filters
     */
    protected function applyLocationFilters($query, array $filters): void
    {
        if (! empty($filters['province'])) {
            $province = $filters['province'];
            $query->where(function ($q) use ($province) {
                $q->where('province', $province)
                    ->orWhereJsonContains('provinces', $province);
            });
        }

        if (! empty($filters['city'])) {
            $query->where('city', 'like', '%'.$filters['city'].'%');
        }
    }
}
