<?php

namespace App\Models;

use App\Support\StaffRoles;
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
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $mobile
 * @property string|null $username
 * @property string|null $email
 * @property string|null $name
 * @property string|null $national_code
 * @property string|null $province
 * @property string|null $avatar
 * @property string|null $home_phone
 * @property string|null $military_status
 * @property string|null $insurance_history
 * @property string|null $birth_date
 * @property string|null $birth_province
 * @property string|null $birth_city
 * @property string|null $marital_status
 * @property string|null $field_of_study
 * @property string|null $address
 * @property string|null $postal_code
 * @property string|null $role
 * @property array<int, string>|null $operator_permissions
 * @property string|null $status
 * @property int|null $subscription_plan_id
 * @property Carbon|null $subscription_expires_at
 * @property numeric-string|float|int|null $wallet_balance
 * @property bool $is_verified
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $locked_until
 * @property string|null $otp_code
 * @property Carbon|null $otp_expires_at
 * @property-read SubscriptionPlan|null $subscriptionPlan
 * @property-read Carbon|string|null $last_transaction_at
 * @property-read \Illuminate\Notifications\DatabaseNotificationCollection<int, \Illuminate\Notifications\DatabaseNotification> $unreadNotifications
 */
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'mobile',
        'username',
        'email',
        'name',
        'national_code',
        'province',
        'avatar',
        'home_phone',
        'military_status',
        'insurance_history',
        'birth_date',
        'birth_province',
        'birth_city',
        'marital_status',
        'field_of_study',
        'address',
        'postal_code',
        'role',
        'operator_permissions',
        'status',
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
            'operator_permissions' => 'array',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return StaffRoles::isStaffAdmin($this)
            && ($this->status ?? 'active') === 'active';
    }

    /** @return BelongsTo<SubscriptionPlan, $this> */
    public function subscriptionPlan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class);
    }

    /** @return HasMany<ExamAttempt, $this> */
    public function attempts(): HasMany
    {
        return $this->hasMany(ExamAttempt::class);
    }

    /** @return HasMany<Resume, $this> */
    public function resumes(): HasMany
    {
        return $this->hasMany(Resume::class);
    }

    /** @return HasMany<Transaction, $this> */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** @return HasMany<WalletLedger, $this> */
    public function walletLedgers(): HasMany
    {
        return $this->hasMany(WalletLedger::class);
    }

    protected static function booted(): void
    {
        static::saving(static function (User $user): void {
            if (! $user->exists) {
                return;
            }
            if ($user->isDirty('wallet_balance') && ! \App\Services\WalletService::isMutatingBalance()) {
                $user->wallet_balance = $user->getOriginal('wallet_balance');
            }
        });

        static::created(static function (User $user): void {
            if (! \Illuminate\Support\Facades\Schema::hasTable('wallet_ledgers')) {
                return;
            }
            $balance = (int) $user->wallet_balance;
            if ($balance === 0) {
                return;
            }
            app(\App\Services\WalletService::class)->recordOpeningBalance($user, $balance);
        });
    }

    /** @return HasMany<PdfPurchase, $this> */
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

    public function isActiveAccount(): bool
    {
        return ($this->status ?? 'active') === 'active';
    }

    public function isBlocked(): bool
    {
        return ($this->status ?? 'active') === 'blocked';
    }

    public function avatarUrl(): ?string
    {
        $avatar = $this->avatar;
        if (! is_string($avatar) || $avatar === '') {
            return null;
        }
        if (str_starts_with($avatar, 'data:image') || str_starts_with($avatar, 'http://') || str_starts_with($avatar, 'https://') || str_starts_with($avatar, '/')) {
            return $avatar;
        }

        return \App\Support\PublicAsset::url($avatar) ?: null;
    }
}
