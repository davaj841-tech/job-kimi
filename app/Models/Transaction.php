<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Transaction extends Model
{
    public const STATUS_PENDING = 'pending';

    /** Persisted as `success` in the transactions.status enum. */
    public const STATUS_COMPLETED = 'success';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'user_id',
        'amount',
        'type',
        'gateway',
        'status',
        'reference_id',
        'idempotency_key',
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

    /**
     * @param  Builder<Transaction>  $query
     * @return Builder<Transaction>
     */
    public function scopeByIdempotencyKey(Builder $query, string $key): Builder
    {
        return $query->where('idempotency_key', $key);
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
