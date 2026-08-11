<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\ExamStoreRequest;
use App\Http\Requests\Api\ExamUpdateRequest;
use App\Http\Resources\ExamCollection;
use App\Http\Resources\ExamResource;
use App\Models\Exam;
use App\Repositories\ExamRepository;
use App\Services\Exam\ExamCreationService;
use App\Services\Exam\ExamSubjectAssembler;
use App\Services\ExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends BaseController
{
    public function __construct(
        protected ExamRepository $examRepository,
        protected ExamService $examService,
        protected ExamSubjectAssembler $subjectAssembler,
        protected ExamCreationService $examCreationService,
    ) {}

    /**
     * فهرست آزمون‌ها
     *
     * لیست صفحه‌بندی‌شده آزمون‌های منتشرشده.
     *
     * @group آزمون‌ها
     *
     * @unauthenticated
     *
     * @queryParam category_id integer فیلتر بر اساس دسته‌بندی. Example: 1
     * @queryParam job_classification_id integer فیلتر رسته شغلی. Example: 2
     * @queryParam search string جستجو در عنوان. Example: استخدامی
     * @queryParam is_free boolean فقط آزمون‌های رایگان. Example: 1
     * @queryParam per_page integer تعداد در هر صفحه. Example: 15
     * @queryParam sort string مرتب‌سازی. Example: newest
     *
     * @response 200 {"success":true,"data":{"data":[],"links":{},"meta":{}}}
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only([
            'category_id', 'job_classification_id', 'is_free', 'access', 'search', 'per_page', 'sort',
        ]);
        // کاتالوگ عمومی فقط آزمون‌های منتشرشده
        $filters['status'] = 'published';
        $filters['user_id'] = $request->user()?->id;

        $exams = $this->examRepository->getPublished($filters);

        return $this->successResponse((new ExamCollection($exams))->resolve());
    }

    /**
     * جزئیات آزمون
     *
     * دریافت یک آزمون منتشرشده با اسلاگ.
     *
     * @group آزمون‌ها
     *
     * @unauthenticated
     *
     * @urlParam slug string required اسلاگ آزمون. Example: azmoon-estekhdami
     *
     * @response 200 {"success":true,"data":{"id":1,"title":"...","subjects":[]}}
     * @response 404 {"success":false,"message":"آزمون یافت نشد."}
     */
    public function show(string $slug): JsonResponse
    {
        $exam = $this->examRepository->findBySlug($slug);

        if (! $exam || $exam->status !== 'published') {
            return $this->errorResponse('آزمون یافت نشد.', 404);
        }

        $user = request()->user();
        $exam->user_best_score = $user ? $this->examRepository->userBestScore($user, $exam) : null;
        $exam->is_eligible = $user ? $this->examService->isEligible($user, $exam) : false;

        $data = (new ExamResource($exam))->resolve();
        $data['subjects'] = $this->subjectAssembler->assemble($exam, $user);

        return $this->successResponse($data);
    }

    /**
     * ایجاد آزمون
     *
     * ایجاد آزمون جدید (ادمین/اپراتور).
     *
     * @group آزمون‌ها
     *
     * @authenticated
     *
     * @response 201 {"success":true,"message":"آزمون ایجاد شد.","data":{}}
     * @response 403 {"success":false,"message":"دسترسی غیرمجاز."}
     */
    public function store(ExamStoreRequest $request): JsonResponse
    {
        $data = $this->examCreationService->prepareData($request->validated(), $request->user());
        $exam = Exam::query()->create($data);

        return $this->successResponse(
            new ExamResource($exam->load('category')->loadCount('questions')),
            'آزمون ایجاد شد.',
            201
        );
    }

    /**
     * به‌روزرسانی آزمون
     *
     * @group آزمون‌ها
     *
     * @authenticated
     *
     * @urlParam id integer required شناسه آزمون. Example: 1
     *
     * @response 200 {"success":true,"message":"آزمون به‌روزرسانی شد.","data":{}}
     * @response 404 {"success":false,"message":"آزمون یافت نشد."}
     */
    public function update(ExamUpdateRequest $request, int $id): JsonResponse
    {
        $exam = $this->examRepository->findById($id);

        if (! $exam) {
            return $this->errorResponse('آزمون یافت نشد.', 404);
        }

        $user = $request->user();
        if (! in_array($user->role, ['admin', 'operator'], true) && $exam->created_by !== $user->id) {
            return $this->errorResponse('دسترسی غیرمجاز.', 403);
        }

        $exam->update($request->validated());

        return $this->successResponse(new ExamResource($exam->fresh()->load('category')->loadCount('questions')), 'آزمون به‌روزرسانی شد.');
    }

    /**
     * بایگانی آزمون
     *
     * @group آزمون‌ها
     *
     * @authenticated
     *
     * @urlParam id integer required شناسه آزمون. Example: 1
     *
     * @response 200 {"success":true,"message":"آزمون بایگانی شد.","data":null}
     */
    public function destroy(int $id): JsonResponse
    {
        $exam = $this->examRepository->findById($id);

        if (! $exam) {
            return $this->errorResponse('آزمون یافت نشد.', 404);
        }

        // بایگانی به‌جای حذف فیزیکی
        $exam->update(['status' => 'archived']);

        return $this->successResponse(null, 'آزمون بایگانی شد.');
    }
}
