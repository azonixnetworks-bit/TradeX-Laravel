<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserRegistrationSession extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_COMPLETED = 'COMPLETED';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_SUPERSEDED = 'SUPERSEDED';

    protected $fillable = [
        'uuid',
        'registration_token_hash',
        'name',
        'email',
        'whatsapp_number',
        'password_hash',
        'referral_code_input',
        'referred_by_user_id',
        'country_code',
        'language_code',
        'email_verified_at',
        'whatsapp_verified_at',
        'totp_secret',
        'totp_setup_at',
        'totp_confirmed_at',
        'totp_last_used_step',
        'status',
        'expires_at',
        'completed_user_id',
        'completed_at',
        'start_ip',
        'start_user_agent',
    ];

    protected $hidden = [
        'registration_token_hash',
        'password_hash',
        'totp_secret',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'whatsapp_verified_at' => 'datetime',
            'totp_secret' => 'encrypted',
            'totp_setup_at' => 'datetime',
            'totp_confirmed_at' => 'datetime',
            'totp_last_used_step' => 'integer',
            'expires_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'referred_by_user_id'
        );
    }

    public function completedUser(): BelongsTo
    {
        return $this->belongsTo(
            User::class,
            'completed_user_id'
        );
    }

    public function otpChallenges(): HasMany
    {
        return $this->hasMany(
            UserRegistrationOtpChallenge::class,
            'user_registration_session_id'
        );
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
