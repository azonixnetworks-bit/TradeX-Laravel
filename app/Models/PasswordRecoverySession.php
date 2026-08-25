<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class PasswordRecoverySession extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'access_token_hash',
        'email_hmac',
        'whatsapp_hmac',
        'email_verified_at',
        'whatsapp_verified_at',
        'totp_verified_at',
        'reset_token_hash',
        'reset_token_issued_at',
        'reset_token_expires_at',
        'expires_at',
        'consumed_at',
        'invalidated_at',
    ];

    protected $hidden = [
        'access_token_hash',
        'email_hmac',
        'whatsapp_hmac',
        'reset_token_hash',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'whatsapp_verified_at' => 'datetime',
            'totp_verified_at' => 'datetime',
            'reset_token_issued_at' => 'datetime',
            'reset_token_expires_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $session): void {
            if (blank($session->uuid)) {
                $session->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function otpChallenges(): HasMany
    {
        return $this->hasMany(PasswordRecoveryOtpChallenge::class);
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->invalidated_at === null
            && $this->expires_at->isFuture();
    }

    public function hasVerifiedContacts(): bool
    {
        return $this->email_verified_at !== null
            && $this->whatsapp_verified_at !== null;
    }

    public function isReadyForResetLink(): bool
    {
        return $this->hasVerifiedContacts()
            && $this->totp_verified_at !== null;
    }
}
