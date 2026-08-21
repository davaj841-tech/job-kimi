<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Jobs\CreateBackupJob;
use App\Services\AuditLogService;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupAdminController extends BaseController
{
    public function __construct(
        protected BackupService $backups,
        protected AuditLogService $audit,
    ) {}

    public function index(): JsonResponse
    {
        return $this->successResponse($this->backups->listBackups());
    }

    public function store(): JsonResponse
    {
        CreateBackupJob::dispatch();
        $this->audit->log('backup.queued', null, null, null);

        return $this->successResponse(null, 'بکاپ در صف اجرا قرار گرفت.', 202);
    }

    public function download(Request $request): BinaryFileResponse|JsonResponse
    {
        $data = $request->validate(['path' => ['required', 'string']]);

        try {
            $full = $this->backups->resolvePath($data['path']);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        $this->audit->log('backup.downloaded', null, null, [
            'file' => basename($full),
        ]);

        return response()->download($full, basename($full));
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['path' => ['required', 'string']]);

        try {
            $full = $this->backups->resolvePath($data['path']);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        $result = $this->backups->verifyBackup($full);

        return $this->successResponse([
            'ok' => $result['ok'],
            'message' => $result['message'],
            'status' => $result['manifest']['status'] ?? null,
            'warnings' => $result['manifest']['warnings'] ?? [],
        ]);
    }

    public function restore(Request $request): JsonResponse
    {
        $maxKb = max(102400, (int) config('backup.restore_max_kb', 512000));

        $request->validate([
            'file' => ['required', 'file', 'max:'.$maxKb],
        ], [
            'file.max' => 'حجم فایل بکاپ بیش از حد مجاز است. برای بکاپ‌های بزرگ از scripts/restore.sh استفاده کنید.',
        ]);

        $file = $request->file('file');
        $ext = strtolower((string) $file->getClientOriginalExtension());
        if ($ext !== 'zip') {
            return $this->errorResponse('فقط فایل ZIP بکاپ مجاز است.', 422);
        }

        try {
            $this->backups->restoreFromUpload($file);
        } catch (\Throwable $e) {
            $this->audit->log('backup.restore_failed', null, null, [
                'message' => $e->getMessage(),
            ]);

            return $this->errorResponse($e->getMessage() ?: 'بازگردانی بکاپ ناموفق بود.', 422);
        }

        $this->audit->log('backup.restored', null, null, [
            'via' => 'upload',
        ]);

        return $this->successResponse(null, 'بکاپ با موفقیت بازگردانی شد. صفحه را تازه کنید.');
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['path' => ['required', 'string']]);

        try {
            $this->backups->deleteBackup($data['path']);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        $this->audit->log('backup.deleted', null, null, [
            'file' => basename($data['path']),
        ]);

        return $this->successResponse(null, 'بکاپ حذف شد.');
    }
}
