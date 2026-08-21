<?php

namespace App\Services\Seo;

use App\Models\Seo\SeoLink;
use Illuminate\Support\Facades\Http;

class BrokenLinkService
{
    public function checkLink(SeoLink $link): void
    {
        try {
            $response = Http::timeout(config('seo.broken_links.timeout_seconds', 10))
                ->withoutVerifying()
                ->get($link->target_url);

            $link->update([
                'http_status' => $response->status(),
                'is_broken' => $response->status() >= 400,
                'checked_at' => now(),
            ]);
        } catch (\Throwable) {
            $link->update([
                'http_status' => 0,
                'is_broken' => true,
                'checked_at' => now(),
            ]);
        }
    }

    public function checkAll(): int
    {
        $hours = config('seo.broken_links.check_interval_hours', 24);
        $links = SeoLink::query()
            ->where(function ($q) use ($hours) {
                $q->whereNull('checked_at')
                    ->orWhere('checked_at', '<', now()->subHours($hours));
            })
            ->limit(50)
            ->get();

        foreach ($links as $link) {
            $this->checkLink($link);
        }

        return $links->count();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, SeoLink>
     */
    public function getBrokenLinks(): \Illuminate\Database\Eloquent\Collection
    {
        return SeoLink::broken()->with('linkable')->orderByDesc('checked_at')->get();
    }
}
