<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

class CopyTradeBrokerAccount extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_VERIFIED = 'VERIFIED';
    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_SUSPENDED = 'SUSPENDED';
    public const STATUS_DISCONNECTED = 'DISCONNECTED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_NEEDS_RESUBMISSION = 'NEEDS_RESUBMISSION';
    public const STATUS_ESCALATED = 'ESCALATED';

    public const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_VERIFIED,
        self::STATUS_ACTIVE,
        self::STATUS_SUSPENDED,
        self::STATUS_DISCONNECTED,
        self::STATUS_REJECTED,
        self::STATUS_NEEDS_RESUBMISSION,
        self::STATUS_ESCALATED,
    ];

    public const VERIFIED_OR_OPERATIONAL_STATUSES = [
        self::STATUS_VERIFIED,
        self::STATUS_ACTIVE,
        self::STATUS_SUSPENDED,
        self::STATUS_DISCONNECTED,
    ];

    protected $fillable = [
        'uuid',
        'user_id',
        'broker_id',
        'broker_market_id',
        'onboarding_intent_id',
        'account_number',
        'account_number_fingerprint',
        'account_number_last4',
        'status',
        'resubmission_count',
        'verified_currency',
        'verified_account_mode',
        'partner_attribution_confirmed_at',
        'last_status_reason',
        'submitted_at',
        'verified_at',
        'activated_at',
        'suspended_at',
        'disconnected_at',
        'rejected_at',
        'escalated_at',
        'verified_by_admin_id',
        'status_changed_by_admin_id',
    ];

    protected $hidden = [
        'account_number',
        'account_number_fingerprint',
        'last_status_reason',
    ];

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
            'last_status_reason' => 'encrypted',
            'resubmission_count' => 'integer',
            'partner_attribution_confirmed_at' => 'datetime',
            'submitted_at' => 'datetime',
            'verified_at' => 'datetime',
            'activated_at' => 'datetime',
            'suspended_at' => 'datetime',
            'disconnected_at' => 'datetime',
            'rejected_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $account): void {
            if (blank($account->uuid)) {
                $account->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeBroker::class,
            'broker_id'
        );
    }

    public function brokerMarket(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeBrokerMarket::class,
            'broker_market_id'
        );
    }

    public function onboardingIntent(): BelongsTo
    {
        return $this->belongsTo(
            CopyTradeOnboardingIntent::class,
            'onboarding_intent_id'
        );
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(
            CopyTradeBrokerAccountReview::class,
            'broker_account_id'
        )->latest('created_at');
    }

    public function verifiedBalanceSnapshots(): HasMany
    {
        return $this->hasMany(
            CopyTradeVerifiedBalanceSnapshot::class,
            'broker_account_id'
        )->latest('observed_at');
    }

    public function latestVerifiedBalanceSnapshot(): HasOne
    {
        return $this->hasOne(
            CopyTradeVerifiedBalanceSnapshot::class,
            'broker_account_id'
        )->latestOfMany('observed_at');
    }

    public function accountBenefit(): HasOne
    {
        return $this->hasOne(
            CopyTradeAccountBenefit::class,
            'broker_account_id'
        );
    }

    public function entitlementPeriods(): HasMany
    {
        return $this->hasMany(
            CopyTradeEntitlementPeriod::class,
            'broker_account_id'
        )->latest('starts_at');
    }

    public function activationEvaluations(): HasMany
    {
        return $this->hasMany(
            CopyTradeActivationEvaluation::class,
            'broker_account_id'
        )->latest('evaluated_at');
    }

    public function latestActivationEvaluation(): HasOne
    {
        return $this->hasOne(
            CopyTradeActivationEvaluation::class,
            'broker_account_id'
        )->latestOfMany('evaluated_at');
    }

    public function billingCycles(): HasMany
    {
        return $this->hasMany(
            CopyTradeBillingCycle::class,
            'broker_account_id'
        )->latest('period_end');
    }

    public function billingSnapshots(): HasMany
    {
        return $this->hasMany(
            CopyTradeBillingSnapshot::class,
            'broker_account_id'
        )->latest('event_at');
    }

    public function billingBalanceEvents(): HasMany
    {
        return $this->hasMany(
            CopyTradeBillingBalanceEvent::class,
            'broker_account_id'
        )->latest('occurred_at');
    }

    public function billingInvoices(): HasMany
    {
        return $this->hasMany(
            CopyTradeBillingInvoice::class,
            'broker_account_id'
        )->latest('issued_at');
    }

    public function billingAdjustments(): HasMany
    {
        return $this->hasMany(
            CopyTradeBillingAdjustment::class,
            'broker_account_id'
        )->latest('created_at');
    }

    public function verifiedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'verified_by_admin_id'
        );
    }

    public function statusChangedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'status_changed_by_admin_id'
        );
    }

    public function maskedAccountNumber(): string
    {
        return '••••'.($this->account_number_last4 ?: '');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isVerified(): bool
    {
        return $this->status === self::STATUS_VERIFIED;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    public function needsResubmission(): bool
    {
        return $this->status === self::STATUS_NEEDS_RESUBMISSION;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isEscalated(): bool
    {
        return $this->status === self::STATUS_ESCALATED;
    }

    public function hasVerifiedBrokerRelationship(): bool
    {
        return in_array(
            $this->status,
            self::VERIFIED_OR_OPERATIONAL_STATUSES,
            true
        );
    }
}
