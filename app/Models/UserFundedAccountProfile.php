<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class UserFundedAccountProfile extends Model
{
    public const SOURCE_CUSTOMER_SELECTION = 'CUSTOMER_SELECTION';

    protected $fillable = [
        'uuid',
        'user_id',
        'funded_account_partner_id',
        'program_name',
        'phase',
        'is_enabled',
        'source',
        'selected_at',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'selected_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $profile): void {
            if (blank($profile->uuid)) {
                $profile->uuid = (string) Str::uuid();
            }

            self::normalize($profile);
        });

        static::updating(function (self $profile): void {
            self::normalize($profile);
        });
    }

    private static function normalize(self $profile): void
    {
        $profile->phase = strtoupper(trim((string) $profile->phase));
        $profile->source = strtoupper(trim((string) $profile->source));
        $profile->program_name = filled($profile->program_name)
            ? trim((string) $profile->program_name)
            : null;
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(FundedAccountPartner::class, 'funded_account_partner_id');
    }
}
