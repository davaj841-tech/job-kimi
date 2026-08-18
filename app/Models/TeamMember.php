<?php

namespace App\Models;

use App\Support\PublicAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class TeamMember extends Model
{
    protected $fillable = [
        'name',
        'role',
        'photo',
        'bio',
        'sort_order',
    ];

    protected $appends = ['photo_url'];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo) {
            return null;
        }

        $url = PublicAsset::url($this->photo);

        return $url !== '' ? $url : Storage::disk('public')->url($this->photo);
    }
}
