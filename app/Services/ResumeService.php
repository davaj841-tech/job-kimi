<?php

namespace App\Services;

use App\Models\Resume;
use App\Rules\ResumeDataRule;
use Illuminate\Support\Facades\Validator;

class ResumeService
{
    public function __construct(
        protected ResumePDFService $resumePDFService
    ) {}

    /**

     * @param  array<string, mixed>  $payload

     */

    public function create(array $payload): Resume
    {
        $data = $payload['data'];
        $title = $payload['title'] ?? null;

        if (blank($title)) {
            $name = data_get($data, 'personal.full_name', 'رزومه');
            $job = data_get($data, 'target_job');
            $title = $job ? "{$name} - {$job}" : $name;
        }

        return Resume::query()->create([
            'user_id' => $payload['user_id'],
            'title' => $title,
            'data' => $data,
            'template_id' => (int) ($payload['template_id'] ?? 1),
            'is_active' => true,
        ]);
    }

    /**

     * @param  array<string, mixed>  $payload

     */

    public function update(Resume $resume, array $payload): Resume
    {
        $updates = [];

        if (array_key_exists('title', $payload) && filled($payload['title'])) {
            $updates['title'] = $payload['title'];
        }

        if (array_key_exists('template_id', $payload)) {
            $updates['template_id'] = (int) $payload['template_id'];
        }

        if (array_key_exists('data', $payload) && is_array($payload['data'])) {
            $updates['data'] = $payload['data'];

            if (blank($payload['title'] ?? null) && blank($resume->title)) {
                $name = data_get($payload['data'], 'personal.full_name', 'رزومه');
                $job = data_get($payload['data'], 'target_job');
                $updates['title'] = $job ? "{$name} - {$job}" : $name;
            }
        }

        if (array_key_exists('is_active', $payload)) {
            $updates['is_active'] = (bool) $payload['is_active'];
        }

        if ($updates !== []) {
            $resume->update($updates);
        }

        return $resume->fresh();
    }

    public function generatePDF(Resume $resume): string
    {
        return $this->resumePDFService->generatePDF($resume);
    }

    public function switchTemplate(Resume $resume, int $templateId): Resume
    {
        $resume->update(['template_id' => $templateId]);
        $this->resumePDFService->generatePDF($resume->fresh());

        return $resume->fresh();
    }

    /**

     * @param  array<string, mixed>  $data

     */

    public function validateData(array $data): bool
    {
        $validator = Validator::make(
            ['data' => $data],
            ['data' => [new ResumeDataRule]]
        );

        return ! $validator->fails();
    }
}
