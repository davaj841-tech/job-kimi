<?php

namespace App\Services\Seo;

use App\Models\Seo\SeoMeta;
use Illuminate\Database\Eloquent\Model;

class RobotsService
{
    /**
     * @return array{index: bool, follow: bool}
     */
    public function defaults(): array
    {
        $defaults = config('seo.robots_defaults', ['index' => true, 'follow' => true]);

        return [
            'index' => (bool) ($defaults['index'] ?? true),
            'follow' => (bool) ($defaults['follow'] ?? true),
        ];
    }

    /**
     * @return array{index: bool, follow: bool}
     */
    public function fromString(?string $robots): array
    {
        $robots = mb_strtolower(trim((string) $robots));
        if ($robots === '') {
            return $this->defaults();
        }

        return [
            'index' => ! str_contains($robots, 'noindex'),
            'follow' => ! str_contains($robots, 'nofollow'),
        ];
    }

    public function toString(bool $index, bool $follow): string
    {
        return ($index ? 'index' : 'noindex').', '.($follow ? 'follow' : 'nofollow');
    }

    /**
     * @return array{index: bool, follow: bool}
     */
    public function forModel(?Model $model): array
    {
        if ($model === null) {
            return $this->defaults();
        }

        $metaRaw = $model->getRelationValue('seoMeta');
        $meta = $metaRaw instanceof SeoMeta ? $metaRaw : null;
        if ($meta !== null && $meta->robots) {
            return $this->fromString($meta->robots);
        }

        return $this->defaults();
    }

    public function isIndexable(?Model $model): bool
    {
        return $this->forModel($model)['index'];
    }

    public function matchesNoindexPattern(string $path): bool
    {
        $path = ltrim($path, '/');

        foreach (config('seo.noindex_patterns', []) as $pattern) {
            $pattern = ltrim((string) $pattern, '/');
            $regex = '/^'.str_replace('\*', '.*', preg_quote($pattern, '/')).($pattern !== '' && str_ends_with($pattern, '*') ? '' : '(?:$|\/)/').'/';

            if (preg_match($regex, $path)) {
                return true;
            }
        }

        return false;
    }
}
