<?php

namespace App\Models;

use Database\Factories\ResumeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Resume extends Model
{
    /** @use HasFactory<ResumeFactory> */
    use HasFactory;

    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'template_id',
        'title',
        'data',
        'pdf_path',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_active' => 'boolean',
            'template_id' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getPdfUrlAttribute(): ?string
    {
        if (! $this->pdf_path) {
            return null;
        }

        return url('/api/v1/resumes/'.$this->id.'/pdf');
    }

    public function photoAbsolutePath(): ?string
    {
        $photo = data_get($this->data, 'personal.photo');

        if (! is_string($photo) || $photo === '') {
            return null;
        }

        $photo = str_replace('\\', '/', $photo);
        if (str_contains($photo, '..') || str_starts_with($photo, '/') || preg_match('#^[a-zA-Z]:#', $photo)) {
            return null;
        }

        if (! preg_match('#^(avatars|resumes)/[A-Za-z0-9._/-]+$#', $photo)) {
            return null;
        }

        if (! Storage::disk('public')->exists($photo)) {
            return null;
        }

        return Storage::disk('public')->path($photo);
    }
}
