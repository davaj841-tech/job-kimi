<?php

namespace App\Services\Seo;

use App\Models\Seo\SeoRedirect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RedirectService
{
    public function findRedirect(string $path): ?SeoRedirect
    {
        if (! Schema::hasTable('seo_redirects')) {
            return null;
        }

        $path = '/'.ltrim($path, '/');

        return SeoRedirect::active()->where('source_path', $path)->first();
    }

    public function handleRedirect(SeoRedirect $redirect): ?RedirectResponse
    {
        $target = $this->resolveChain($redirect->target_url);

        if (! $this->isValidTarget($target)) {
            return null;
        }

        $redirect->recordHit();

        if ($redirect->status_code === 410) {
            abort(410);
        }

        return redirect($target, $redirect->status_code);
    }

    public function create(string $source, string $target, int $statusCode = 301): SeoRedirect
    {
        $source = '/'.ltrim($source, '/');
        $target = $this->normalizeTarget($target);

        if (! in_array($statusCode, config('seo.redirects.allowed_status_codes', [301, 302, 410]), true)) {
            $statusCode = 301;
        }

        if ($this->wouldCreateLoop($source, $target)) {
            throw new InvalidArgumentException('Redirect would create a loop.');
        }

        if (! $this->isValidTarget($target)) {
            throw new InvalidArgumentException('Invalid redirect target.');
        }

        return SeoRedirect::updateOrCreate(
            ['source_path' => $source],
            ['target_url' => $target, 'status_code' => $statusCode, 'is_active' => true]
        );
    }

    public function resolveChain(string $url, int $depth = 0): string
    {
        if ($depth > (int) config('seo.redirects.max_chain_depth', 10)) {
            return $url;
        }

        if (! Str::startsWith($url, '/')) {
            return $url;
        }

        $path = '/'.ltrim($url, '/');
        $next = $this->findRedirect($path);

        if (! $next || $next->status_code === 410) {
            return $url;
        }

        if ($next->target_url === $path) {
            return $url;
        }

        return $this->resolveChain($next->target_url, $depth + 1);
    }

    protected function wouldCreateLoop(string $source, string $target): bool
    {
        $source = '/'.ltrim($source, '/');
        $resolvedTarget = $this->resolveChain($target);

        if ($source === $resolvedTarget) {
            return true;
        }

        if (Str::startsWith($resolvedTarget, '/')) {
            $chainTarget = $this->findRedirect(ltrim($resolvedTarget, '/'));
            if ($chainTarget && '/'.ltrim($chainTarget->target_url, '/') === $source) {
                return true;
            }
        }

        return false;
    }

    protected function normalizeTarget(string $target): string
    {
        if (Str::startsWith($target, ['http://', 'https://', '/'])) {
            return $target;
        }

        return '/'.ltrim($target, '/');
    }

    protected function isValidTarget(string $url): bool
    {
        if (Str::startsWith($url, '/')) {
            return true;
        }

        $parsed = parse_url($url);
        if (! $parsed || ! isset($parsed['host'])) {
            return false;
        }

        $appHost = parse_url(config('app.url'), PHP_URL_HOST);

        return $parsed['host'] === $appHost || Str::endsWith($parsed['host'], '.'.$appHost);
    }
}
