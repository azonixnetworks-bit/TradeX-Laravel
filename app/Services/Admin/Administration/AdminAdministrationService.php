<?php

namespace App\Services\Admin\Administration;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminAdministrationService
{
    public function adminIndex(
        array $filters
    ): array {
        $query = AdminUser::query()
            ->with([
                'roles:id,code,name,is_active',
            ]);

        $search = trim(
            (string) (
                $filters['search'] ?? ''
            )
        );

        if ($search !== '') {
            $query->where(
                function (Builder $builder) use ($search): void {
                    $builder
                        ->where(
                            'name',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'email',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'uuid',
                            'like',
                            '%'.$search.'%'
                        );
                }
            );
        }

        $status = strtoupper(
            trim(
                (string) (
                    $filters['status'] ?? ''
                )
            )
        );

        if ($status === 'ACTIVE') {
            $query->where(
                'is_active',
                true
            );
        } elseif ($status === 'DISABLED') {
            $query->where(
                'is_active',
                false
            );
        } elseif ($status === 'LOCKED') {
            $query->where(
                'locked_until',
                '>',
                now()
            );
        }

        $roleCode = strtoupper(
            trim(
                (string) (
                    $filters['role'] ?? ''
                )
            )
        );

        if ($roleCode !== '') {
            $query->whereHas(
                'roles',
                fn (Builder $builder) =>
                    $builder->where(
                        'code',
                        $roleCode
                    )
            );
        }

        return [
            'admins' =>
                $query
                    ->orderByDesc('id')
                    ->paginate(
                        $this->perPage(
                            $filters
                        )
                    )
                    ->withQueryString(),

            'roles' =>
                AdminRole::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'code',
                        'name',
                    ]),

