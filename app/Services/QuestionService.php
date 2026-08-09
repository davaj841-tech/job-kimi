<?php

namespace App\Services;

use App\Exports\QuestionsExport;
use App\Imports\QuestionsImport;
use App\Repositories\QuestionRepository;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
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
        Excel::import($import, $file);

        return [
            'created' => $import->created,
            'skipped' => $import->skipped,
            'errors' => $import->errors,
        ];
    }

    public function exportToExcel(array $filters): BinaryFileResponse
    {
        $fileName = 'questions-'.now()->format('Ymd-His').'.xlsx';

        return Excel::download(new QuestionsExport($filters), $fileName);
    }
}
