<?php

namespace App\Models;

use App\Notifications\AdminResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class AdminUser extends Authenticatable
{
    use HasFactory;
    use Notifiable;
    use SoftDeletes;

    protected $table = 'admin_users';

    protected $fillable = [
        'uuid',
        'name',
        'email',
        'email_verified_at',
        'password',
        'is_active',
        'failed_login_attempts',
        'locked_until',
        'last_login_at',
        'last_login_ip',
        'totp_secret',
        'totp_setup_at',
        'totp_confirmed_at',
        'totp_last_used_step',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'totp_secret',
        'totp_last_used_step',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',
            'totp_setup_at' => 'datetime',
            'totp_confirmed_at' => 'datetime',
            'totp_last_used_step' => 'integer',
            'totp_secret' => 'encrypted',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminRole::class,
            'admin_user_roles',
            'admin_user_id',
            'admin_role_id'
        )->withTimestamps();
    }

    public function emailDeliveries(): HasMany
    {
        return $this->hasMany(AdminEmailDelivery::class);
    }

    public function latestEmailDelivery(): HasOne
    {
        return $this->hasOne(AdminEmailDelivery::class)->latestOfMany();
    }

    public function hasRole(string $roleCode): bool
    {
        return $this->roles()
            ->where('code', $roleCode)
            ->where('is_active', true)
            ->exists();
    }

    public function isSuperAdmin(): bool
    {
        return $this->hasRole('SUPER_ADMIN');
    }

    public function hasPermission(string $permissionCode): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        return $this->roles()
            ->where('admin_roles.is_active', true)
            ->whereHas('permissions', function ($query) use ($permissionCode) {
                $query->where(
                    'admin_permissions.code',
                    $permissionCode
                );
            })
            ->exists();
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null
            && $this->locked_until->isFuture();
    }

    public function canLogin(): bool
    {
        return $this->is_active
            && ! $this->isLocked()
            && $this->deleted_at === null;
    }

    public function hasConfirmedTotp(): bool
    {
        return filled(
            $this->getRawOriginal(
                'totp_secret'
            )
        )
            && $this->totp_confirmed_at !== null;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(
            new AdminResetPasswordNotification(
                (string) $token
            )
        );
    }
}
