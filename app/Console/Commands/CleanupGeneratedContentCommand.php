<?php

namespace App\Console\Commands;

use App\Enums\Content\ContentStatus;
use App\Models\GeneratedContent;
use Illuminate\Console\Command;

class CleanupGeneratedContentCommand extends Command
{
    protected $signature = 'content:cleanup
                            {--days=90 : حذف failed/skipped قدیمی‌تر از N روز}
                            {--dry-run : فقط شمارش، بدون حذف}';

    protected $description = 'پاکسازی رکوردهای ناموفق/ردشده قدیمی تولید محتوا (هرگز published را حذف نمی‌کند)';

    public function handle(): int
    {
        $days = max(7, (int) $this->option('days'));
        $q = GeneratedContent::query()
            ->whereIn('status', [ContentStatus::Failed->value, ContentStatus::Skipped->value])
            ->where('created_at', '<', now()->subDays($days));

        $count = (clone $q)->count();
        if ($this->option('dry-run')) {
            $this->info("dry_run would_delete={$count}");

            return self::SUCCESS;
        }

        $deleted = $q->delete();
        $this->info("deleted={$deleted}");

        return self::SUCCESS;
    }
}
