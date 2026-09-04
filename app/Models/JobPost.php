<?php

namespace App\Models;

use App\Traits\HasSeo;
use Database\Factories\JobPostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $title
 * @property string|null $seo_tag
 * @property string|null $company_name
 * @property int|null $job_classification_id
 * @property bool|null $auto_catalog
 * @property array<int, int>|null $exam_ids
 * @property array<int, int>|null $pdf_ids
 * @property string|null $description
 * @property string|null $requirements
 * @property string|null $education
 * @property string|null $field_of_study
 * @property string|null $experience
 * @property string|null $employment_type
 * @property string|null $province
 * @property array<int, string>|null $provinces
 * @property string|null $city
 * @property string|null $job_category
 * @property Carbon|null $registration_starts_at
 * @property Carbon|null $registration_deadline
 * @property Carbon|null $exam_date
 * @property Carbon|null $published_at
 * @property string|null $registration_link
 * @property string|null $source_url
 * @property string|null $attachment_path
 * @property string|null $status
 * @property bool|null $is_featured
 * @property int|null $view_count
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property int|null $job_source_id
 * @property string|null $external_id
 * @property string|null $content_hash
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read string|null $attachment_url
 * @property-read string|null $classification_name
 * @property-read JobClassification|null $classification
 * @property-read User|null $creator
 * @property-read User|null $approver
 * @property-read JobSource|null $source
 * @property list<array<string, mixed>>|null $catalog_exams
 * @property list<array<string, mixed>>|null $catalog_pdfs
 */
class JobPost extends Model
{
    /** @use HasFactory<JobPostFactory> */
    use HasFactory;

    use HasSeo;

    protected $fillable = [
        'title',
        'seo_tag',
        'company_name',
        'job_classification_id',
        'auto_catalog',
        'exam_ids',
        'pdf_ids',
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
            'exam_ids' => 'array',
            'pdf_ids' => 'array',
            'auto_catalog' => 'boolean',
            'registration_starts_at' => 'datetime',
            'registration_deadline' => 'datetime',
            'exam_date' => 'datetime',
            'published_at' => 'datetime',
            'is_featured' => 'boolean',
            'view_count' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<JobClassification, $this>
     */
    public function classification(): BelongsTo
    {
        return $this->belongsTo(JobClassification::class, 'job_classification_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<Exam, $this>
     */
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class);
    }

    /**
     * @return HasMany<PdfProduct, $this>
     */
    public function pdfProducts(): HasMany
    {
        return $this->hasMany(PdfProduct::class);
    }

    /**
     * @return HasMany<JobPostAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(JobPostAttachment::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<JobPostComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(JobPostComment::class);
    }

    /**
     * @return BelongsTo<JobSource, $this>
     */
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
        return $this->classification->name ?? $this->company_name;
    }
}
