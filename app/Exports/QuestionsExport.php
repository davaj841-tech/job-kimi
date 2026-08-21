<?php

namespace App\Exports;

use App\Models\Question;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * @implements WithMapping<Question>
 */
class QuestionsExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  array<string, mixed>  $filters
     */
    public function __construct(
        protected array $filters = []
    ) {}

    /**
     * @return Builder<Question>
     */
    public function query(): Builder
    {
        $query = Question::query()->with('exam:id,slug')->latest();

        if (! empty($this->filters['exam_id'])) {
            $query->where('exam_id', $this->filters['exam_id']);
        }

        if (! empty($this->filters['subject'])) {
            $query->where('subject', $this->filters['subject']);
        }

        if (! empty($this->filters['difficulty'])) {
            $query->where('difficulty', $this->filters['difficulty']);
        }

        if (! empty($this->filters['search'])) {
            $query->where('question_text', 'like', '%'.$this->filters['search'].'%');
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return [
            'exam_slug',
            'question_text',
            'option_a',
            'option_b',
            'option_c',
            'option_d',
            'correct_answer',
            'explanation',
            'difficulty',
            'subject',
        ];
    }

    /**
     * @param  Question  $question
     * @return list<mixed>
     */
    public function map($question): array
    {
        return [
            $question->exam?->slug,
            $question->question_text,
            $question->option_a,
            $question->option_b,
            $question->option_c,
            $question->option_d,
            $question->correct_answer,
            $question->explanation,
            $question->difficulty,
            $question->subject,
        ];
    }
}
