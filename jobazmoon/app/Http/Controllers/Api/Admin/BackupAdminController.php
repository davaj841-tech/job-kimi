<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Jobs\CreateBackupJob;
use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupAdminController extends BaseController
{
    public function __construct(protected BackupService $backups) {}

    public function index(): JsonResponse
    {
        return $this->successResponse($this->backups->listBackups());
    }

    public function store(): JsonResponse
    {
        CreateBackupJob::dispatch();

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

        return response()->download($full, basename($full));
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate(['path' => ['required', 'string']]);

        try {
            $this->backups->deleteBackup($data['path']);
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->successResponse(null, 'بکاپ حذف شد.');
    }
}
