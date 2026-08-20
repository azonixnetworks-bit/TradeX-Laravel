<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AdminPermission extends Model
{
    protected $table = 'admin_permissions';

    protected $fillable = [
        'code',
        'name',
        'module',
        'description',
    ];

    /**
     * Roles that contain this permission.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            AdminRole::class,
            'admin_role_permissions',
            'admin_permission_id',
            'admin_role_id'
        )->withTimestamps();
    }
}