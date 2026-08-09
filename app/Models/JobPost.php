<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class JobPost extends Model
{
    use HasFactory;
    protected $fillable = [
        'title',
        'seo_tag',
        'company_name',
        'job_classification_id',
        'description',
        'requirements',
        'education',
        'field_of_study',
        'experience',
        'employment_type',
        'province',
        'provinces',
        'city',
        'job_category',
        'registration_starts_at',
        'registration_deadline',
        'exam_date',
        'published_at',
        'registration_link',
        'source_url',
        'attachment_path',
        'status',
        'is_featured',
        'view_count',
        'created_by',
        'approved_by',
        'job_source_id',
        'external_id',
        'content_hash',
    ];

    protected $appends = [
        'attachment_url',
        'classification_name',
    ];

    protected function casts(): array
    {
        return [
            'provinces' => 'array',
            'registration_starts_at' => 'datetime',
            'registration_deadline' => 'datetime',
            'exam_date' => 'datetime',
            'published_at' => 'datetime',
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

    public function source(): BelongsTo
    {
        return $this->belongsTo(JobSource::class, 'job_source_id');
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
