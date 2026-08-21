<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $transaction_id
 * @property int $amount
 * @property string $direction
 * @property string $type
 * @property string|null $source_key
 * @property int $balance_after
 * @property string|null $description
 * @property string|null $prev_hash
 * @property string|null $hash
 * @property Carbon|null $created_at
 * @property-read User|null $user
 * @property-read Transaction|null $transaction
 */
class WalletLedger extends Model
{
    public const DIRECTION_CREDIT = 'credit';

    public const DIRECTION_DEBIT = 'debit';

    public const TYPE_DEPOSIT = 'deposit';

    public const TYPE_WITHDRAWAL = 'withdrawal';

    public const TYPE_PURCHASE = 'purchase';

    public const TYPE_REFUND = 'refund';

    public const TYPE_ADMIN_CREDIT = 'admin_credit';

    public const TYPE_ADMIN_DEBIT = 'admin_debit';

    public const TYPE_OPENING = 'opening';

    public $timestamps = false;

    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Wallet ledger is append-only and cannot be updated.');
        });

        static::deleting(static function (): never {
            throw new LogicException('Wallet ledger is append-only and cannot be deleted.');
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Transaction, $this>
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function signedAmount(): int
    {
        $amount = (int) $this->amount;

        return $this->direction === self::DIRECTION_DEBIT ? -$amount : $amount;
    }
}
