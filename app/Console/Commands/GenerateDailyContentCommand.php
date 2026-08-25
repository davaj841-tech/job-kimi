<?php

namespace App\Console\Commands;

use App\Services\Content\ContentGeneratorService;
use Database\Seeders\ContentTemplateSeeder;
use Illuminate\Console\Command;

class GenerateDailyContentCommand extends Command
{
    protected $signature = 'content:generate-daily
                            {--seed-templates : Upsert Persian content templates before generation}
                            {--force : Run even when CONTENT_ENABLED / daily generation is off}';

    protected $description = 'تولید روزانه محتوای استخدامی از آگهی‌های تأییدشده (بدون هوش مصنوعی)';

    public function handle(ContentGeneratorService $generator): int
    {
        if ($this->option('seed-templates')) {
            (new ContentTemplateSeeder)->run();
            $this->info('قالب‌ها به‌روز شدند.');
        }

        $stats = $generator->generateDaily(null, (bool) $this->option('force'));
        $this->table(
            ['metric', 'value'],
            collect($stats)->except('errors')->map(fn ($v, $k) => [$k, $v])->values()->all()
        );
        foreach ($stats['errors'] as $err) {
            $this->warn($err);
        }

        return self::SUCCESS;
    }
}
