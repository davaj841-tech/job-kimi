<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SiteError extends Model
{
    protected $fillable = [
        'level',
        'message',
        'message_fa',
        'exception_class',
        'file',
        'line',
        'url',
        'method',
        'user_id',
        'trace',
        'context',
        'occurrences',
        'last_seen_at',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'context' => 'array',
            'last_seen_at' => 'datetime',
            'resolved_at' => 'datetime',
            'occurrences' => 'integer',
            'line' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
