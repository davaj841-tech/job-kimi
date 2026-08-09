<?php

namespace App\Services\Aggregation;

use App\Enums\Aggregation\CrawlerRunStatus;
use App\Models\CrawlerError;
use App\Models\CrawlerRun;
use App\Models\JobSource;
use App\Services\Aggregation\Support\PersianText;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CrawlOrchestrator
{
    public function __construct(
        protected CrawlerResolver $resolver,
        protected JobNormalizer $normalizer,
        protected JobValidator $validator,
        protected DuplicateDetector $duplicates,
        protected JobPublisher $publisher,
        protected SourceHealthService $health,
    ) {}

    /**
     * @return array{run: CrawlerRun, summary: array<string, mixed>, health: array<string, mixed>}
     */
    public function crawlSource(JobSource $source, bool $isManualTest = false): array
    {
        $source->refresh();

        if (! $source->is_enabled || ! $source->is_approved) {
            throw new \RuntimeException('Refusing to crawl a source that is not enabled and approved.');
        }

        $run = CrawlerRun::query()->create([
            'job_source_id' => $source->id,
            'status' => CrawlerRunStatus::Pending,
            'meta' => ['phase' => 9],
        ]);
        $run->markRunning();

        $found = 0;
        $created = 0;
        $updated = 0;
        $dupes = 0;
        $errors = 0;
        $rejected = 0;
        $validationErrors = 0;
        $crawlErrors = 0;
        $httpStatus = null;

        try {
            $crawler = $this->resolver->resolve($source);
            $rawItems = $crawler->crawl($source);
            $found = count($rawItems);
            if ($found > 0 && is_array($rawItems[0] ?? null)) {
                $httpStatus = $rawItems[0]['_http_status'] ?? 200;
            } else {
                // Successful fetch with zero employment items is still HTTP OK.
                $httpStatus = 200;
            }

            foreach ($rawItems as $raw) {
                try {
                    $normalized = $this->normalizer->normalize(is_array($raw) ? $raw : []);
                    $normalized['job_source_id'] = $source->id;
                    if (! filled($normalized['company_name']) || $normalized['company_name'] === 'نامشخص') {
                        $normalized['company_name'] = $source->name;
                        $normalized['organization_key'] = PersianText::normalizeKey($source->name);
                        $normalized = $this->normalizer->withRecomputedHash($normalized);
                    }

                    $validation = $this->validator->validate($normalized);
                    if (! $validation['valid']) {
                        $errors++;
                        $rejected++;
                        $validationErrors++;
                        $this->logError($source, $run, 'validation', implode('; ', $validation['errors']), $normalized['source_url'] ?? null, [
                            'errors' => $validation['errors'],
                        ]);
                        continue;
                    }

                    $dupe = $this->duplicates->findDuplicate($normalized);
                    if ($dupe['is_duplicate'] && $dupe['original']) {
                        $original = $dupe['original'];
                        // Never overwrite manual jobs or posts owned by another source.
                        if ((int) $original->job_source_id === (int) $source->id) {
                            $this->publisher->updateExisting($original, $normalized, $source);
                            $updated++;
                        }
                        $dupes++;
                        continue;
                    }

                    $this->publisher->publish($normalized, $source, false);
                    $created++;
                } catch (Throwable $e) {
                    $errors++;
                    $crawlErrors++;
                    $this->logError($source, $run, 'item_processing', $e->getMessage(), null, [
                        'exception' => class_basename($e),
                    ]);
                }
            }

            // All-rejected after a successful fetch is a quality failure, not transport failure.
            $allRejected = $found > 0 && $created === 0 && $updated === 0 && $rejected > 0 && $crawlErrors === 0;
            $status = $allRejected
                ? CrawlerRunStatus::Failed
                : ($errors > 0 && $created === 0 && $updated === 0
                    ? CrawlerRunStatus::Failed
                    : ($errors > 0 ? CrawlerRunStatus::Partial : CrawlerRunStatus::Completed));

            $run->update([
                'jobs_found' => $found,
                'jobs_created' => $created,
                'jobs_updated' => $updated,
                'duplicates' => $dupes,
                'errors_count' => $errors,
                'meta' => array_merge(is_array($run->meta) ? $run->meta : [], [
                    'phase' => 9,
                    'http_status' => $httpStatus,
                    'rejected' => $rejected,
                    'validation_errors' => $validationErrors,
                    'crawl_errors' => $crawlErrors,
                    'empty_success' => $found === 0 && $status === CrawlerRunStatus::Completed,
                    'quality_failure' => $allRejected,
                ]),
            ]);
            $run->markFinished($status);

            $source->update([
                'last_crawled_at' => now(),
                'last_success_at' => in_array($status, [CrawlerRunStatus::Completed, CrawlerRunStatus::Partial], true)
                    ? now()
                    : $source->last_success_at,
                'last_failure_at' => $status === CrawlerRunStatus::Failed
                    ? now()
                    : $source->last_failure_at,
            ]);
        } catch (Throwable $e) {
            $errors++;
            $crawlErrors++;
            $httpFromMessage = null;
            if (preg_match('/HTTP\s+(\d{3})/i', $e->getMessage(), $m)) {
                $httpFromMessage = (int) $m[1];
            }
            $this->logError($source, $run, 'crawl_failed', $e->getMessage(), $source->official_url);
            $run->update([
                'jobs_found' => $found,
                'jobs_created' => $created,
                'jobs_updated' => $updated,
                'duplicates' => $dupes,
                'errors_count' => $errors,
                'meta' => array_merge(is_array($run->meta) ? $run->meta : [], [
                    'phase' => 9,
                    'http_status' => $httpFromMessage,
                    'rejected' => $rejected,
                    'validation_errors' => $validationErrors,
                    'crawl_errors' => $crawlErrors,
                    'empty_success' => false,
                    'quality_failure' => false,
                ]),
            ]);
            $run->markFinished(CrawlerRunStatus::Failed);
            $source->update([
                'last_crawled_at' => now(),
                'last_failure_at' => now(),
            ]);
            Log::warning('CrawlOrchestrator failed', [
                'source_id' => $source->id,
                'error' => $e->getMessage(),
            ]);
        }

        $run->refresh();
        $source->refresh();

        $summary = [
            'source_id' => $source->id,
            'status' => $run->status?->value ?? (string) $run->status,
            'found' => $found,
            'created' => $created,
            'updated' => $updated,
            'duplicates' => $dupes,
            'errors' => $errors,
            'rejected' => $rejected,
            'validation_errors' => $validationErrors,
            'crawl_errors' => $crawlErrors,
            'http_status' => is_array($run->meta) ? ($run->meta['http_status'] ?? null) : null,
            'execution_ms' => $run->execution_ms,
        ];

        $health = $this->health->recordCrawl($source, $run, $summary, $isManualTest);
        $summary['outcome'] = $health['outcome'];
        $summary['quality_status'] = $health['quality_status'];

        return [
            'run' => $run->fresh(['errors']),
            'summary' => $summary,
            'health' => $health,
            'source' => $source->fresh(),
        ];
    }

    protected function logError(
        JobSource $source,
        CrawlerRun $run,
        string $type,
        string $message,
        ?string $url = null,
        array $context = []
    ): void {
        CrawlerError::query()->create([
            'job_source_id' => $source->id,
            'crawler_run_id' => $run->id,
            'error_type' => $type,
            'message' => Str::limit($message, 2000, ''),
            'url' => $url,
            'context' => $context,
            'occurred_at' => now(),
        ]);
    }
}
