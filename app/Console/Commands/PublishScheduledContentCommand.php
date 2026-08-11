<?php

namespace App\Console\Commands;

use App\Services\Content\ContentGeneratorService;
use Illuminate\Console\Command;

class PublishScheduledContentCommand extends Command
{
    protected $signature = 'content:publish-scheduled {--limit=20}';

    protected $description = 'انتشار محتوای زمان‌بندی‌شده در لاراول (بدون CMS خارجی)';

    public function handle(ContentGeneratorService $generator): int
    {
        $stats = $generator->publishScheduled((int) $this->option('limit'));
        $this->info('published='.$stats['published'].' failed='.$stats['failed']);

        return self::SUCCESS;
    }
}
