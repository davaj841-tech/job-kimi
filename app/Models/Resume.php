<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Resume extends Model
{
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

        if (! $photo) {
            return null;
        }

        if (Storage::disk('public')->exists($photo)) {
            return Storage::disk('public')->path($photo);
        }

        if (is_string($photo) && file_exists($photo)) {
            return $photo;
        }

        return null;
    }
}
