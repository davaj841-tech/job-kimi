<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $session_id
 * @property string|null $page_url
 * @property string|null $route_name
 * @property string|null $user_agent
 * @property string|null $ip_address
 * @property string|null $referrer
 * @property Carbon|null $created_at
 * @property-read User|null $user
 */
class PageView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id', 'session_id', 'page_url', 'route_name',
        'user_agent', 'ip_address', 'referrer', 'created_at',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
