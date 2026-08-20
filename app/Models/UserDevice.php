<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class UserDevice extends Model
{
    use HasFactory;

    public const SLOT_MOBILE = 'MOBILE';

    public const SLOT_DESKTOP = 'DESKTOP';

    public const PLATFORM_ANDROID = 'ANDROID';

    public const PLATFORM_IOS = 'IOS';

    public const PLATFORM_WINDOWS = 'WINDOWS';

    public const PLATFORM_MACOS = 'MACOS';

    public const PLATFORM_WEB = 'WEB';


    protected $fillable = [
        'uuid',
        'user_id',

        'device_slot',
        'platform',

        'device_name',
        'device_model',
        'os_version',
        'app_version',

        'installation_id_hash',
        'device_public_key',

        'is_trusted',
        'is_active',

        'registered_at',
        'trusted_at',

        'last_seen_at',
        'last_login_at',
        'last_login_ip',

        'revoked_at',
        'revoked_by_admin_id',
        'revoke_reason',
    ];


    protected function casts(): array
    {
        return [
            'is_trusted' => 'boolean',
            'is_active' => 'boolean',

            'registered_at' => 'datetime',
            'trusted_at' => 'datetime',

            'last_seen_at' => 'datetime',
            'last_login_at' => 'datetime',

            'revoked_at' => 'datetime',
        ];
    }


    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function user(): BelongsTo
    {
        return $this->belongsTo(
            User::class
        );
    }


    public function revokedByAdmin(): BelongsTo
    {
        return $this->belongsTo(
            AdminUser::class,
            'revoked_by_admin_id'
        );
    }


    public function refreshTokens(): HasMany
    {
        return $this->hasMany(
            UserRefreshToken::class,
            'user_device_id'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Query Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeActive(
        Builder $query
    ): Builder {

        return $query
            ->where('is_active', true)
            ->whereNull('revoked_at');
    }


    public function scopeTrusted(
        Builder $query
    ): Builder {

        return $query
            ->where('is_trusted', true);
    }


    public function scopeMobile(
        Builder $query
    ): Builder {

        return $query->where(
            'device_slot',
            self::SLOT_MOBILE
        );
    }


    public function scopeDesktop(
        Builder $query
    ): Builder {

        return $query->where(
            'device_slot',
            self::SLOT_DESKTOP
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    public function isMobile(): bool
    {
        return $this->device_slot
            === self::SLOT_MOBILE;
    }


    public function isDesktop(): bool
    {
        return $this->device_slot
            === self::SLOT_DESKTOP;
    }


    public function isRevoked(): bool
    {
        return $this->revoked_at !== null
            || ! $this->is_active;
    }
}