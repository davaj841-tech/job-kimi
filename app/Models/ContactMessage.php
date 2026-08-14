<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ContactMessage extends Model
{
    protected $fillable = [
        'tracking_code',
        'name',
        'email',
        'subject',
        'message',
        'reply',
        'replied_at',
        'replied_by',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'replied_at' => 'datetime',
        ];
    }

    public function replier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'replied_by');
    }

    public static function generateTrackingCode(): string
    {
        do {
            $code = 'JA-'.strtoupper(Str::random(8));
        } while (self::query()->where('tracking_code', $code)->exists());

        return $code;
    }
}
