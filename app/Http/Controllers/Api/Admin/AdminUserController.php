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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminUserController extends BaseController
{
    public function index(AdminUserIndexRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $perPage = (int) ($validated['per_page'] ?? 20);

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

        $rows = collect($paginator->items())->map(fn (User $user) => $this->listItem($user))->values();

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
            'email' => $user->email,
            'national_code' => $user->national_code,
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

        if (($actor?->role !== 'admin') && (($data['role'] ?? null) === 'admin')) {
            return $this->errorResponse('فقط مدیر می‌تواند نقش مدیر تعیین کند.', 403);
        }

        if ($actor?->role === 'admin' && array_key_exists('operator_permissions', $data)) {
            $data['operator_permissions'] = ($data['role'] ?? $user->role) === 'operator'
                ? OperatorPermissions::normalize($data['operator_permissions'])
                : null;
        } else {
            unset($data['operator_permissions']);
        }

        if ($request->user()->id === $user->id) {
            if (isset($data['role']) && $data['role'] !== 'admin') {
                return $this->errorResponse('نمی‌توانید نقش خودتان را از ادمین خارج کنید.', 422);
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

        return $this->successResponse($this->listItem($user->fresh('subscriptionPlan')), 'کاربر به‌روزرسانی شد.');
    }

    public function updateRole(UpdateUserRoleRequest $request, int $id): JsonResponse
    {
        $user = User::query()->find($id);

        if (! $user) {
            return $this->errorResponse('کاربر یافت نشد.', 404);
        }

        if ($request->user()?->role !== 'admin' && $request->validated('role') === 'admin') {
            return $this->errorResponse('فقط مدیر می‌تواند نقش مدیر تعیین کند.', 403);
        }

        if ($request->user()->id === $user->id && $request->validated('role') !== 'admin') {
            return $this->errorResponse('نمی‌توانید نقش خودتان را از ادمین خارج کنید.', 422);
        }

        $old = ['role' => $user->role];
        $payload = ['role' => $request->validated('role')];
        if ($request->validated('role') !== 'operator') {
            $payload['operator_permissions'] = null;
        }
        $user->update($payload);
        app(AuditLogService::class)->log('user.role_changed', $user, $old, ['role' => $user->role]);

        return $this->successResponse($this->listItem($user->fresh('subscriptionPlan')), 'نقش کاربر به‌روزرسانی شد.');
    }

    public function updateStatus(UpdateUserStatusRequest $request, int $id): JsonResponse
    {
        $user = User::query()->find($id);

        if (! $user) {
            return $this->errorResponse('کاربر یافت نشد.', 404);
        }

        if ($request->user()->id === $user->id) {
            return $this->errorResponse('نمی‌توانید وضعیت حساب خودتان را تغییر دهید.', 422);
        }

        $user->update(['status' => $request->validated('status')]);

        return $this->successResponse($this->listItem($user->fresh('subscriptionPlan')), 'وضعیت کاربر به‌روزرسانی شد.');
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
            'role' => ['required', 'in:jobseeker,employer,operator,admin'],
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

        if ($request->user()?->role !== 'admin' && $data['role'] === 'admin') {
            return $this->errorResponse('فقط مدیر می‌تواند حساب مدیر بسازد.', 403);
        }

        $permissions = null;
        if ($data['role'] === 'operator') {
            $permissions = $request->user()?->role === 'admin'
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

        return $this->successResponse($this->listItem($user->load('subscriptionPlan')), 'کاربر ایجاد شد.', 201);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::query()->find($id);

        if (! $user) {
            return $this->errorResponse('کاربر یافت نشد.', 404);
        }

        if (request()->user()?->id === $user->id) {
            return $this->errorResponse('نمی‌توانید حساب خودتان را حذف کنید.', 422);
        }

        $user->delete();

        return $this->successResponse(null, 'کاربر حذف شد.');
    }

    protected function listItem(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name ?: '—',
            'mobile' => $user->mobile,
            'email' => $user->email,
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
