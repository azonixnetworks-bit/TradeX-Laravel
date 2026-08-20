<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdminRole extends Model
{
    protected $table = 'admin_roles';

    protected $fillable = [
        'code',
        'name',
        'description',
        'is_system',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Admin users assigned to this role.
     */
    public function admins(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminUser::class,
            'admin_user_roles',
            'admin_role_id',
            'admin_user_id'
        )->withTimestamps();
    }

    /**
     * Permissions assigned to this role.
     */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminPermission::class,
            'admin_role_permissions',
            'admin_role_id',
            'admin_permission_id'
        )->withTimestamps();
    }

    public function hasPermission(string $permissionCode): bool
    {
        return $this->permissions()
            ->where('code', $permissionCode)
            ->exists();
    }
}