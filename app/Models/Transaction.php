<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'amount',
        'type',
        'gateway',
        'status',
        'reference_id',
        'description',
        'payable_type',
        'payable_id',
        'invoice_number',
        'invoice_pdf',
        'coupon_id',
        'discount_amount',
        'original_amount',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:0',
            'discount_amount' => 'decimal:0',
            'original_amount' => 'decimal:0',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
