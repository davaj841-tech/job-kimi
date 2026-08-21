<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Transaction
 *
 * @property-read Transaction $resource
 */
class TransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->user_id,
            'user_name' => $this->when($this->relationLoaded('user'), $this->user?->name),
            'user_mobile' => $this->when($this->relationLoaded('user'), $this->user?->mobile),
            'user' => $this->when($this->relationLoaded('user') && $this->user, [
                'id' => $this->user?->id,
                'name' => $this->user?->name,
                'mobile' => $this->user?->mobile,
                'email' => $this->user?->email,
            ]),
            'amount' => (int) $this->amount,
            'type' => $this->type,
            'gateway' => $this->gateway,
            'status' => $this->status,
            'reference_id' => $this->reference_id,
            'description' => $this->description,
            'payable_type' => $this->payable_type,
            'payable_id' => $this->payable_id,
            'invoice_number' => $this->invoice_number,
            'invoice_pdf' => $this->invoice_pdf
                ? url('/api/v1/transactions/'.$this->id.'/invoice')
                : null,
            'discount_amount' => (int) ($this->discount_amount ?? 0),
            'original_amount' => $this->original_amount !== null ? (int) $this->original_amount : null,
            'coupon_id' => $this->coupon_id,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
