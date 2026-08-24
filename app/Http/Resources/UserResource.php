<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\OperatorPermissions;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 *
 * @property-read User $resource
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
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
            'avatar' => $this->avatarUrl(),
            'home_phone' => $this->home_phone,
            'military_status' => $this->military_status,
            'insurance_history' => $this->insurance_history,
            'birth_date' => $this->birth_date,
            'birth_province' => $this->birth_province,
            'birth_city' => $this->birth_city,
            'marital_status' => $this->marital_status,
            'field_of_study' => $this->field_of_study,
            'address' => $this->address,
            'postal_code' => $this->postal_code,
            'role' => $this->role,
            'operator_permissions' => $this->role === 'operator'
                ? OperatorPermissions::normalize($this->operator_permissions)
                : null,
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
