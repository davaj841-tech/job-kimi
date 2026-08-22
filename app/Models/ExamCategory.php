<?php

namespace App\Models;

use App\Traits\HasSeo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string|null $slug
 * @property string|null $icon
 */
class ExamCategory extends Model
{
    /** @use HasFactory<\Database\Factories\ExamCategoryFactory> */
    use HasFactory;
    use \App\Traits\HasSeo;

    protected $fillable = [
        'name',
        'slug',
        'icon',
    ];

    protected static function booted(): void
    {
        static::creating(function (ExamCategory $category) {
            if (blank($category->slug)) {
                $category->slug = Str::slug($category->name);
            }
        });
    }

    /** @return HasMany<Exam, $this> */
    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'category_id');
    }
}
