<?php

namespace App\Services\Content;

use App\Enums\Content\ContentStatus;
use App\Enums\Content\ContentType;
use App\Models\BlogPost;
use App\Models\GeneratedContent;
use App\Models\JobPost;
use App\Models\JobSource;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class ContentGeneratorService
{
    public function __construct(
        protected ContentTemplateService $templates,
        protected ContentRenderer $renderer,
        protected ContentDuplicateDetector $duplicates,
        protected ContentQualityService $quality,
        protected InternalLinkService $links,
    ) {}

    /**
     * @return array{created: int, updated: int, skipped: int, failed: int, published: int, errors: list<string>}
     */
    public function generateDaily(?Carbon $day = null, bool $force = false): array
    {
        $stats = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'failed' => 0, 'published' => 0, 'errors' => []];

        if (! $force && (! config('content.enabled', false) || ! config('content.daily_generation_enabled', false))) {
            $stats['errors'][] = 'content_generation_disabled';

            return $stats;
        }

        $tz = (string) config('content.timezone', 'Asia/Tehran');
        $dayLocal = ($day ?? now($tz))->timezone($tz);
        $dayKey = $dayLocal->toDateString();

        $lock = Cache::lock('content-generate-daily-'.$dayKey, 120);
        if (! $lock->get()) {
            $stats['skipped']++;
            $stats['errors'][] = 'daily_generation_locked';

            return $stats;
        }

        try {
            $max = max(1, (int) config('content.max_articles_per_day', 1));
            $producedToday = $this->countProducedOnLocalDay($dayLocal);
            $remaining = max(0, $max - $producedToday);
            if ($remaining === 0) {
                $stats['skipped']++;
                $stats['errors'][] = 'max_articles_per_day_reached';

                return $stats;
            }

            $jobs = $this->eligibleJobs()->take($remaining * 3);

            foreach ($jobs as $job) {
                if (($stats['created'] + $stats['updated']) >= $remaining) {
                    break;
                }

                $types = $this->templates->opportunitiesForJob($job);
                if ($types === []) {
                    $stats['skipped']++;
                    continue;
                }

                $type = $types[0];
                $result = $this->generateForJob($job, $type);
                $stats[$result['outcome']] = ($stats[$result['outcome']] ?? 0) + 1;
                if ($result['published']) {
                    $stats['published']++;
                }
                if ($result['error']) {
                    $stats['errors'][] = $result['error'];
                }
            }

            if (($stats['created'] + $stats['updated']) < $remaining) {
                $weekly = $this->generateWeeklySummary();
                if ($weekly) {
                    $stats[$weekly['outcome']] = ($stats[$weekly['outcome']] ?? 0) + 1;
                    if ($weekly['published']) {
                        $stats['published']++;
                    }
                    if ($weekly['error']) {
                        $stats['errors'][] = $weekly['error'];
                    }
                }
            }

            return $stats;
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @return array{outcome: string, published: bool, content: ?GeneratedContent, error: ?string}
     */
    public function generateForJob(JobPost $job, ?ContentType $type = null): array
    {
        $job->loadMissing('source');

        if ($job->status !== 'approved') {
            return $this->outcome('skipped', false, null, 'job_not_approved');
        }
        if (! filled($job->company_name) || ! filled($job->title)) {
            return $this->outcome('skipped', false, null, 'missing_org_or_title');
        }
        if (! $job->source instanceof JobSource || ! $this->quality->sourceAllowed($job->source)) {
            return $this->outcome('skipped', false, null, 'source_not_allowed');
        }

        $type ??= ($this->templates->opportunitiesForJob($job)[0] ?? null);
        if (! $type instanceof ContentType) {
            return $this->outcome('skipped', false, null, 'no_opportunity');
        }
        if (! $this->quality->hasEnoughFactualFields($job, $type)) {
            return $this->outcome('skipped', false, null, 'insufficient_factual_data');
        }

        $template = $this->templates->enabledFor($type);
        if (! $template) {
            return $this->outcome('failed', false, null, 'template_missing:'.$type->value);
        }

        $lock = Cache::lock('content-gen-job-'.$job->id.'-'.$type->value, 60);
        if (! $lock->get()) {
            return $this->outcome('skipped', false, null, 'job_generation_locked');
        }

        try {
            $context = $this->renderer->contextFromJob($job);
            $title = $this->renderer->render($template->title_template, $context);
            $body = $this->renderer->render($template->content_template, $context);
            $body = $this->links->appendLinks($body, $job);
            $excerpt = Str::limit(trim(preg_replace('/\s+/u', ' ', strip_tags($body)) ?? ''), 220, '…');
            $hash = $this->duplicates->hash($title, $body, $type, $job->id);

            $content = null;
            $outcome = 'created';

            try {
                $content = DB::transaction(function () use ($job, $type, $title, $body, $excerpt, $hash, &$outcome) {
                    $existing = GeneratedContent::query()
                        ->where('job_post_id', $job->id)
                        ->where('content_type', $type->value)
                        ->lockForUpdate()
                        ->first();

                    if ($existing && $existing->content_hash === $hash) {
                        $outcome = 'skipped';

                        return $existing;
                    }

                    if (! $existing && $this->duplicates->hashExists($hash)) {
                        $outcome = 'skipped';

                        return null;
                    }

                    $slug = $existing?->slug ?: $this->uniqueSlug($title, $job->id, $type);
                    $content = $existing ?? new GeneratedContent;
                    $content->fill([
                        'title' => $title,
                        'slug' => $slug,
                        'excerpt' => $excerpt,
                        'content' => $body,
                        'content_type' => $type,
                        'source_type' => JobSource::class,
                        'source_id' => $job->job_source_id,
                        'job_post_id' => $job->id,
                        'content_hash' => $hash,
                        'generation_attempts' => (int) ($content->generation_attempts ?? 0) + 1,
                        'last_error' => null,
                        'metadata' => [
                            'organization' => $job->company_name,
                            'source_name' => $job->source?->name,
                            'source_id' => $job->job_source_id,
                            'generated_at' => now()->toIso8601String(),
                            'factual_fields' => [
                                'deadline' => (bool) $job->registration_deadline,
                                'exam_date' => (bool) $job->exam_date,
                                'has_link' => filled($job->registration_link) || filled($job->source_url),
                            ],
                        ],
                    ]);

                    if (! $content->exists || ! $content->status) {
                        $content->status = ContentStatus::Draft;
                    }

                    $validation = $this->quality->validate($content, $job);
                    if (! $validation['valid']) {
                        $content->status = ContentStatus::Failed;
                        $content->last_error = implode('; ', $validation['errors']);
                        $content->save();
                        $outcome = 'failed';

                        return $content;
                    }

                    // Persist locally first — external publish happens after commit.
                    if ($content->status !== ContentStatus::Failed) {
                        $content->status = ContentStatus::Draft;
                    }
                    $content->save();
                    $outcome = $existing ? 'updated' : 'created';

                    return $content;
                });
            } catch (QueryException $e) {
                // Unique (job_post_id, content_type) race — update winner row.
                if ($this->isUniqueViolation($e)) {
                    $existing = GeneratedContent::query()
                        ->where('job_post_id', $job->id)
                        ->where('content_type', $type->value)
                        ->first();
                    if (! $existing) {
                        return $this->outcome('failed', false, null, 'unique_race');
                    }
                    if ($existing->content_hash === $hash) {
                        return $this->outcome('skipped', false, $existing, 'duplicate_unchanged');
                    }
                    $existing->fill([
                        'title' => $title,
                        'excerpt' => $excerpt,
                        'content' => $body,
                        'content_hash' => $hash,
                        'generation_attempts' => (int) $existing->generation_attempts + 1,
                        'last_error' => null,
                        'source_id' => $job->job_source_id,
                    ]);
                    $validation = $this->quality->validate($existing, $job);
                    if (! $validation['valid']) {
                        $existing->status = ContentStatus::Failed;
                        $existing->last_error = implode('; ', $validation['errors']);
                        $existing->save();

                        return $this->outcome('failed', false, $existing, $existing->last_error);
                    }
                    $existing->status = ContentStatus::Draft;
                    $existing->save();
                    $published = $this->applyPublishMode($existing, (string) config('content.publish_mode', 'draft'));
                    $existing->save();

                    return $this->outcome('updated', $published, $existing, null);
                }
                Log::warning('Content generation DB error', [
                    'job_post_id' => $job->id,
                    'content_type' => $type->value,
                    'message' => $e->getMessage(),
                ]);

                return $this->outcome('failed', false, null, 'db_error');
            } catch (Throwable $e) {
                Log::warning('Content generation failed', [
                    'job_post_id' => $job->id,
                    'content_type' => $type->value,
                    'message' => $e->getMessage(),
                ]);

                return $this->outcome('failed', false, null, $e->getMessage());
            }

            if ($outcome === 'skipped') {
                return $this->outcome('skipped', false, $content, $content ? 'duplicate_unchanged' : 'duplicate_hash');
            }
            if ($outcome === 'failed' || ! $content) {
                return $this->outcome('failed', false, $content, $content?->last_error ?? 'generation_failed');
            }

            $mode = (string) config('content.publish_mode', 'draft');
            $published = $this->applyPublishMode($content, $mode);
            $content->save();

            Log::info('Content generated', [
                'generated_content_id' => $content->id,
                'job_post_id' => $job->id,
                'content_type' => $type->value,
                'source_id' => $job->job_source_id,
                'outcome' => $outcome,
                'status' => $content->status instanceof ContentStatus ? $content->status->value : $content->status,
            ]);

            return $this->outcome($outcome, $published, $content, null);
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @return array{outcome: string, published: bool, content: ?GeneratedContent, error: ?string}|null
     */
    public function generateWeeklySummary(): ?array
    {
        $type = ContentType::WeeklyRecruitmentSummary;
        $template = $this->templates->enabledFor($type);
        if (! $template) {
            return null;
        }

        $tz = (string) config('content.timezone', 'Asia/Tehran');
        $slugKey = 'weekly-'.now($tz)->format('o-\WW');
        $lock = Cache::lock('content-weekly-'.$slugKey, 60);
        if (! $lock->get()) {
            return $this->outcome('skipped', false, null, 'weekly_locked');
        }

        try {
            $existing = GeneratedContent::query()
                ->where('content_type', $type->value)
                ->where('slug', 'like', $slugKey.'%')
                ->first();
            if ($existing) {
                return $this->outcome('skipped', false, $existing, 'weekly_exists');
            }

            $since = now($tz)->subDays(7);
            $jobs = JobPost::query()
                ->with('source')
                ->where('status', 'approved')
                ->where('updated_at', '>=', $since)
                ->whereHas('source', fn ($q) => $q->where('is_enabled', true)->where('is_approved', true))
                ->latest('id')
                ->limit(20)
                ->get()
                ->filter(fn (JobPost $j) => $j->source && $this->quality->sourceAllowed($j->source))
                ->values();

            if ($jobs->count() < 2) {
                return $this->outcome('skipped', false, null, 'weekly_insufficient');
            }

            $lines = $jobs->map(function (JobPost $j) {
                $line = '• '.$j->company_name.' — '.$j->title;
                if ($j->registration_deadline) {
                    $line .= ' (مهلت: '.$this->renderer->contextFromJob($j)['registration_deadline'].')';
                }

                return $this->renderer->e($line);
            });
            $listHtml = $lines->implode('<br>');

            $context = [
                'organization' => 'خلاصه هفتگی',
                'title' => 'فرصت‌های استخدامی هفته',
                'province' => '',
                'city' => '',
                'education' => '',
                'field_of_study' => '',
                'experience' => '',
                'employment_type' => '',
                'job_category' => '',
                'requirements' => '',
                'description' => '',
                'registration_starts_at' => '',
                'registration_deadline' => '',
                'exam_date' => '',
                'published_at' => now($tz)->format('Y/m/d'),
                'registration_link' => '',
                'source_url' => '',
                'source_name' => 'جاب‌آزمون',
                'source_domain' => '',
                'weekly_list_html' => $listHtml,
                'week_count' => (string) $jobs->count(),
            ];

            $title = $this->renderer->render($template->title_template, $context);
            // Seeder still uses {weekly_list} — map to html key
            $tpl = str_replace('{weekly_list}', '{weekly_list_html}', $template->content_template);
            $body = $this->renderer->render($tpl, $context);
            $hash = $this->duplicates->hash($title, $body, $type, null);

            try {
                $content = GeneratedContent::query()->create([
                    'title' => $title,
                    'slug' => $slugKey.'-'.Str::lower(Str::random(4)),
                    'excerpt' => Str::limit(strip_tags($body), 220, '…'),
                    'content' => $body,
                    'content_type' => $type,
                    'status' => ContentStatus::Draft,
                    'source_type' => 'weekly',
                    'content_hash' => $hash,
                    'generation_attempts' => 1,
                    'metadata' => ['job_ids' => $jobs->pluck('id')->all()],
                ]);
            } catch (QueryException $e) {
                if ($this->isUniqueViolation($e)) {
                    return $this->outcome('skipped', false, null, 'weekly_race');
                }
                throw $e;
            }

            if (preg_match('/\{[a-z0-9_]+\}/u', $title.$body)
                || mb_strlen(strip_tags($body)) < (int) config('content.minimum_content_length', 280)) {
                $content->update(['status' => ContentStatus::Failed, 'last_error' => 'weekly_quality_failed']);

                return $this->outcome('failed', false, $content, 'weekly_quality_failed');
            }

            $published = $this->applyPublishMode($content, (string) config('content.publish_mode', 'draft'));
            $content->save();

            return $this->outcome('created', $published, $content, null);
        } finally {
            optional($lock)->release();
        }
    }

    /**
     * @return Collection<int, JobPost>
     */
    public function eligibleJobs(): Collection
    {
        $lookback = max(1, (int) config('content.lookback_days', 14));

        return JobPost::query()
            ->with('source')
            ->where('status', 'approved')
            ->where('updated_at', '>=', now()->subDays($lookback))
            ->whereNotNull('job_source_id')
            ->whereNotNull('company_name')
            ->whereNotNull('title')
            ->whereHas('source', function ($q) {
                $q->where('is_enabled', true)->where('is_approved', true);
            })
            ->latest('updated_at')
            ->limit(100)
            ->get()
            ->filter(fn (JobPost $j) => $j->source
                && $this->quality->sourceAllowed($j->source)
                && $this->quality->hasEnoughFactualFields($j))
            ->values();
    }

    public function publishContent(GeneratedContent $content): bool
    {
        $ok = $this->applyPublishMode($content, 'publish');
        $content->save();

        return $ok;
    }

    public function unpublishContent(GeneratedContent $content): bool
    {
        $content->status = ContentStatus::Draft;
        $content->published_at = null;
        if ($content->blog_post_id) {
            BlogPost::query()->where('id', $content->blog_post_id)->update(['status' => 'draft']);
        }
        $content->save();

        return true;
    }

    public function publishScheduled(int $limit = 20): array
    {
        $stats = ['published' => 0, 'failed' => 0];
        $items = GeneratedContent::query()
            ->where('status', ContentStatus::Scheduled->value)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->limit(max(1, min(100, $limit)))
            ->get();

        foreach ($items as $item) {
            try {
                if ($this->publishContent($item)) {
                    $stats['published']++;
                } else {
                    $stats['failed']++;
                }
            } catch (Throwable $e) {
                Log::warning('publishScheduled failed', [
                    'generated_content_id' => $item->id,
                    'message' => $e->getMessage(),
                ]);
                $stats['failed']++;
            }
        }

        return $stats;
    }

    public function publishPending(int $limit = 20): array
    {
        // Backward-compatible alias: publish due scheduled items, then eligible drafts when mode is publish.
        $stats = $this->publishScheduled($limit);
        $remaining = max(0, $limit - $stats['published']);
        if ($remaining <= 0 || (string) config('content.publish_mode', 'draft') !== 'publish') {
            return $stats;
        }

        $drafts = GeneratedContent::query()
            ->where('status', ContentStatus::Draft->value)
            ->where(function ($q) {
                $q->whereNull('scheduled_for')->orWhere('scheduled_for', '<=', now());
            })
            ->orderBy('id')
            ->limit($remaining)
            ->get();

        foreach ($drafts as $item) {
            try {
                $this->publishContent($item) ? $stats['published']++ : $stats['failed']++;
            } catch (Throwable) {
                $stats['failed']++;
            }
        }

        return $stats;
    }

    public function countProducedOnLocalDay(Carbon $dayLocal): int
    {
        $start = $dayLocal->copy()->startOfDay();
        $end = $dayLocal->copy()->endOfDay();

        return GeneratedContent::query()
            ->whereBetween('created_at', [$start, $end])
            ->whereNotIn('status', [ContentStatus::Skipped->value, ContentStatus::Failed->value])
            ->count();
    }

    /**
     * Laravel-only publish: update GeneratedContent status + optional BlogPost mirror.
     * Never contacts external CMS / WordPress.
     */
    protected function applyPublishMode(GeneratedContent $content, string $mode): bool
    {
        $publish = in_array($mode, ['publish', 'local_blog'], true);

        if (config('content.sync_to_blog', true) && ($publish || $mode === 'local_blog' || $mode === 'draft')) {
            try {
                $blogStatus = $publish ? 'published' : 'draft';
                $blog = $content->blog_post_id
                    ? BlogPost::query()->find($content->blog_post_id)
                    : null;

                $payload = [
                    'title' => $content->title,
                    'slug' => $content->slug,
                    'content' => $content->content,
                    'excerpt' => $content->excerpt,
                    'category' => config('content.blog_category', 'استخدام'),
                    'meta_title' => $content->title,
                    'meta_description' => $content->excerpt,
                    'status' => $blogStatus,
                    'created_by' => $this->systemAuthorId(),
                ];

                if ($blog) {
                    unset($payload['created_by']);
                    $blog->update($payload);
                } else {
                    $blog = BlogPost::query()->create($payload);
                    $content->blog_post_id = $blog->id;
                }
            } catch (Throwable $e) {
                $content->last_error = 'blog_sync: '.$e->getMessage();
                Log::warning('Blog sync failed', [
                    'generated_content_id' => $content->id,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        if ($publish) {
            $content->status = ContentStatus::Published;
            $content->published_at = $content->published_at ?: now();

            return true;
        }

        $content->status = ContentStatus::Draft;

        return false;
    }

    protected function systemAuthorId(): int
    {
        $configured = (int) config('content.system_author_id', 0);
        if ($configured > 0) {
            return $configured;
        }

        $adminId = (int) User::query()->where('role', 'admin')->orderBy('id')->value('id');
        if ($adminId > 0) {
            return $adminId;
        }

        $anyId = (int) User::query()->orderBy('id')->value('id');
        if ($anyId > 0) {
            return $anyId;
        }

        $user = User::query()->create([
            'name' => 'Content System',
            'email' => 'content-system@jobazmoon.local',
            'mobile' => '09000000000',
            'password' => bcrypt(str()->random(32)),
            'role' => 'admin',
            'status' => 'active',
            'is_verified' => true,
        ]);

        return (int) $user->id;
    }

    protected function uniqueSlug(string $title, int $jobId, ContentType $type): string
    {
        $base = Str::slug($title);
        if ($base === '') {
            $base = 'job-'.$jobId.'-'.Str::lower($type->value);
        }
        $slug = Str::limit($base, 140, '').'-'.$jobId;
        $i = 1;
        while ($this->duplicates->slugExists($slug)) {
            $slug = Str::limit($base, 120, '').'-'.$jobId.'-'.$i;
            $i++;
            if ($i > 50) {
                $slug = 'job-'.$jobId.'-'.Str::lower($type->value).'-'.Str::lower(Str::random(6));
                break;
            }
        }

        return $slug;
    }

    protected function isUniqueViolation(QueryException $e): bool
    {
        $msg = Str::lower($e->getMessage());

        return str_contains($msg, 'unique')
            || (string) $e->getCode() === '23000'
            || str_contains($msg, 'duplicate');
    }

    /**
     * @return array{outcome: string, published: bool, content: ?GeneratedContent, error: ?string}
     */
    protected function outcome(string $outcome, bool $published, ?GeneratedContent $content, ?string $error): array
    {
        return compact('outcome', 'published', 'content', 'error');
    }
}
