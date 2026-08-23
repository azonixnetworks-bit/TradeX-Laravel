<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfileChangeChallenge extends Model
{
    use HasFactory;

    public const PURPOSE_WHATSAPP = 'WHATSAPP_CHANGE';
    public const PURPOSE_TELEGRAM = 'TELEGRAM_CHANGE';

    protected $fillable = [
        'uuid',
        'user_id',
        'purpose',
        'challenge_token_hash',
        'new_value',
        'otp_hmac',
        'attempts',
        'expires_at',
        'consumed_at',
        'invalidated_at',
        'request_ip',
        'request_user_agent',
    ];

    protected $hidden = [
        'challenge_token_hash',
        'new_value',
        'otp_hmac',
    ];

    protected function casts(): array
    {
        return [
            'new_value' => 'encrypted',
            'attempts' => 'integer',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->invalidated_at === null
            && $this->expires_at->isFuture();
    }
}
