<?php

namespace App\Models;

use App\Traits\HasSeo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $title
 * @property string|null $slug
 * @property string|null $content
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property bool $is_published
 */
class CmsPage extends Model
{
    use \App\Traits\HasSeo;

    protected $table = 'cms_pages';

    protected $fillable = [
        'title', 'slug', 'content', 'meta_title', 'meta_description', 'is_published',
    ];

    protected function casts(): array
    {
        return ['is_published' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::creating(function (CmsPage $page) {
            if (blank($page->slug)) {
                $page->slug = Str::slug($page->title).'-'.Str::random(4);
            }
        });
    }
}
