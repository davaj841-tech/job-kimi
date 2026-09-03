<?php

namespace App\Jobs\Seo;

use App\Services\Seo\SearchEnginePingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PingSitemapJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 30;

    public function handle(SearchEnginePingService $pinger): void
    {
        if (! config('seo.automation.ping_search_engines', true)) {
            return;
        }

        $pinger->pingSitemap();
    }
}
