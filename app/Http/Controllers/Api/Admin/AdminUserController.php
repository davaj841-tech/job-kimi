<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\Admin\AdminUserIndexRequest;
use App\Http\Requests\Api\Admin\UpdateUserRequest;
use App\Http\Requests\Api\Admin\UpdateUserRoleRequest;
use App\Http\Requests\Api\Admin\UpdateUserStatusRequest;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\OperatorPermissions;
use App\Support\StaffRoles;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends BaseController
{
    public function index(AdminUserIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 20);
        $actor = $request->user();

        $query = User::query()->with('subscriptionPlan:id,name');

        if (! empty($validated['search'])) {
            $search = $validated['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('mobile', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['role'])) {
            $query->where('role', $validated['role']);
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $sort = $validated['sort'] ?? 'desc';
        match ($sort) {
            'asc', 'oldest' => $query->orderBy('created_at', 'asc'),
            'wallet', 'wallet_desc' => $query->orderBy('wallet_balance', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $paginator = $query->paginate($perPage);

        $rows = collect($paginator->items())
            ->map(fn (User $user) => $this->listItem($user, $actor))
            ->values();

        return response()->json([
            'success' => true,
            'message' => 'عملیات موفق',
            'data' => $rows,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::query()->with('subscriptionPlan')->find($id);

        if (! $user) {
            return $this->errorResponse('کاربر یافت نشد.', 404);
        }

        $actor = request()->user();
        $attemptsCount = $user->attempts()->count();
        $purchasesCount = $user->pdfPurchases()->count();

        $recentAttempts = $user->attempts()
            ->with('exam:id,title')
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn ($a) => [
                'id' => $a->id,
                'exam_title' => $a->exam?->title,
                'score' => $a->score,
                'status' => $a->status,
                'created_at' => $a->created_at?->toIso8601String(),
            ]);

        return $this->successResponse([
            'id' => $user->id,
            'name' => $user->name,
            'mobile' => $user->mobile,
            'email' => $this->maskEmail($user->email, $actor),
            'national_code' => $this->maskNationalCode($user->national_code, $actor),
            'username' => $user->username,
            'avatar' => $user->avatar,
            'role' => $user->role,
            'operator_permissions' => OperatorPermissions::normalize($user->operator_permissions),
            'status' => $user->status ?? 'active',
            'wallet_balance' => $user->wallet_balance,
            'subscription_plan' => $user->subscriptionPlan?->name,
            'subscription_plan_id' => $user->subscription_plan_id,
            'subscription_expires_at' => $user->subscription_expires_at?->toIso8601String(),
            'is_verified' => $user->is_verified,
            'created_at' => $user->created_at?->toIso8601String(),
            'attempts_count' => $attemptsCount,
            'purchases_count' => $purchasesCount,
            'recent_attempts' => $recentAttempts,
        ]);
    }

    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = User::query()->find($id);

        if (! $user) {
            return $this->errorResponse('کاربر یافت نشد.', 404);
        }

        $data = $request->validated();
        $actor = $request->user();

        if ($denied = $this->denyIfProtectedStaffAccount($actor, $user)) {
            return $denied;
        }

        if (array_key_exists('role', $data) && ! $this->canAssignRole($actor, $data['role'])) {
            return $this->errorResponse('تعیین این نقش برای شما مجاز نیست.', 403);
        }

        if (! StaffRoles::isStaffAdmin($actor)) {
            if (array_key_exists('role', $data) && $data['role'] !== $user->role) {
                return $this->errorResponse('تغییر نقش فقط توسط مدیر مجاز است.', 403);
            }
            if (array_intersect(['status', 'password', 'operator_permissions', 'is_verified'], array_keys($data))) {
                return $this->errorResponse('تغییر وضعیت یا رمز عبور فقط توسط مدیر مجاز است.', 403);
            }
        }

        $data = $this->stripOperatorUnsafeFields($actor, $data);
        unset($data['wallet_balance']);

        if (StaffRoles::isStaffAdmin($actor) && array_key_exists('operator_permissions', $data)) {
            $data['operator_permissions'] = ($data['role'] ?? $user->role) === 'operator'
                ? OperatorPermissions::normalize($data['operator_permissions'])
                : null;
        } else {
            unset($data['operator_permissions']);
        }

        if ($request->user()->id === $user->id) {
            if (isset($data['role']) && ! $this->canSelfRetainStaffRole($user, $data['role'])) {
                return $this->errorResponse('نمی‌توانید نقش خودتان را تغییر دهید.', 422);
            }
            if (isset($data['status']) && $data['status'] === 'blocked') {
                return $this->errorResponse('نمی‌توانید وضعیت حساب خودتان را مسدود کنید.', 422);
            }
        }

        if (array_key_exists('password', $data)) {
            if (filled($data['password'])) {
                // hashed via User cast
            } else {
                unset($data['password']);
            }
        }

        $old = $user->only(['name', 'email', 'mobile', 'role', 'status', 'username', 'national_code']);
        $user->update($data);

        app(AuditLogService::class)->log(
            'user.updated',
            $user,
            $old,
            $user->fresh()->only(['name', 'email', 'mobile', 'role', 'status', 'username', 'national_code'])
        );

        return $this->successResponse($this->listItem($user->fresh('subscriptionPlan'), $actor), 'کاربر به‌روزرسانی شد.');
    }

    public function updateRole(UpdateUserRoleRequest $request, int $id): JsonResponse
    {
        $user = User::query()->find($id);

        if (! $user) {
            return $this->errorResponse('کاربر یافت نشد.', 404);
        }

        $actor = $request->user();
        $newRole = $request->validated('role');

        if (! StaffRoles::isStaffAdmin($actor)) {
            return $this->errorResponse('تغییر نقش فقط توسط مدیر مجاز است.', 403);
        }

        if ($denied = $this->denyIfProtectedStaffAccount($actor, $user)) {
            return $denied;
        }

        if (! $this->canAssignRole($actor, $newRole)) {
            return $this->errorResponse('تعیین این نقش برای شما مجاز نیست.', 403);
        }

        if ($request->user()->id === $user->id && ! $this->canSelfRetainStaffRole($user, $newRole)) {
            return $this->errorResponse('نمی‌توانید نقش خودتان را تغییر دهید.', 422);
        }

        $old = ['role' => $user->role];
        $payload = ['role' => $newRole];
        if ($newRole !== 'operator') {
            $payload['operator_permissions'] = null;
        }
        $user->update($payload);
        app(AuditLogService::class)->log('user.role_changed', $user, $old, ['role' => $user->role]);

        return $this->successResponse($this->listItem($user->fresh('subscriptionPlan'), $actor), 'نقش کاربر به‌روزرسانی شد.');
    }

    public function updateStatus(UpdateUserStatusRequest $request, int $id): JsonResponse
    {
        $user = User::query()->find($id);

        if (! $user) {
            return $this->errorResponse('کاربر یافت نشد.', 404);
        }

        if ($denied = $this->denyIfTargetIsAdmin($request->user(), $user)) {
            return $denied;
        }

        if ($request->user()->id === $user->id) {
            return $this->errorResponse('نمی‌توانید وضعیت حساب خودتان را تغییر دهید.', 422);
        }

        $user->update(['status' => $request->validated('status')]);

        return $this->successResponse($this->listItem($user->fresh('subscriptionPlan'), $request->user()), 'وضعیت کاربر به‌روزرسانی شد.');
    }

    public function store(Request $request): JsonResponse
    {
        $request->merge([
            'mobile' => filled($request->input('mobile')) ? trim((string) $request->input('mobile')) : null,
            'email' => filled($request->input('email')) ? trim((string) $request->input('email')) : null,
            'username' => strtolower(trim((string) $request->input('username', ''))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'regex:/^[a-z0-9_]{3,20}$/', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8'],
            'mobile' => ['nullable', 'regex:/^09\d{9}$/', 'unique:users,mobile'],
            'email' => ['nullable', 'email', 'max:191', 'unique:users,email'],
            'province' => ['required', 'string', 'max:100'],
            'role' => ['required', 'in:jobseeker,employer,operator,admin,super_admin'],
            'operator_permissions' => ['sometimes', 'nullable', 'array'],
            'operator_permissions.*' => ['string'],
            'status' => ['nullable', 'in:active,blocked'],
        ], [
            'name.required' => 'نام الزامی است.',
            'username.required' => 'نام کاربری الزامی است.',
            'username.regex' => 'نام کاربری باید ۳ تا ۲۰ کاراکتر و فقط حروف کوچک انگلیسی، عدد و ـ باشد.',
            'username.unique' => 'نام کاربری تکراری است.',
            'password.required' => 'رمز عبور الزامی است.',
            'password.min' => 'رمز عبور باید حداقل ۸ کاراکتر باشد.',
            'mobile.regex' => 'شماره موبایل باید ۱۱ رقم و با ۰۹ شروع شود.',
            'mobile.unique' => 'موبایل تکراری است.',
            'email.email' => 'ایمیل معتبر نیست.',
            'email.unique' => 'ایمیل تکراری است.',
            'province.required' => 'انتخاب استان الزامی است.',
            'role.required' => 'انتخاب نقش الزامی است.',
            'role.in' => 'نقش انتخاب‌شده معتبر نیست.',
        ]);

        if (empty($data['mobile']) && empty($data['email'])) {
            return $this->errorResponse('حداقل یکی از موبایل یا ایمیل الزامی است.', 422);
        }

        $actor = $request->user();

        if (! $this->canAssignRole($actor, $data['role'])) {
            return $this->errorResponse('ایجاد این نقش برای شما مجاز نیست.', 403);
        }

        if (! StaffRoles::isStaffAdmin($actor) && ! in_array($data['role'], ['jobseeker', 'employer'], true)) {
            return $this->errorResponse('ایجاد نقش مدیر یا اپراتور فقط توسط مدیر مجاز است.', 403);
        }

        $permissions = null;
        if ($data['role'] === 'operator') {
            $permissions = StaffRoles::isStaffAdmin($actor)
                ? OperatorPermissions::normalize($data['operator_permissions'] ?? OperatorPermissions::defaults())
                : OperatorPermissions::defaults();
        }

        $user = User::query()->create([
            'name' => $data['name'],
            'username' => $data['username'],
            'password' => $data['password'],
            'mobile' => $data['mobile'] ?? null,
            'email' => $data['email'] ?? null,
            'province' => $data['province'] ?? null,
            'role' => $data['role'],
            'operator_permissions' => $permissions,
            'status' => $data['status'] ?? 'active',
            'is_verified' => true,
        ]);

        app(AuditLogService::class)->log('user.created', $user, null, [
            'role' => $user->role,
            'username' => $user->username,
        ]);

        return $this->successResponse($this->listItem($user->load('subscriptionPlan'), $actor), 'کاربر ایجاد شد.', 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::query()->find($id);

        if (! $user) {
            return $this->errorResponse('کاربر یافت نشد.', 404);
        }

        if ($denied = $this->denyIfProtectedStaffAccount(request()->user(), $user)) {
            return $denied;
        }

        if (request()->user()?->id === $user->id) {
            return $this->errorResponse('نمی‌توانید حساب خودتان را حذف کنید.', 422);
        }

        app(AuditLogService::class)->log('user.deleted', $user, $user->only(['name', 'role', 'mobile']), null);
        $user->delete();

        return $this->successResponse(null, 'کاربر حذف شد.');
    }

    protected function denyIfProtectedStaffAccount(?User $actor, User $target): ?JsonResponse
    {
        if (StaffRoles::isProtectedStaffAccount($target) && ! StaffRoles::canManageStaffAccounts($actor)) {
            return $this->errorResponse('تغییر حساب مدیر فقط توسط سوپرادمین مجاز است.', 403);
        }

        return null;
    }

    /** @deprecated use denyIfProtectedStaffAccount */
    protected function denyIfTargetIsAdmin(?User $actor, User $target): ?JsonResponse
    {
        return $this->denyIfProtectedStaffAccount($actor, $target);
    }

    protected function canAssignRole(?User $actor, string $role): bool
    {
        if (in_array($role, ['super_admin', 'admin'], true)) {
            return StaffRoles::canManageStaffAccounts($actor);
        }

        if ($role === 'operator') {
            return StaffRoles::isStaffAdmin($actor);
        }

        return true;
    }

    protected function canSelfRetainStaffRole(User $user, string $newRole): bool
    {
        if (StaffRoles::isSuperAdmin($user)) {
            return $newRole === StaffRoles::SUPER_ADMIN;
        }

        if (StaffRoles::isAdmin($user)) {
            return $newRole === StaffRoles::ADMIN;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function stripOperatorUnsafeFields(?User $actor, array $data): array
    {
        if (StaffRoles::isStaffAdmin($actor)) {
            return $data;
        }

        unset(
            $data['role'],
            $data['status'],
            $data['password'],
            $data['operator_permissions'],
            $data['subscription_plan_id'],
            $data['subscription_expires_at'],
            $data['wallet_balance'],
            $data['is_verified'],
        );

        return $data;
    }

    protected function maskNationalCode(?string $code, ?User $actor): ?string
    {
        if (! filled($code) || StaffRoles::isStaffAdmin($actor)) {
            return $code;
        }

        return '***'.substr($code, -4);
    }

    protected function maskEmail(?string $email, ?User $actor): ?string
    {
        if (! filled($email) || StaffRoles::isStaffAdmin($actor)) {
            return $email;
        }

        [$local, $domain] = explode('@', $email, 2);

        return substr($local, 0, 2).'***@'.$domain;
    }

    /**
     * @return array<string, mixed>
     */
    protected function listItem(User $user, ?User $actor = null): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name ?: '—',
            'mobile' => $user->mobile,
            'email' => $this->maskEmail($user->email, $actor),
            'username' => $user->username,
            'province' => $user->province,
            'role' => $user->role,
            'operator_permissions' => OperatorPermissions::normalize($user->operator_permissions),
            'wallet_balance' => $user->wallet_balance,
            'subscription_plan' => $user->subscriptionPlan?->name,
            'status' => $user->status ?? 'active',
            'created_at' => $user->created_at?->toIso8601String(),
        ];
    }
}
