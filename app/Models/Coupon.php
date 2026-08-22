<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $type
 * @property numeric-string|float|int|null $value
 * @property int|null $max_uses
 * @property int $used_count
 * @property numeric-string|float|int|null $min_purchase
 * @property string $applicable_to
 * @property Carbon|null $starts_at
 * @property Carbon|null $expires_at
 * @property bool $is_active
 * @property int|null $created_by
 */
class Coupon extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'code', 'type', 'value', 'max_uses', 'used_count', 'min_purchase',
        'applicable_to', 'starts_at', 'expires_at', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:0',
            'min_purchase' => 'decimal:0',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