            'stats' => [
                'total' =>
                    AdminUser::query()->count(),

                'active' =>
                    AdminUser::query()
                        ->where('is_active', true)
                        ->count(),

                'disabled' =>
                    AdminUser::query()
                        ->where('is_active', false)
                        ->count(),

                'locked' =>
                    AdminUser::query()
                        ->where(
                            'locked_until',
                            '>',
                            now()
                        )
                        ->count(),
            ],
        ];
    }

    public function adminEditData(
        AdminUser $actor,
        AdminUser $target
    ): array {
        $target->load([
            'roles:id,code,name,is_active,is_system',
        ]);

        return [
            'adminUser' =>
                $target,

            'assignableRoles' =>
                $this->assignableRoles(),

            'protected' =>
                $target->isSuperAdmin(),

            'canUpdate' =>
                $actor->hasPermission(
                    'admins.update'
                )
                && ! $target->isSuperAdmin(),

            'canManageRoles' =>
                $actor->hasPermission(
                    'admins.update'
                )
                && $actor->hasPermission(
                    'roles.manage'
                )
                && ! $target->isSuperAdmin(),

            'canManageStatus' =>
                $actor->hasPermission(
                    'admins.disable'
                )
                && ! $target->isSuperAdmin()
                && $actor->getKey()
                    !== $target->getKey(),
        ];
    }

    public function assignableRoles()
    {
        return AdminRole::query()
            ->where('is_active', true)
            ->where(
                'code',
                '!=',
                'SUPER_ADMIN'
            )
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get([
                'id',
                'code',
                'name',
                'description',
                'is_system',
            ]);
    }

    public function createAdmin(
        AdminUser $actor,
        array $data,
        Request $request
    ): AdminUser {
        return DB::transaction(
            function () use (
                $actor,
                $data,
                $request
            ): AdminUser {
                $roles =
                    $this->lockedAssignableRoles(
                        $data['role_ids']
                    );

                $admin = AdminUser::query()
                    ->create([
                        'uuid' =>
                            Str::uuid()->toString(),

                        'name' =>
                            trim($data['name']),

                        'email' =>
                            strtolower(
                                trim($data['email'])
                            ),

                        'email_verified_at' =>
                            now(),

                        'password' =>
                            $data['password'],

                        'is_active' =>
                            true,

                        'failed_login_attempts' =>
                            0,
                    ]);

                $admin->roles()->sync(
                    $roles->pluck('id')->all()
                );

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId:
                        $actor->getKey(),
                    action:
                        'ADMIN_ACCOUNT_CREATED',
                    module:
                        'ADMINISTRATION',
                    targetType:
                        'ADMIN_USER',
                    targetId:
                        $admin->getKey(),
                    newValues: [
                        'role_codes' =>
                            $roles
                                ->pluck('code')
                                ->values()
                                ->all(),

                        'is_active' =>
                            true,
                    ],
                    ipAddress:
                        $request->ip(),
                    userAgent:
                        $request->userAgent()
                );

                return $admin;
            },
            3
        );
    }

    public function updateAdmin(
        AdminUser $actor,
        AdminUser $target,
        array $data,
        Request $request
    ): void {
        DB::transaction(
            function () use (
                $actor,
                $target,
                $data,
                $request
            ): void {
                $locked =
                    AdminUser::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $target->getKey()
                        );

                $this->assertAdminMutable(
                    $locked
                );

                $old = [
                    'name' =>
                        $locked->name,

                    'email' =>
                        $locked->email,
                ];

                $locked->forceFill([
                    'name' =>
                        trim($data['name']),

                    'email' =>
                        strtolower(
                            trim($data['email'])
                        ),
                ])->save();

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId:
                        $actor->getKey(),
                    action:
                        'ADMIN_ACCOUNT_UPDATED',
                    module:
                        'ADMINISTRATION',
                    targetType:
                        'ADMIN_USER',
                    targetId:
                        $locked->getKey(),
                    oldValues:
                        $old,
                    newValues: [
                        'name' =>
                            $locked->name,

                        'email' =>
                            $locked->email,
                    ],
                    ipAddress:
                        $request->ip(),
                    userAgent:
                        $request->userAgent()
                );
            },
            3
        );
    }

    public function updateAdminStatus(
        AdminUser $actor,
        AdminUser $target,
        array $data,
        Request $request
    ): void {
        DB::transaction(
            function () use (
                $actor,
                $target,
                $data,
                $request
            ): void {
                $locked =
                    AdminUser::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $target->getKey()
                        );

                $this->assertAdminMutable(
                    $locked
                );

                if (
                    $actor->getKey()
                    === $locked->getKey()
                ) {
                    abort(
                        403,
                        'You cannot change your own admin account status from this workflow.'
                    );
                }

                $newActive =
                    $data['status'] === 'ACTIVE';

                $oldActive =
                    (bool) $locked->is_active;

                $locked->forceFill([
                    'is_active' =>
                        $newActive,
                ])->save();

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId:
                        $actor->getKey(),
                    action:
                        $newActive
                            ? 'ADMIN_ACCOUNT_ENABLED'
                            : 'ADMIN_ACCOUNT_DISABLED',
                    module:
                        'ADMINISTRATION',
                    targetType:
                        'ADMIN_USER',
                    targetId:
                        $locked->getKey(),
                    oldValues: [
                        'is_active' =>
                            $oldActive,
                    ],
                    newValues: [
                        'is_active' =>
                            $newActive,

                        'reason' =>
                            $this->boundedReason(
                                $data['reason']
                            ),
                    ],
                    ipAddress:
                        $request->ip(),
                    userAgent:
                        $request->userAgent()
                );
            },
            3
        );
    }

    public function syncAdminRoles(
        AdminUser $actor,
        AdminUser $target,
        array $roleIds,
        Request $request
    ): void {
        DB::transaction(
            function () use (
                $actor,
                $target,
                $roleIds,
                $request
            ): void {
                $locked =
                    AdminUser::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $target->getKey()
                        );

                $this->assertAdminMutable(
                    $locked
                );

                $roles =
                    $this->lockedAssignableRoles(
                        $roleIds
                    );

                $oldCodes =
                    $locked->roles()
                        ->orderBy('code')
                        ->pluck('code')
                        ->values()
                        ->all();

                $locked->roles()->sync(
                    $roles->pluck('id')->all()
                );

                $newCodes =
                    $roles
                        ->pluck('code')
                        ->sort()
                        ->values()
                        ->all();

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId:
                        $actor->getKey(),
                    action:
                        'ADMIN_ACCOUNT_ROLES_CHANGED',
                    module:
                        'ADMINISTRATION',
                    targetType:
                        'ADMIN_USER',
                    targetId:
                        $locked->getKey(),
                    oldValues: [
                        'role_codes' =>
                            $oldCodes,
                    ],
                    newValues: [
                        'role_codes' =>
                            $newCodes,
                    ],
                    ipAddress:
                        $request->ip(),
                    userAgent:
                        $request->userAgent()
                );
            },
            3
        );
    }

    public function roleIndex(): array
    {
        return [
            'roles' =>
                AdminRole::query()
                    ->withCount([
                        'admins',
                        'permissions',
                    ])
                    ->orderByDesc(
                        'is_system'
                    )
                    ->orderBy('name')
                    ->get(),

            'stats' => [
                'total' =>
                    AdminRole::query()->count(),

                'system' =>
                    AdminRole::query()
                        ->where(
                            'is_system',
                            true
                        )
                        ->count(),

                'custom' =>
                    AdminRole::query()
                        ->where(
                            'is_system',
                            false
                        )
                        ->count(),

                'active' =>
                    AdminRole::query()
                        ->where(
                            'is_active',
                            true
                        )
                        ->count(),
            ],
        ];
    }

    public function rolePermissionsData(
        AdminUser $actor,
        AdminRole $role
    ): array {
        $role->load([
            'permissions:id,code,name,module,description',
        ]);

        return [
            'role' =>
                $role,

            'permissionGroups' =>
                $this->permissionGroups(),

            'protected' =>
                (bool) $role->is_system,

            'canManage' =>
                $actor->hasPermission(
                    'roles.manage'
                )
                && ! $role->is_system,
        ];
    }

    public function permissionGroups()
    {
        return AdminPermission::query()
            ->orderBy('module')
            ->orderBy('code')
            ->get()
            ->groupBy(
                fn (AdminPermission $permission) =>
                    $permission->module
                    ?: 'OTHER'
            );
    }

    public function createRole(
        AdminUser $actor,
        array $data,
        Request $request
    ): AdminRole {
        return DB::transaction(
            function () use (
                $actor,
                $data,
                $request
            ): AdminRole {
                $permissions =
                    $this->lockedPermissions(
                        $data['permission_ids']
                    );

                $role =
                    AdminRole::query()
                        ->create([
                            'code' =>
                                $data['code'],

                            'name' =>
                                trim($data['name']),

                            'description' =>
                                $this->nullableText(
                                    $data['description']
                                    ?? null
                                ),

                            'is_system' =>
                                false,

                            'is_active' =>
                                true,
                        ]);

                $role->permissions()->sync(
                    $permissions
                        ->pluck('id')
                        ->all()
                );

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId:
                        $actor->getKey(),
                    action:
                        'ADMIN_ROLE_CREATED',
                    module:
                        'ADMINISTRATION',
                    targetType:
                        'ADMIN_ROLE',
                    targetId:
                        $role->getKey(),
                    newValues: [
                        'code' =>
                            $role->code,

                        'permission_codes' =>
                            $permissions
                                ->pluck('code')
                                ->sort()
                                ->values()
                                ->all(),
                    ],
                    ipAddress:
                        $request->ip(),
                    userAgent:
                        $request->userAgent()
                );

                return $role;
            },
            3
        );
    }

    public function updateRole(
        AdminUser $actor,
        AdminRole $role,
        array $data,
        Request $request
    ): void {
        DB::transaction(
            function () use (
                $actor,
                $role,
                $data,
                $request
            ): void {
                $locked =
                    AdminRole::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $role->getKey()
                        );

                $this->assertCustomRole(
                    $locked
                );

                $old = [
                    'name' =>
                        $locked->name,

                    'description' =>
                        $locked->description,
                ];

                $locked->forceFill([
                    'name' =>
                        trim($data['name']),

                    'description' =>
                        $this->nullableText(
                            $data['description']
                            ?? null
                        ),
                ])->save();

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId:
                        $actor->getKey(),
                    action:
                        'ADMIN_ROLE_UPDATED',
                    module:
                        'ADMINISTRATION',
                    targetType:
                        'ADMIN_ROLE',
                    targetId:
                        $locked->getKey(),
                    oldValues:
                        $old,
                    newValues: [
                        'name' =>
                            $locked->name,

                        'description' =>
                            $locked->description,
                    ],
                    ipAddress:
                        $request->ip(),
                    userAgent:
                        $request->userAgent()
                );
            },
            3
        );
    }

    public function updateRoleStatus(
        AdminUser $actor,
        AdminRole $role,
        array $data,
        Request $request
    ): void {
        DB::transaction(
            function () use (
                $actor,
                $role,
                $data,
                $request
            ): void {
                $locked =
                    AdminRole::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $role->getKey()
                        );

                $this->assertCustomRole(
                    $locked
                );

                $oldActive =
                    (bool) $locked->is_active;

                $newActive =
                    $data['status'] === 'ACTIVE';

                $locked->forceFill([
                    'is_active' =>
                        $newActive,
                ])->save();

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId:
                        $actor->getKey(),
                    action:
                        $newActive
                            ? 'ADMIN_ROLE_ENABLED'
                            : 'ADMIN_ROLE_DISABLED',
                    module:
                        'ADMINISTRATION',
                    targetType:
                        'ADMIN_ROLE',
                    targetId:
                        $locked->getKey(),
                    oldValues: [
                        'is_active' =>
                            $oldActive,
                    ],
                    newValues: [
                        'is_active' =>
                            $newActive,

                        'reason' =>
                            $this->boundedReason(
                                $data['reason']
                            ),
                    ],
                    ipAddress:
                        $request->ip(),
                    userAgent:
                        $request->userAgent()
                );
            },
            3
        );
    }

    public function syncRolePermissions(
        AdminUser $actor,
        AdminRole $role,
        array $permissionIds,
        Request $request
    ): void {
        DB::transaction(
            function () use (
                $actor,
                $role,
                $permissionIds,
                $request
            ): void {
                $locked =
                    AdminRole::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $role->getKey()
                        );

                $this->assertCustomRole(
                    $locked
                );

                $permissions =
                    $this->lockedPermissions(
                        $permissionIds
                    );

                $oldCodes =
                    $locked->permissions()
                        ->orderBy('code')
                        ->pluck('code')
                        ->values()
                        ->all();

                $locked->permissions()->sync(
                    $permissions
                        ->pluck('id')
                        ->all()
                );

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId:
                        $actor->getKey(),
                    action:
                        'ADMIN_ROLE_PERMISSIONS_CHANGED',
                    module:
                        'ADMINISTRATION',
                    targetType:
                        'ADMIN_ROLE',
                    targetId:
                        $locked->getKey(),
                    oldValues: [
                        'permission_codes' =>
                            $oldCodes,
                    ],
                    newValues: [
                        'permission_codes' =>
                            $permissions
                                ->pluck('code')
                                ->sort()
                                ->values()
                                ->all(),
                    ],
                    ipAddress:
                        $request->ip(),
                    userAgent:
                        $request->userAgent()
                );
            },
            3
        );
    }

    private function lockedAssignableRoles(
        array $ids
    ) {
        $uniqueIds = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $roles =
            AdminRole::query()
                ->whereIn(
                    'id',
                    $uniqueIds
                )
                ->where(
                    'is_active',
                    true
                )
                ->where(
                    'code',
                    '!=',
                    'SUPER_ADMIN'
                )
                ->lockForUpdate()
                ->get();

        if (
            $roles->count()
            !== $uniqueIds->count()
        ) {
            abort(
                409,
                'One or more selected admin roles changed availability. Refresh and try again.'
            );
        }

        return $roles;
    }

    private function lockedPermissions(
        array $ids
    ) {
        $uniqueIds = collect($ids)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $permissions =
            AdminPermission::query()
                ->whereIn(
                    'id',
                    $uniqueIds
                )
                ->lockForUpdate()
                ->get();

        if (
            $permissions->count()
            !== $uniqueIds->count()
        ) {
            abort(
                409,
                'One or more selected permissions changed availability. Refresh and try again.'
            );
        }

        return $permissions;
    }

    private function assertAdminMutable(
        AdminUser $admin
    ): void {
        if ($admin->isSuperAdmin()) {
            abort(
                403,
                'The seeded Super Admin account is protected from this workflow.'
            );
        }
    }

    private function assertCustomRole(
        AdminRole $role
    ): void {
        if ($role->is_system) {
            abort(
                403,
                'System admin roles are immutable in the Administration UI.'
            );
        }

        if ($role->code === 'SUPER_ADMIN') {
            abort(
                403,
                'The Super Admin role is protected.'
            );
        }
    }

    private function perPage(
        array $filters
    ): int {
        $requested = (int) (
            $filters['per_page'] ?? 20
        );

        return in_array(
            $requested,
            [10, 20, 50, 100],
            true
        )
            ? $requested
            : 20;
    }

    private function boundedReason(
        string $reason
    ): string {
        return Str::limit(
            trim($reason),
            500,
            ''
        );
    }

    private function nullableText(
        mixed $value
    ): ?string {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim(
            (string) $value
        );

        return $text !== ''
            ? $text
            : null;
    }
}
