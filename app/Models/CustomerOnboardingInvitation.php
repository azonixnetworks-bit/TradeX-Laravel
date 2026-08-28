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
        'matched_user_id',
        'created_by_admin_id',
        'internal_note',
        'expires_at',
        'matched_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'matched_at' => 'datetime',
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

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
