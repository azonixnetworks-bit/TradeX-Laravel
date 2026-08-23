<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserRegistrationOtpChallenge extends Model
{
    use HasFactory;

    public const CHANNEL_EMAIL = 'EMAIL';
    public const CHANNEL_WHATSAPP = 'WHATSAPP';

    protected $fillable = [
        'user_registration_session_id',
        'channel',
        'code_hmac',
        'attempts',
        'sent_at',
        'expires_at',
        'consumed_at',
        'invalidated_at',
    ];

    protected $hidden = [
        'code_hmac',
    ];

    protected function casts(): array
    {
        return [
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'expires_at' => 'datetime',
            'consumed_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    public function registrationSession(): BelongsTo
    {
        return $this->belongsTo(
            UserRegistrationSession::class,
            'user_registration_session_id'
        );
    }

    public function isUsable(): bool
    {
        return $this->consumed_at === null
            && $this->invalidated_at === null
            && $this->expires_at->isFuture();
    }
}
