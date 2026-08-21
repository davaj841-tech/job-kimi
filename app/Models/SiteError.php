<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $level
 * @property string|null $message
 * @property string|null $message_fa
 * @property string|null $exception_class
 * @property string|null $file
 * @property int|null $line
 * @property string|null $url
 * @property string|null $method
 * @property int|null $user_id
 * @property string|null $trace
 * @property array<string, mixed>|null $context
 * @property int|null $occurrences
 * @property Carbon|null $last_seen_at
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 */
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
