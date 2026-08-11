<?php

namespace App\Services\Content;

use App\Enums\Content\ContentType;
use App\Models\ContentTemplate;
use App\Models\JobPost;
use Illuminate\Support\Collection;

class ContentTemplateService
{
    public function enabledFor(ContentType $type): ?ContentTemplate
    {
        return ContentTemplate::query()
            ->where('content_type', $type->value)
            ->where('enabled', true)
            ->orderByDesc('priority')
            ->orderBy('id')
            ->first();
    }

    /**
     * @return list<ContentType>
     */
    public function opportunitiesForJob(JobPost $job): array
    {
        $types = [];

        $hasOrg = filled($job->company_name);
        $hasTitle = filled($job->title);
        $hasDeadline = filled($job->registration_deadline);
        $hasStart = filled($job->registration_starts_at);
        $hasExam = filled($job->exam_date);
        $hasLink = filled($job->registration_link) || filled($job->source_url);
        $hasReq = filled($job->requirements);
        $hasField = filled($job->field_of_study);
        $hasProvince = filled($job->province) || (is_array($job->provinces) && $job->provinces !== []);
        $hasAgeHint = $hasReq && preg_match('/سن|سالگی|حداکثر سن|حداقل سن/u', (string) $job->requirements);
        $hasDocsHint = $hasReq && preg_match('/مدرک|مدارک|شناسنامه|کارت ملی|گواهی/u', (string) $job->requirements);

        if ($hasOrg && $hasTitle && ($hasExam || $hasDeadline || $hasLink)) {
            $types[] = ContentType::NewJobExam;
        }
        if ($hasOrg && ($hasStart || $hasDeadline || $hasLink)) {
            $types[] = ContentType::RegistrationGuide;
        }
        if ($hasDeadline) {
            $types[] = ContentType::RegistrationDeadline;
        }
        if ($hasOrg && ($hasExam || $hasDeadline || $hasReq)) {
            $types[] = ContentType::JobExamSummary;
        }
        if ($hasOrg) {
            $types[] = ContentType::OrganizationRecruitment;
        }
        if ($hasProvince) {
            $types[] = ContentType::ProvinceRecruitmentSummary;
        }
        if ($hasField) {
            $types[] = ContentType::FieldOfStudyRecruitment;
        }
        if ($hasAgeHint) {
            $types[] = ContentType::AgeRequirements;
        }
        if ($hasDocsHint) {
            $types[] = ContentType::RequiredDocuments;
        }

        return $types;
    }

    /**
     * @return Collection<int, ContentTemplate>
     */
    public function allEnabled(): Collection
    {
        return ContentTemplate::query()
            ->where('enabled', true)
            ->orderByDesc('priority')
            ->get();
    }
}
