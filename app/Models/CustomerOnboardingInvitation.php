<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerOnboardingInvitation extends Model
{
    public const STATUS_PENDING = 'PENDING';
    public const STATUS_MATCHED = 'MATCHED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_CONFLICT = 'CONFLICT';
    public const STATUS_REVOKED = 'REVOKED';

    public const STAGE_INVITE_CREATED = 'INVITE_CREATED';
    public const STAGE_INVITE_SENT = 'INVITE_SENT';
    public const STAGE_EMAIL_VERIFIED = 'EMAIL_VERIFIED';
    public const STAGE_WHATSAPP_VERIFIED = 'WHATSAPP_VERIFIED';
    public const STAGE_PASSWORD_SET = 'PASSWORD_SET';
    public const STAGE_TOTP_SETUP = 'TOTP_SETUP';
    public const STAGE_TERMS_ACCEPTED = 'TERMS_ACCEPTED';
    public const STAGE_COMPLETED = 'COMPLETED';

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'whatsapp_number',
        'country_code',
        'language_code',
        'referral_code_input',
        'referred_by_user_id',
        'status',
        'onboarding_stage',
        'activation_token_hash',
        'invited_at',
        'last_invited_at',
        'invite_send_count',
        'active_email_hash',
        'active_whatsapp_hash',
        'matched_user_id',
        'created_by_admin_id',
        'internal_note',
        'expires_at',
        'matched_at',
        'revoked_at',
        'revoked_by_admin_id',
    ];

    protected $hidden = [
        'activation_token_hash',
        'active_email_hash',
        'active_whatsapp_hash',
        'internal_note',
    ];

    protected function casts(): array
    {
        return [
            'internal_note' => 'encrypted',
            'invited_at' => 'datetime',
            'last_invited_at' => 'datetime',
            'invite_send_count' => 'integer',
            'expires_at' => 'datetime',
            'matched_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'referred_by_user_id'
        );
    }

    public function matchedUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'matched_user_id'
        );
    }

    public function createdByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'created_by_admin_id'
        );
    }

    public function revokedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'revoked_by_admin_id'
        );
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function hasUsableActivationToken(): bool
    {
        return $this->isPending()
            && filled($this->activation_token_hash)
            && $this->revoked_at === null
            && $this->expires_at->isFuture();
    }
}
