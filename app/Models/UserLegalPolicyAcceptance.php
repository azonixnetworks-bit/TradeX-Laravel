<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use LogicException;

class UserLegalPolicyAcceptance extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'legal_policy_version_id',
        'policy_type',
        'policy_version_number',
        'content_sha256',
        'accepted_at',
        'accepted_ip',
        'accepted_user_agent',
        'device_uuid',
        'device_platform',
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
        static::creating(function (self $acceptance): void {
            if (blank($acceptance->uuid)) {
                $acceptance->uuid = Str::uuid()->toString();
            }
        });

        static::updating(function (): void {
            throw new LogicException(
                'Legal policy acceptance evidence is immutable.'
            );
        });

        static::deleting(function (): void {
            throw new LogicException(
                'Legal policy acceptance evidence cannot be deleted through Eloquent.'
            );
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function legalPolicyVersion(): BelongsTo
    {
        return $this->belongsTo(LegalPolicyVersion::class);
    }
}
