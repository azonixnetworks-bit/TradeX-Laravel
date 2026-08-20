<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'is_active' => 'boolean',
            'locked_until' => 'datetime',
            'last_login_at' => 'datetime',

            // Automatically hashes plain password values.
            'password' => 'hashed',
        ];
    }

    /**
     * Roles assigned to this admin.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminRole::class,
            'admin_user_roles',
            'admin_user_id',
            'admin_role_id'
        )->withTimestamps();
    }

    /**
     * Check whether admin has a specific role.
     */
    public function hasRole(string $roleCode): bool
    {
        return $this->roles()
            ->where('code', $roleCode)
            ->where('is_active', true)
            ->exists();
    }

    /**
     * Check whether admin is Super Admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('SUPER_ADMIN');
    }

    /**
     * Check whether admin has permission.
     */
    public function hasPermission(string $permissionCode): bool
    {
        // Super Admin automatically has full access.
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

    /**
     * Check if admin account is currently locked.
     */
    public function isLocked(): bool
    {
        return $this->locked_until !== null
            && $this->locked_until->isFuture();
    }

    /**
     * Check if admin can login.
     */
    public function canLogin(): bool
    {
        return $this->is_active
            && ! $this->isLocked()
            && $this->deleted_at === null;
    }
}