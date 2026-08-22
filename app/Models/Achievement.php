<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property string $code
 * @property string $title
 * @property string|null $description
 * @property string|null $icon
 * @property-read mixed $earned_at
 */
class Achievement extends Model
{
    protected $fillable = ['code', 'title', 'description', 'icon'];

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_achievements')
            ->withPivot('earned_at')
            ->withTimestamps();
    }
}
