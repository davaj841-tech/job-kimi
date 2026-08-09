<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pdfProduct(): BelongsTo
    {
        return $this->belongsTo(PdfProduct::class);
    }
}
