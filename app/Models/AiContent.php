<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AiContent extends Model
{
    protected $fillable = [
        'type',
        'prompt',
        'generated_content',
        'reviewed_by',
        'status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return HasMany<BlogPost, $this> */
    public function blogPosts(): HasMany
    {
        return $this->hasMany(BlogPost::class);
    }
}
