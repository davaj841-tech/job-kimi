<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property numeric-string|float|int|null $amount
 * @property string|null $type
 * @property string|null $gateway
 * @property string|null $status
 * @property string|null $reference_id
 * @property string|null $idempotency_key
 * @property string|null $description
 * @property string|null $payable_type
 * @property int|null $payable_id
 * @property string|null $invoice_number
 * @property string|null $invoice_pdf
 * @property int|null $coupon_id
 * @property numeric-string|float|int|null $discount_amount
 * @property numeric-string|float|int|null $original_amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Coupon|null $coupon
 */
class Transaction extends Model
{
    /** @use HasFactory<\Database\Factories\TransactionFactory> */
    use HasFactory;

    public const STATUS_PENDING = 'pending';

    /** Persisted as `success` in the transactions.status enum. */
    public const STATUS_COMPLETED = 'success';

    public const STATUS_FAILED = 'failed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_EXPIRED = 'expired';

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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<Model, $this> */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<Coupon, $this> */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
