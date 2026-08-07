<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class JobClassification extends Model
{
    protected $fillable = [
        'parent_id',
        'name',
        'icon',
        'color',
        'logo_path',
        'sort_order',
        'is_active',
        'show_on_home',
    ];

    protected $appends = ['logo_url'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'show_on_home' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('name');
    }

    public function jobPosts(): HasMany
    {
        return $this->hasMany(JobPost::class);
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function pdfProducts(): HasMany
    {
        return $this->hasMany(PdfProduct::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    /** Self + descendant ids for filtering related content */
    public function descendantAndSelfIds(): array
    {
        $ids = [$this->id];
        $children = self::query()->where('parent_id', $this->id)->pluck('id');
        foreach ($children as $childId) {
            $ids[] = (int) $childId;
            $grand = self::query()->where('parent_id', $childId)->pluck('id')->all();
            $ids = array_merge($ids, array_map('intval', $grand));
        }

        return array_values(array_unique($ids));
    }
}
