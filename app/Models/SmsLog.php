<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = [
        'recipient_masked',
        'message_type',
        'provider',
        'status',
        'message_id',
        'error_code',
        'error_message',
        'duration_ms',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }
}
