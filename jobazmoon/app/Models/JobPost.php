<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class JobPost extends Model
{
    protected $fillable = [
        'title',
        'seo_tag',
        'company_name',
        'job_classification_id',
        'description',
        'province',
        'provinces',
        'city',
        'job_category',
        'registration_deadline',
        'exam_date',
        'registration_link',
        'source_url',
        'attachment_path',
        'status',
        'is_featured',
        'view_count',
        'created_by',
        'approved_by',
    ];

    protected $appends = [
        'attachment_url',
        'classification_name',
    ];

    protected function casts(): array
    {
        return [
            'provinces' => 'array',
            'registration_deadline' => 'datetime',
            'exam_date' => 'datetime',
            'is_featured' => 'boolean',
            'view_count' => 'integer',
        ];
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(JobClassification::class, 'job_classification_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    public function pdfProducts(): HasMany
    {
        return $this->hasMany(PdfProduct::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(JobPostAttachment::class)->orderBy('sort_order')->orderBy('id');
    }

    public function getAttachmentUrlAttribute(): ?string
    {
        if (! $this->attachment_path) {
            return null;
        }

        return Storage::disk('public')->url($this->attachment_path);
    }

    public function getClassificationNameAttribute(): ?string
    {
        return $this->classification?->name ?? $this->company_name;
    }
}
