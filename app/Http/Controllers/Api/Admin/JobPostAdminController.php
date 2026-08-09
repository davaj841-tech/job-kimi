<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\BaseController;
use App\Http\Requests\Api\JobPostStoreRequest;
use App\Http\Resources\JobPostCollection;
use App\Http\Resources\JobPostResource;
use App\Imports\JobPostsImport;
use App\Repositories\JobPostRepository;
use App\Services\JobPostService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobPostAdminController extends BaseController
{
    public function __construct(
        protected JobPostRepository $jobPostRepository,
        protected JobPostService $jobPostService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'status', 'search', 'per_page', 'province', 'city', 'job_classification_id', 'deadline_from', 'deadline_to',
        ]);
        $posts = $this->jobPostRepository->getAdminList($filters);

        return $this->successResponse(new JobPostCollection($posts));
    }

    public function filterOptions(): JsonResponse
    {
        return $this->successResponse($this->jobPostRepository->getAdminFilterOptions());
    }

    public function show(int $id): JsonResponse
    {
        $jobPost = $this->jobPostRepository->findById($id);

        if (! $jobPost) {
            return $this->errorResponse('آگهی یافت نشد.', 404);
        }

        return $this->successResponse(new JobPostResource($jobPost));
    }

    public function store(JobPostStoreRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['status'] = $data['status'] ?? 'approved';
        $data['created_by'] = $request->user()->id;
        if (($data['status'] ?? null) === 'approved') {
            $data['approved_by'] = $request->user()->id;
        }
        $data['is_featured'] = $data['is_featured'] ?? false;

        $jobPost = $this->jobPostService->create($data, $this->collectAttachments($request));

        return $this->successResponse(
            new JobPostResource($jobPost->load(['creator', 'approver', 'classification', 'attachments'])),
            'آگهی ایجاد شد.',
            201
        );
    }

    public function update(JobPostStoreRequest $request, int $id): JsonResponse
    {
        $jobPost = $this->jobPostRepository->findById($id);

        if (! $jobPost) {
            return $this->errorResponse('آگهی یافت نشد.', 404);
        }

        $updated = $this->jobPostService->update($jobPost, $request->validated(), $this->collectAttachments($request));

        return $this->successResponse(new JobPostResource($updated), 'آگهی به‌روزرسانی شد.');
    }

    public function destroy(int $id): JsonResponse
    {
        $jobPost = $this->jobPostRepository->findById($id);

        if (! $jobPost) {
            return $this->errorResponse('آگهی یافت نشد.', 404);
        }

        $this->jobPostService->forceDelete($jobPost);

        return $this->successResponse(null, 'آگهی حذف شد.');
    }

    public function approve(int $id): JsonResponse
    {
        try {
            $jobPost = $this->jobPostService->approve($id, auth()->id());
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->successResponse(new JobPostResource($jobPost), 'آگهی تایید شد.');
    }

    public function reject(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ], [
            'reason.max' => 'دلیل رد نباید بیشتر از ۱۰۰۰ کاراکتر باشد.',
        ]);

        try {
            $jobPost = $this->jobPostService->reject($id, $request->input('reason'));
        } catch (\RuntimeException $e) {
            return $this->errorResponse($e->getMessage(), 404);
        }

        return $this->successResponse(new JobPostResource($jobPost), 'آگهی رد شد.');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
        ], [
            'file.required' => 'انتخاب فایل اکسل الزامی است.',
            'file.mimes' => 'فرمت فایل باید xlsx، xls یا csv باشد.',
        ]);

        $import = new JobPostsImport($request->user()->id);
        Excel::import($import, $request->file('file'));

        return $this->successResponse([
            'created' => $import->created,
            'skipped' => $import->skipped,
            'errors' => $import->errors,
        ], 'ورود اکسل انجام شد.');
    }

    public function importSample(): StreamedResponse
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('آگهی‌ها');

        $headers = [
            'title',
            'seo_tag',
            'classification',
            'description',
            'provinces',
            'city',
            'registration_deadline',
            'exam_date',
            'registration_link',
            'is_featured',
        ];

        foreach ($headers as $i => $header) {
            $sheet->setCellValue([$i + 1, 1], $header);
        }

        $sheet->fromArray([
            [
                'استخدام بانک ملت ۱۴۰۵',
                'استخدام_بانک_ملت_1405',
                'بانک‌ها',
                'شرح کامل آگهی استخدام',
                'تهران،اصفهان',
                'تهران',
                '2026-09-01',
                '2026-10-15',
                'https://example.com/register',
                '1',
            ],
            [
                'استخدام آموزش و پرورش',
                'استخدام_آموزش_و_پرورش_1405',
                'استخدام آموزش و پرورش',
                'توضیحات نمونه',
                'فارس',
                '',
                '2026-08-20',
                '',
                '',
                '0',
            ],
        ], null, 'A2');

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'job-posts-import-sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** @return array<int, array{file:\Illuminate\Http\UploadedFile,title:?string,description:?string}> */
    protected function collectAttachments(Request $request): array
    {
        $files = $request->file('attachments', []);
        if (! is_array($files)) {
            $files = $files ? [$files] : [];
        }

        $titles = $request->input('attachment_titles', []);
        $descriptions = $request->input('attachment_descriptions', []);
        $items = [];

        foreach ($files as $i => $file) {
            if (! $file) {
                continue;
            }
            $items[] = [
                'file' => $file,
                'title' => is_array($titles) ? ($titles[$i] ?? null) : null,
                'description' => is_array($descriptions) ? ($descriptions[$i] ?? null) : null,
            ];
        }

        // Backward-compatible single attachment field
        if ($request->file('attachment')) {
            $items[] = [
                'file' => $request->file('attachment'),
                'title' => $request->input('attachment_title'),
                'description' => $request->input('attachment_description'),
            ];
        }

        return $items;
    }
}
