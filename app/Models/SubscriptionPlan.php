<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property int $duration_days
 * @property numeric-string|float|int|null $price
 * @property array<int|string, mixed>|null $features
 * @property bool|null $is_active
 */
class SubscriptionPlan extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionPlanFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'duration_days',
        'price',
        'features',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'features' => 'array',
            'price' => 'decimal:0',
            'is_active' => 'boolean',
            'duration_days' => 'integer',
        ];
    }

    /** @return HasMany<User, $this> */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
