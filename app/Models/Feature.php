<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Feature extends Model
{
    protected $primaryKey = 'name';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'enabled',
        'config',
        'description',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'config' => 'array',
        ];
    }
}
