<?php

namespace App\Repositories;

use App\Models\Resume;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ResumeRepository
{
    public function getByUser(User $user): Collection
    {
        return Resume::query()
            ->where('user_id', $user->id)
            ->latest()
            ->get();
    }

    public function findById(int $id, User $user): ?Resume
    {
        return Resume::query()
            ->where('user_id', $user->id)
            ->find($id);
    }

    public function getRecent(User $user, int $limit = 5): Collection
    {
        return Resume::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit($limit)
            ->get();
    }
}
