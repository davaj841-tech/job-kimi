<?php

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\CrawlerRun;
use App\Models\JobPost;
use Illuminate\Support\Facades\DB;

$since = now()->subHours(2);
$runs = CrawlerRun::where('created_at', '>', $since);

echo 'queued='.DB::table('jobs')->where('queue', 'crawlers')->count().PHP_EOL;
echo 'pending='.JobPost::where('status', 'pending')->whereNotNull('job_source_id')->count().PHP_EOL;
echo 'runs_2h='.$runs->count().PHP_EOL;
echo 'jobs_found_2h='.(int) $runs->sum('jobs_found').PHP_EOL;
echo 'jobs_created_2h='.(int) $runs->sum('jobs_created').PHP_EOL;
echo 'jobs_updated_2h='.(int) $runs->sum('jobs_updated').PHP_EOL;
foreach ($runs->clone()->selectRaw('status, count(*) as c')->groupBy('status')->get() as $row) {
    $status = $row->status instanceof \BackedEnum ? $row->status->value : (string) $row->status;
    echo "status_{$status}={$row->c}".PHP_EOL;
}
