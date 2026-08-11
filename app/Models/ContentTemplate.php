<?php

namespace App\Models;

use App\Enums\Content\ContentType;
use Illuminate\Database\Eloquent\Model;

class ContentTemplate extends Model
{
    protected $fillable = [
        'name',
        'content_type',
        'title_template',
        'content_template',
        'enabled',
        'priority',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'content_type' => ContentType::class,
            'enabled' => 'boolean',
            'priority' => 'integer',
            'metadata' => 'array',
        ];
    }
}
