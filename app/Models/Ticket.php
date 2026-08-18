<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ticket extends Model
{
    protected $fillable = [
        'user_id', 'tracking_code', 'subject', 'message', 'category', 'status', 'priority', 'assigned_to',
    ];

    protected static function booted(): void
    {
        static::creating(function (Ticket $ticket) {
            if (! preg_match('/^\d{6}$/', (string) $ticket->tracking_code)) {
                $ticket->tracking_code = static::generateTrackingCode();
            }
        });
    }

    public static function generateTrackingCode(): string
    {
        do {
            $code = (string) random_int(100000, 999999);
        } while (static::query()->where('tracking_code', $code)->exists());

        return $code;
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(TicketReply::class)->latest();
    }
}
