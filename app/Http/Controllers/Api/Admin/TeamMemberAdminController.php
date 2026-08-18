<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\TeamMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class TeamMemberAdminController extends BaseController
{
    public function index(): JsonResponse
    {
        return $this->successResponse(
            TeamMember::query()->orderBy('sort_order')->orderBy('id')->get()
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        if ($request->hasFile('photo')) {
            $data['photo'] = $request->file('photo')->store('team', 'public');
        }
        $data['sort_order'] = $data['sort_order'] ?? ((int) TeamMember::query()->max('sort_order') + 1);
        $member = TeamMember::query()->create($data);

        return $this->successResponse($member, 'عضو تیم اضافه شد.', 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $member = TeamMember::query()->findOrFail($id);
        $data = $this->validated($request, true);
        if ($request->hasFile('photo')) {
            if ($member->photo) {
                Storage::disk('public')->delete($member->photo);
            }
            $data['photo'] = $request->file('photo')->store('team', 'public');
        }
        $member->update($data);

        return $this->successResponse($member->fresh(), 'به‌روزرسانی شد.');
    }

    public function destroy(int $id): JsonResponse
    {
        $member = TeamMember::query()->findOrFail($id);
        if ($member->photo) {
            Storage::disk('public')->delete($member->photo);
        }
        $member->delete();

        return $this->successResponse(null, 'حذف شد.');
    }

    /** @return array<string, mixed> */
    protected function validated(Request $request, bool $update = false): array
    {
        $require = $update ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$require, 'string', 'max:120'],
            'role' => ['nullable', 'string', 'max:160'],
            'bio' => ['nullable', 'string', 'max:800'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:100'],
            'photo' => ['nullable', 'image', 'max:2048'],
        ]);
    }
}
