<?php

namespace App\Services;

use App\Models\Feature;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

final class FeatureFlagService
{
    private const CACHE_KEY = 'features.all';

    private const CACHE_TTL = 3600;

    public function isEnabled(string $name, bool $default = false): bool
    {
        $map = $this->cachedMap();

        if (! array_key_exists($name, $map)) {
            return $default;
        }

        return (bool) $map[$name]['enabled'];
    }

    public function enable(string $name): void
    {
        Feature::query()->updateOrCreate(
            ['name' => $name],
            ['enabled' => true]
        );

        $this->forgetCache();
    }

    public function disable(string $name): void
    {
        Feature::query()->updateOrCreate(
            ['name' => $name],
            ['enabled' => false]
        );

        $this->forgetCache();
    }

    public function config(string $name, ?string $key = null): mixed
    {
        $feature = $this->cachedMap()[$name] ?? null;
        $config = $feature['config'] ?? [];

        if (! is_array($config)) {
            $config = [];
        }

        if ($key === null) {
            return $config;
        }

        return $config[$key] ?? null;
    }

    /**
     * @return Collection<string, mixed>
     */
    public function all(): Collection
    {
        return collect($this->cachedMap());
    }

    /**
     * @return array<string, array{enabled: bool, config: mixed, description: string|null}>
     */
    public function allForApi(): array
    {
        return $this->cachedMap();
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    public function enableAll(): void
    {
        Feature::query()->update(['enabled' => true]);
        $this->forgetCache();
    }

    public function disableAll(): void
    {
        Feature::query()->update(['enabled' => false]);
        $this->forgetCache();
    }

    /**
     * @return array<string, array{enabled: bool, config: mixed, description: string|null}>
     */
    private function cachedMap(): array
    {
        /** @var array<string, array{enabled: bool, config: mixed, description: string|null}> $cached */
        $cached = Cache::remember(self::CACHE_KEY, self::CACHE_TTL, function (): array {
            $map = [];

            foreach (Feature::query()->orderBy('name')->get() as $feature) {
                $map[$feature->name] = [
                    'enabled' => (bool) $feature->enabled,
                    'config' => $feature->config,
                    'description' => $feature->description,
                ];
            }

            return $map;
        });

        return $cached;
    }
}
