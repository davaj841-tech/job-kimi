<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\BaseController;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\ExamRating;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ExamRatingController extends BaseController
{
    public function store(Request $request, int $id): JsonResponse
    {
        $exam = Exam::query()->findOrFail($id);
        $data = $request->validate([
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
        ]);

        $completed = ExamAttempt::query()
            ->where('exam_id', $exam->id)
            ->where('user_id', $request->user()->id)
            ->where('status', 'completed')
            ->exists();

        if (! $completed) {
            return $this->errorResponse('فقط پس از تکمیل آزمون می‌توانید امتیاز دهید.', 422);
        }

        DB::transaction(function () use ($exam, $request, $data) {
            ExamRating::query()->updateOrCreate(
                ['exam_id' => $exam->id, 'user_id' => $request->user()->id],
                ['rating' => $data['rating']]
            );

            $avg = ExamRating::query()->where('exam_id', $exam->id)->avg('rating');
            $count = ExamRating::query()->where('exam_id', $exam->id)->count();
            $exam->update([
                'avg_rating' => round((float) $avg, 2),
                'ratings_count' => $count,
            ]);
        });

        return $this->successResponse([
            'avg_rating' => (float) $exam->fresh()->avg_rating,
            'ratings_count' => (int) $exam->fresh()->ratings_count,
        ], 'امتیاز ثبت شد.');
    }
}
