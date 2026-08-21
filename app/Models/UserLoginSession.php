<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $token_id
 * @property Carbon $logged_in_at
 * @property Carbon|null $logged_out_at
 * @property int|null $duration_seconds
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property string $source
 */
class UserLoginSession extends Model
{
    protected $fillable = [
        'user_id',
        'token_id',
        'logged_in_at',
        'logged_out_at',
        'duration_seconds',
        'ip_address',
        'user_agent',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'logged_in_at' => 'datetime',
            'logged_out_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isOpen(): bool
    {
        return $this->logged_out_at === null;
    }

    public function effectiveDurationSeconds(?Carbon $until = null): int
    {
        if ($this->duration_seconds !== null) {
            return max(0, (int) $this->duration_seconds);
        }

        $end = $this->logged_out_at ?? ($until ?? now());

        return max(0, (int) $this->logged_in_at->diffInSeconds($end));
    }
}
