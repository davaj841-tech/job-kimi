<?php

namespace App\Console\Commands;

use App\Enums\Content\ContentType;
use App\Models\JobPost;
use App\Services\Content\ContentGeneratorService;
use Illuminate\Console\Command;

class GenerateContentForJobCommand extends Command
{
    protected $signature = 'content:generate
                            {--job= : JobPost ID}
                            {--type= : Content type enum value}';

    protected $description = 'تولید محتوا برای یک آگهی مشخص';

    public function handle(ContentGeneratorService $generator): int
    {
        $jobId = (int) $this->option('job');
        if ($jobId <= 0) {
            $this->error('--job=ID الزامی است.');

            return self::FAILURE;
        }

        $job = JobPost::query()->with('source')->find($jobId);
        if (! $job) {
            $this->error('آگهی یافت نشد.');

            return self::FAILURE;
        }

        $type = null;
        if ($this->option('type')) {
            $type = ContentType::tryFrom((string) $this->option('type'));
            if (! $type) {
                $this->error('نوع محتوا نامعتبر است.');

                return self::FAILURE;
            }
        }

        $result = $generator->generateForJob($job, $type);
        $this->info('outcome: '.$result['outcome']);
        if ($result['error']) {
            $this->warn($result['error']);
        }
        if ($result['content']) {
            $this->line('id='.$result['content']->id.' slug='.$result['content']->slug);
        }

        return self::SUCCESS;
    }
}
