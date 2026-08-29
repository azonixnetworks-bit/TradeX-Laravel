<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class OnboardingLegalPolicyAcceptance extends Model
{
    protected $fillable = [
        'uuid',
        'user_registration_session_id',
        'legal_policy_version_id',
        'policy_type',
        'policy_version_number',
        'content_sha256',
        'accepted_at',
        'accepted_ip',
        'accepted_user_agent',
    ];

    protected $hidden = [
        'accepted_ip',
        'accepted_user_agent',
    ];

    protected function casts(): array
    {
        return [
            'policy_version_number' => 'integer',
            'accepted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(
            function (self $acceptance): void {
                if (blank($acceptance->uuid)) {
                    $acceptance->uuid =
                        Str::uuid()->toString();
                }
            }
        );

        static::updating(function (): void {
            throw new LogicException(
                'Onboarding legal acceptance evidence is immutable.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Onboarding legal acceptance evidence cannot be deleted through Eloquent.'
            );
        });
    }

    public function registrationSession(): BelongsTo
    {
        return $this->belongsTo(
            UserRegistrationSession::class,
            'user_registration_session_id'
        );
    }

    public function legalPolicyVersion(): BelongsTo
    {
        return $this->belongsTo(
            LegalPolicyVersion::class,
            'legal_policy_version_id'
        );
    }
}
