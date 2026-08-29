<?php

namespace App\Services;

use App\Exports\QuestionsExport;
use App\Imports\QuestionsImport;
use App\Repositories\QuestionRepository;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Maatwebsite\Excel\Imports\HeadingRowFormatter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class QuestionService
{
    public function __construct(
        protected QuestionRepository $questionRepository
    ) {}

    /**
     * @return array{created: int, skipped: int, errors: array<int, string>}
     */
    public function importFromExcel(UploadedFile $file, ?int $examId = null): array
    {
        $import = new QuestionsImport($examId);

        // Keep Persian/English headers as-is; slug formatter turns «متن_سوال» into «mtn_soal».
        HeadingRowFormatter::default(HeadingRowFormatter::FORMATTER_NONE);
        try {
            Excel::import($import, $file);
        } finally {
            HeadingRowFormatter::reset();
        }

        return [
            'created' => $import->created,
            'skipped' => $import->skipped,
            'duplicates' => $import->duplicates,
            'errors' => $import->errors,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function exportToExcel(array $filters): BinaryFileResponse
    {
        $fileName = 'questions-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new QuestionsExport($filters), $fileName);
    }
}
