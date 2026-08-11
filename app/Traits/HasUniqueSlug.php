<?php

declare(strict_types=1);

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @mixin Model
 */
trait HasUniqueSlug
{
    protected string $slugSourceField = 'title';

    protected string $slugColumnName = 'slug';

    public static function bootHasUniqueSlug(): void
    {
        static::creating(function (Model $model): void {
            /** @var self&Model $model */
            $column = $model->slugColumn();

            if (filled($model->getAttribute($column))) {
                $model->setAttribute(
                    $column,
                    $model->resolveSlugCollision((string) $model->getAttribute($column))
                );

                return;
            }

            $source = (string) ($model->getAttribute($model->slugSourceAttribute()) ?? '');
            $model->setAttribute($column, $model->generateSlug($source));
        });
    }

    public function generateSlug(string $source, ?string $existing = null): string
    {
        if (is_string($existing) && ! blank($existing)) {
            return $this->resolveSlugCollision($existing);
        }

        $base = Str::slug($source);

        if ($base === '') {
            $base = (string) config('exam.slug.fallback_prefix', 'exam');
        }

        $suffixLength = max(1, (int) config('exam.slug.random_suffix_length', 5));
        $candidate = $base.'-'.Str::random($suffixLength);

        return $this->resolveSlugCollision($candidate);
    }

    public function resolveSlugCollision(string $slug): string
    {
        $column = $this->slugColumn();
        $unique = $slug;
        $attempts = 0;
        $suffixLength = max(1, (int) config('exam.slug.random_suffix_length', 5));

        return DB::transaction(function () use ($column, $slug, &$unique, &$attempts, $suffixLength) {
            while ($this->slugExists($unique, $column)) {
                $attempts++;
                $unique = $slug.'-'.Str::random($suffixLength);

                if ($attempts >= 10) {
                    $unique = $slug.'-'.Str::lower(Str::random(8));
                    break;
                }
            }

            // Lock a matching row probe so concurrent creates serialize on the same slug.
            static::query()
                ->where($column, $unique)
                ->lockForUpdate()
                ->exists();

            if ($this->slugExists($unique, $column)) {
                $unique = $slug.'-'.Str::lower(Str::random(8));
            }

            return $unique;
        });
    }

    protected function slugSourceAttribute(): string
    {
        return $this->slugSourceField;
    }

    protected function slugColumn(): string
    {
        return $this->slugColumnName;
    }

    private function slugExists(string $slug, string $column): bool
    {
        $query = static::query()->where($column, $slug);

        if ($this->exists) {
            $query->whereKeyNot($this->getKey());
        }

        return $query->lockForUpdate()->exists();
    }
}
