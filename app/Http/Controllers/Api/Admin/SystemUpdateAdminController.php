<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Models\SystemUpdate;
use App\Services\AuditLogService;
use App\Services\Update\UpdateManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Throwable;

class SystemUpdateAdminController extends BaseController
{
    public function __construct(
        protected UpdateManager $updates,
        protected AuditLogService $audit,
    ) {}

    public function status(): JsonResponse
    {
        return $this->successResponse($this->updates->status());
    }

    public function history(): JsonResponse
    {
        $rows = SystemUpdate::query()
            ->with('user:id,name,username')
            ->latest('id')
            ->limit(100)
            ->get()
            ->map(fn (SystemUpdate $u) => [
                'id' => $u->id,
                'uuid' => $u->uuid,
                'version' => $u->version,
                'previous_version' => $u->previous_version,
                'status' => $u->status,
                'release_type' => $u->release_type,
                'description' => $u->description,
                'error' => $u->error,
                'backup_id' => $u->backup_id,
                'rollback_complete' => $u->rollback_complete,
                'duration_ms' => $u->duration_ms,
                'user' => $u->user?->only(['id', 'name', 'username']),
                'started_at' => $u->started_at?->toIso8601String(),
                'finished_at' => $u->finished_at?->toIso8601String(),
                'created_at' => $u->created_at?->toIso8601String(),
            ]);

        return $this->successResponse($rows);
    }

    public function show(int $id): JsonResponse
    {
        $u = SystemUpdate::query()->with('user:id,name,username')->findOrFail($id);

        return $this->successResponse($u);
    }

    public function validateUpload(Request $request): JsonResponse
    {
        $maxKb = max(1024, (int) config('update.max_upload_kb', 102400));
        $request->validate([
            'file' => ['required', 'file', 'mimes:zip', 'max:'.$maxKb],
        ]);

        $stored = $this->storeUpload($request);
        try {
            $result = $this->updates->validatePack($stored);
            $this->audit->log('system_update.validated', null, null, [
                'version' => $result['target_version'] ?? null,
            ]);

            return $this->successResponse($result, 'بسته معتبر است.');
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), 422);
        } finally {
            @unlink($stored);
        }
    }

    public function install(Request $request): JsonResponse
    {
        $maxKb = max(1024, (int) config('update.max_upload_kb', 102400));
        $request->validate([
            'file' => ['required', 'file', 'mimes:zip', 'max:'.$maxKb],
        ]);

        $stored = $this->storeUpload($request);

        try {
            $update = $this->updates->installFromZip($stored, $request->user()?->id);
            $this->audit->log('system_update.completed', null, null, [
                'version' => $update->version,
                'uuid' => $update->uuid,
            ]);

            return $this->successResponse([
                'update' => $update,
                'current_version' => $this->updates->currentVersion(),
            ], 'به‌روزرسانی با موفقیت نصب شد.');
        } catch (Throwable $e) {
            $this->audit->log('system_update.failed', null, null, [
                'message' => $e->getMessage(),
            ]);

            $last = SystemUpdate::query()->latest('id')->first();

            return $this->errorResponse($e->getMessage() ?: 'نصب به‌روزرسانی ناموفق بود.', 422, [
                'update' => $last,
            ]);
        } finally {
            @unlink($stored);
        }
    }

    public function rollback(int $id): JsonResponse
    {
        $update = SystemUpdate::query()->findOrFail($id);
        if (! in_array($update->status, [SystemUpdate::FAILED, SystemUpdate::COMPLETED], true)) {
            return $this->errorResponse('فقط به‌روزرسانی‌های شکست‌خورده یا کامل‌شده قابل Rollback دستی هستند.', 422);
        }

        try {
            $result = $this->updates->rollback($update);
            $this->audit->log('system_update.rolled_back', null, null, [
                'uuid' => $result->uuid,
                'complete' => $result->rollback_complete,
            ]);

            return $this->successResponse($result, 'Rollback انجام شد.');
        } catch (Throwable $e) {
            return $this->errorResponse($e->getMessage(), 422);
        }
    }

    private function storeUpload(Request $request): string
    {
        $dir = storage_path('app/updates/uploads');
        File::ensureDirectoryExists($dir);
        $file = $request->file('file');
        $name = 'upload-'.bin2hex(random_bytes(8)).'.zip';
        $file->move($dir, $name);

        return $dir.DIRECTORY_SEPARATOR.$name;
    }
}
