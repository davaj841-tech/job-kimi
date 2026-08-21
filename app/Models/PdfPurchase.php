<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property int|null $pdf_product_id
 * @property numeric-string|float|int|null $price_paid
 * @property Carbon|null $purchased_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read PdfProduct|null $pdfProduct
 */
class PdfPurchase extends Model
{
    protected $fillable = [
        'user_id',
        'pdf_product_id',
        'price_paid',
        'purchased_at',
    ];

    protected function casts(): array
    {
        return [
            'price_paid' => 'decimal:0',
            'purchased_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<PdfProduct, $this> */
    public function pdfProduct(): BelongsTo
    {
        return $this->belongsTo(PdfProduct::class);
    }
}
