<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $fillable = [
        'uuid',

        'name',

        'email',
        'email_verified_at',

        'whatsapp_number',
        'whatsapp_verified_at',

        'password',

        'referral_code',
        'referred_by_user_id',

        'country_code',
        'language_code',

        'account_type',
        'status',

        'status_reason',
        'status_changed_at',
        'status_changed_by_admin_id',

        'premium_activated_at',

        'suspended_at',
        'blocked_at',

        'last_login_at',
        'last_login_ip',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'totp_secret',
        'totp_last_used_step',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',

            'whatsapp_verified_at' => 'datetime',

            'status_changed_at' => 'datetime',

            'premium_activated_at' => 'datetime',

            'suspended_at' => 'datetime',

            'blocked_at' => 'datetime',

            'last_login_at' => 'datetime',

            'totp_secret' => 'encrypted',

            'totp_confirmed_at' =>
                'datetime',

            'totp_last_used_step' =>
                'integer',

            'password' => 'hashed',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Referral Relationships
    |--------------------------------------------------------------------------
    */

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'referred_by_user_id'
        );
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(
            User::class,
            'referred_by_user_id'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Admin Status Relationship
    |--------------------------------------------------------------------------
    */

    public function statusChangedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'status_changed_by_admin_id'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Device Security Relationships
    |--------------------------------------------------------------------------
    */

    public function devices(): HasMany
    {
        return $this->hasMany(
            UserDevice::class
        );
    }

    public function refreshTokens(): HasMany
    {
        return $this->hasMany(
            UserRefreshToken::class
        );
    }

    public function activeMobileDevice(): HasOne
    {
        return $this->hasOne(
            UserDevice::class
        )
            ->where(
                'device_slot',
                UserDevice::SLOT_MOBILE
            )
            ->where(
                'is_active',
                true
            )
            ->whereNull(
                'revoked_at'
            );
    }

    public function activeDesktopDevice(): HasOne
    {
        return $this->hasOne(
            UserDevice::class
        )
            ->where(
                'device_slot',
                UserDevice::SLOT_DESKTOP
            )
            ->where(
                'is_active',
                true
            )
            ->whereNull(
                'revoked_at'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Profile Relationship
    |--------------------------------------------------------------------------
    */

    public function profile(): HasOne
    {
        return $this->hasOne(
            UserProfile::class
        );
    }


    /*
    |--------------------------------------------------------------------------
    | KYC Relationships
    |--------------------------------------------------------------------------
    */

    public function kycSubmissions(): HasMany
    {
        return $this->hasMany(
            KycSubmission::class
        );
    }

    public function latestKycSubmission(): HasOne
    {
        return $this->hasOne(
            KycSubmission::class
        )->latestOfMany();
    }

    public function verifiedKycSubmission(): HasOne
    {
        return $this->hasOne(
            KycSubmission::class
        )
            ->where(
                'status',
                KycSubmission::STATUS_VERIFIED
            )
            ->latestOfMany();
    }


    /*
    |--------------------------------------------------------------------------
    | Copy Trading Broker Relationships
    |--------------------------------------------------------------------------
    */

    public function copyTradeBrokerAccounts(): HasMany
    {
        return $this->hasMany(
            CopyTradeBrokerAccount::class
        );
    }

    public function copyTradeOnboardingIntents(): HasMany
    {
        return $this->hasMany(
            CopyTradeOnboardingIntent::class
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Investment Relationships
    |--------------------------------------------------------------------------
    */

    public function investments(): HasMany
    {
        return $this->hasMany(
            Investment::class
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Product / Entitlement Relationships
    |--------------------------------------------------------------------------
    */

    public function userEntitlements(): HasMany
    {
        return $this->hasMany(
            UserEntitlement::class
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Account Type Helpers
    |--------------------------------------------------------------------------
    */

    public function isFree(): bool
    {
        return $this->account_type === 'FREE';
    }

    public function isPremium(): bool
    {
        return $this->account_type === 'PREMIUM';
    }


    /*
    |--------------------------------------------------------------------------
    | Account Status Helpers
    |--------------------------------------------------------------------------
    */

    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }

    public function isActive(): bool
    {
        return $this->status === 'ACTIVE';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'SUSPENDED';
    }

    public function isBlocked(): bool
    {
        return $this->status === 'BLOCKED';
    }


    /*
    |--------------------------------------------------------------------------
    | Access Helpers
    |--------------------------------------------------------------------------
    */

    public function canLogin(): bool
    {
        return $this->isActive()
            && ! $this->trashed();
    }

    public function canAccessPremium(): bool
    {
        if (
            ! $this->isActive()
            || $this->trashed()
        ) {
            return false;
        }

        /*
         * ADMIN-25:
         * account_type is only a compatibility/UI mirror.
         * The active EDUCATION_PREMIUM entitlement is authoritative.
         */
        return $this->userEntitlements()
            ->where(
                'product_code',
                Product::CODE_EDUCATION_PREMIUM
            )
            ->where(
                'status',
                UserEntitlement::STATUS_ACTIVE
            )
            ->exists();
    }

    public function hasConfirmedTotp(): bool
    {
        return $this->totp_confirmed_at
            !== null
            && filled($this->totp_secret);
    }

    public function hasVerifiedKyc(): bool
    {
        return $this->verifiedKycSubmission()
            ->exists();
    }
}
