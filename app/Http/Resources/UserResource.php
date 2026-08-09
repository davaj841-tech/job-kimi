<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'mobile' => $this->mobile,
            'username' => $this->username,
            'email' => $this->email,
            'name' => $this->name,
            'province' => $this->province,
            'national_code' => $this->national_code,
            'avatar' => $this->avatar,
            'role' => $this->role,
            'wallet_balance' => $this->wallet_balance,
            'is_verified' => $this->is_verified,
            'subscription_plan' => $this->whenLoaded('subscriptionPlan', fn () => [
                'id' => $this->subscriptionPlan?->id,
                'name' => $this->subscriptionPlan?->name,
                'duration_days' => $this->subscriptionPlan?->duration_days,
                'features' => $this->subscriptionPlan?->features,
            ]),
            'subscription_expires_at' => $this->subscription_expires_at?->toIso8601String(),
            'has_active_subscription' => $this->hasActiveSubscription(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
