<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class CmsPage extends Model
{
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
