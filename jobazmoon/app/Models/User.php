<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'mobile',
        'username',
        'email',
        'name',
        'national_code',
        'avatar',
        'role',
        'status',
        'wallet_balance',
        'subscription_plan_id',
        'subscription_expires_at',
        'otp_code',
        'otp_expires_at',
        'failed_login_attempts',
        'locked_until',
        'is_verified',
        'password',
        'notification_preferences',
        'locale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'otp_code',
    ];

    protected function casts(): array
    {
        return [
            'wallet_balance' => 'decimal:0',
            'subscription_expires_at' => 'datetime',
            'otp_expires_at' => 'datetime',
            'locked_until' => 'datetime',
            'is_verified' => 'boolean',
            'failed_login_attempts' => 'integer',
            'password' => 'hashed',
            'notification_preferences' => 'array',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return in_array($this->role, ['admin', 'operator'], true)
            && ($this->status ?? 'active') === 'active';
    }

    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    public function resumes(): HasMany
    {
        return $this->hasMany(Resume::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function pdfPurchases(): HasMany
    {
        return $this->hasMany(PdfPurchase::class);
    }

    /** بررسی فعال بودن اشتراک کاربر */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription_expires_at !== null
            && $this->subscription_expires_at->isFuture();
    }

    public function isBlocked(): bool
    {
        return ($this->status ?? 'active') === 'blocked';
    }
}
