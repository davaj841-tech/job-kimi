<?php

namespace App\Http\Controllers\Api;

use App\Http\Requests\Api\QuestionStoreRequest;
use App\Http\Requests\Api\QuestionUpdateRequest;
use App\Http\Resources\QuestionResource;
use App\Models\Exam;
use App\Models\Question;
use App\Repositories\QuestionRepository;
use App\Services\AuditLogService;
use App\Services\QuestionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class QuestionController extends BaseController
{
    public function __construct(
        protected QuestionRepository $questionRepository,
        protected QuestionService $questionService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $questions = $this->questionRepository->getFiltered($request->only([
            'exam_id', 'subject', 'difficulty', 'search', 'question_type', 'per_page',
        ]));

        $items = collect($questions->items())->map(function (Question $q) {
            return [
                'id' => $q->id,
                'exam_id' => $q->exam_id,
                'exam_title' => $q->exam?->title,
                'question_text' => $q->question_text,
                'question_type' => $q->question_type,
                'option_a' => $q->option_a,
                'option_b' => $q->option_b,
                'option_c' => $q->option_c,
                'option_d' => $q->option_d,
                'correct_answer' => $q->correct_answer,
                'explanation' => $q->explanation,
                'difficulty' => $q->difficulty,
                'subject' => $q->subject,
                'created_at' => $q->created_at?->toIso8601String(),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'message' => 'عملیات موفق',
            'data' => $items,
            'meta' => [
                'current_page' => $questions->currentPage(),
                'last_page' => $questions->lastPage(),
                'per_page' => $questions->perPage(),
                'total' => $questions->total(),
                'from' => $questions->firstItem(),
                'to' => $questions->lastItem(),
            ],
        ]);
    }

    public function show(int $question): JsonResponse
    {
        $model = $this->questionRepository->find($question);

        if (! $model) {
            return $this->errorResponse('سوال یافت نشد.', 404);
        }

        $model->load('exam:id,title');

        return $this->successResponse(new QuestionResource($model));
    }

    public function store(QuestionStoreRequest $request): JsonResponse
    {
        $model = Question::query()->create($request->validated());
        Exam::query()->whereKey($model->exam_id)->increment('total_questions');
        app(AuditLogService::class)->log('question.created', $model, null, [
            'exam_id' => $model->exam_id,
            'subject' => $model->subject,
            'difficulty' => $model->difficulty,
        ]);

        return $this->successResponse(new QuestionResource($model), 'سوال ایجاد شد.', 201);
    }

    public function update(QuestionUpdateRequest $request, int $question): JsonResponse
    {
        $model = $this->questionRepository->find($question);

        if (! $model) {
            return $this->errorResponse('سوال یافت نشد.', 404);
        }

        $model->update($request->validated());

        return $this->successResponse(new QuestionResource($model->fresh()), 'سوال به‌روزرسانی شد.');
    }

    public function destroy(int $question): JsonResponse
    {
        $model = $this->questionRepository->find($question);

        if (! $model) {
            return $this->errorResponse('سوال یافت نشد.', 404);
        }

        $examId = $model->exam_id;
        $model->delete();
        Exam::query()->whereKey($examId)->where('total_questions', '>', 0)->decrement('total_questions');

        return $this->successResponse(null, 'سوال حذف شد.');
    }

    public function import(Request $request): JsonResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv'],
            'exam_id' => ['required', 'integer', 'exists:exams,id'],
        ], [
            'exam_id.required' => 'ابتدا آزمون مورد نظر را انتخاب کنید.',
        ]);

        $summary = $this->questionService->importFromExcel(
            $request->file('file'),
            (int) $request->input('exam_id')
        );

        app(AuditLogService::class)->log('question.imported', null, null, [
            'exam_id' => (int) $request->input('exam_id'),
            'created' => $summary['created'] ?? 0,
            'skipped' => $summary['skipped'] ?? 0,
        ]);

        return $this->successResponse($summary, 'ورود اکسل انجام شد.');
    }

    public function importSample(Request $request)
    {
        $format = strtolower((string) $request->query('format', 'xlsx'));
        if (! in_array($format, ['xlsx', 'csv'], true)) {
            $format = 'xlsx';
        }

        $headers = [
            'question_text', 'option_a', 'option_b', 'option_c', 'option_d',
            'correct_answer', 'explanation', 'difficulty', 'subject',
        ];
        $rows = [
            ['نمونه سوال آسان؟', 'گزینه الف', 'گزینه ب', 'گزینه ج', 'گزینه د', 'الف', 'توضیح پاسخ آسان', 'آسان', 'general'],
            ['نمونه سوال متوسط؟', 'گزینه الف', 'گزینه ب', 'گزینه ج', 'گزینه د', 'ب', 'توضیح پاسخ متوسط', '', 'math'],
            ['نمونه سوال سخت؟', 'گزینه الف', 'گزینه ب', 'گزینه ج', 'گزینه د', 'ج', 'توضیح پاسخ سخت', 'سخت', 'chemistry'],
        ];

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headers, $rows) {
                $out = fopen('php://output', 'w');
                if ($out === false) {
                    return;
                }
                // UTF-8 BOM for Excel
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $headers);
                foreach ($rows as $row) {
                    fputcsv($out, $row);
                }
                fclose($out);
            }, 'questions-import-sample.csv', [
                'Content-Type' => 'text/csv; charset=UTF-8',
            ]);
        }

        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('سوالات');
        foreach ($headers as $i => $h) {
            $sheet->setCellValue([$i + 1, 1], $h);
        }
        $sheet->fromArray($rows, null, 'A2');

        $writer = new Xlsx($spreadsheet);

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, 'questions-import-sample.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    public function export(Request $request)
    {
        return $this->questionService->exportToExcel($request->only([
            'exam_id', 'subject', 'difficulty', 'search',
        ]));
    }
}
