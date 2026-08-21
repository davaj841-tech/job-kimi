<?php

namespace App\Models\Seo;

use Illuminate\Database\Eloquent\Model;

class SeoAudit extends Model
{
    protected $table = 'seo_audits';

    protected $guarded = ['id'];

    protected $casts = [
        'results' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
