<?php

namespace App\Console\Commands;

use App\Models\CrawlerError;
use App\Models\CrawlerRun;
use App\Models\JobSource;
use App\Models\JobSourceEndpoint;
use App\Services\Aggregation\CrawlOrchestrator;
use Database\Seeders\PilotJobSourceSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Throwable;

/**
 * Seed (optional) and crawl official sources inline (Phases 5–8).
 * One failed source never aborts the remaining sources.
 */
class PilotAggregateCrawlCommand extends Command
{
    protected $signature = 'jobs:pilot-crawl
                            {--seed : Upsert official sources from config before crawling}
                            {--slug= : Crawl only one source slug}
                            {--enabled-only : Crawl only enabled+approved sources}
                            {--report= : Optional JSON report path}';

    protected $description = 'Crawl official aggregation sources and print a per-source validation report';

    public function handle(CrawlOrchestrator $orchestrator): int
    {
        if ($this->option('seed')) {
            (new PilotJobSourceSeeder)->run();
            $this->info('Official sources seeded/updated from config.');
        }

        $configured = config('aggregation.official_sources');
        if (! is_array($configured) || $configured === []) {
            $configured = config('aggregation.pilot_sources', []);
        }

        $slugs = collect(array_values(array_filter(array_map(
            static fn ($row) => is_array($row) && isset($row['slug']) && is_string($row['slug']) ? $row['slug'] : null,
            $configured
        ))))->values();

        if ($only = $this->option('slug')) {
            $slugs = $slugs->filter(fn ($s) => $s === $only)->values();
        }

        if ($slugs->isEmpty()) {
            $this->warn('No official source slugs configured.');

            return self::SUCCESS;
        }

        $query = JobSource::query()
            ->whereIn('slug', $slugs->all())
            ->with('endpoints')
            ->orderBy('priority')
            ->orderBy('id');

        if ($this->option('enabled-only')) {
            $query->whitelisted();
        }

        $sources = $query->get();

        if ($sources->isEmpty()) {
            $this->warn('Official sources not found in DB. Re-run with --seed.');

            return self::FAILURE;
        }

        /** @var list<array<string, mixed>> $report */
        $report = [];
        /** @var list<list<mixed>> $rows */
        $rows = [];

        foreach ($sources as $source) {
            $started = microtime(true);
            $endpoint = $source->endpoints->firstWhere('is_enabled', true);
            if (! $endpoint instanceof JobSourceEndpoint) {
                $endpoint = $source->endpoints->first();
            }
            $entry = [
                'slug' => $source->slug,
                'name' => $source->name,
                'domain' => $source->domain,
                'source_type' => $source->source_type instanceof \BackedEnum ? $source->source_type->value : $source->source_type,
                'reliability_level' => $source->reliability_level instanceof \BackedEnum ? $source->reliability_level->value : $source->reliability_level,
                'quality_status' => $source->quality_status instanceof \BackedEnum ? $source->quality_status->value : $source->quality_status,
                'crawler_type' => $source->crawler_type instanceof \BackedEnum ? $source->crawler_type->value : $source->crawler_type,
                'is_enabled' => (bool) $source->is_enabled,
                'is_approved' => (bool) $source->is_approved,
                'endpoint' => $endpoint instanceof JobSourceEndpoint ? $endpoint->url : null,
                'parser_type' => $endpoint instanceof JobSourceEndpoint ? $endpoint->parser_type : null,
                'http_status' => null,
                'duration_ms' => null,
                'found' => 0,
                'created' => 0,
                'updated' => 0,
                'duplicates' => 0,
                'rejected' => 0,
                'validation_errors' => 0,
                'crawl_errors' => 0,
                'run_status' => null,
                'error_sample' => null,
                'skipped' => false,
            ];

            if (! $source->is_enabled || ! $source->is_approved) {
                $entry['run_status'] = 'skipped_not_whitelisted';
                $entry['skipped'] = true;
                $entry['duration_ms'] = 0;
                $report[] = $entry;
                $rows[] = [
                    $entry['slug'],
                    $entry['quality_status'],
                    $entry['run_status'],
                    '-',
                    0,
                    0,
                    0,
                    0,
                    0,
                    0,
                    0,
                ];

                continue;
            }

            try {
                $result = $orchestrator->crawlSource($source);
                $durationMs = (int) round((microtime(true) - $started) * 1000);
                /** @var CrawlerRun $run */
                $run = $result['run'];
                $meta = is_array($run->meta) ? $run->meta : [];

                $entry['duration_ms'] = $run->execution_ms ?: $durationMs;
                $entry['found'] = (int) $run->jobs_found;
                $entry['created'] = (int) $run->jobs_created;
                $entry['updated'] = (int) $run->jobs_updated;
                $entry['duplicates'] = (int) $run->duplicates;
                $entry['rejected'] = (int) ($meta['rejected'] ?? $result['summary']['rejected'] ?? 0);
                $entry['validation_errors'] = (int) ($meta['validation_errors'] ?? $result['summary']['validation_errors'] ?? 0);
                $entry['crawl_errors'] = (int) ($meta['crawl_errors'] ?? max(0, (int) $run->errors_count - $entry['validation_errors']));
                $entry['run_status'] = $run->status?->value;
                $entry['http_status'] = $meta['http_status'] ?? ($run->status?->value === 'failed' ? 'error' : 200);

                $sample = CrawlerError::query()
                    ->where('crawler_run_id', $run->id)
                    ->latest('id')
                    ->value('message');
                $entry['error_sample'] = $sample ? mb_substr($sample, 0, 160) : null;
            } catch (Throwable $e) {
                $entry['duration_ms'] = (int) round((microtime(true) - $started) * 1000);
                $entry['run_status'] = 'exception';
                $entry['crawl_errors'] = 1;
                $entry['error_sample'] = mb_substr($e->getMessage(), 0, 160);
                $this->error("Source {$source->slug} failed: {$e->getMessage()}");
            }

            $report[] = $entry;
            $rows[] = [
                $entry['slug'],
                $entry['quality_status'],
                $entry['run_status'],
                $entry['http_status'],
                $entry['duration_ms'],
                $entry['found'],
                $entry['created'],
                $entry['updated'],
                $entry['duplicates'],
                $entry['rejected'],
                $entry['crawl_errors'],
            ];
        }

        $this->newLine();
        $this->table(
            ['Slug', 'Quality', 'Status', 'HTTP', 'ms', 'Found', 'Created', 'Updated', 'Dupes', 'Rejected', 'CrawlErr'],
            $rows
        );

        $path = $this->option('report') ?: storage_path('logs/official-crawl-report.json');
        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode([
            'generated_at' => now()->toIso8601String(),
            'sources' => $report,
            'totals' => [
                'found' => (int) collect($report)->sum(static fn (array $r): int => (int) ($r['found'] ?? 0)),
                'created' => (int) collect($report)->sum(static fn (array $r): int => (int) ($r['created'] ?? 0)),
                'updated' => (int) collect($report)->sum(static fn (array $r): int => (int) ($r['updated'] ?? 0)),
                'duplicates' => (int) collect($report)->sum(static fn (array $r): int => (int) ($r['duplicates'] ?? 0)),
                'rejected' => (int) collect($report)->sum(static fn (array $r): int => (int) ($r['rejected'] ?? 0)),
                'validation_errors' => (int) collect($report)->sum(static fn (array $r): int => (int) ($r['validation_errors'] ?? 0)),
                'crawl_errors' => (int) collect($report)->sum(static fn (array $r): int => (int) ($r['crawl_errors'] ?? 0)),
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        $this->info("Report written: {$path}");

        return self::SUCCESS;
    }
}
