<?php

namespace App\Http\Controllers\Admin\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Administration\StoreAdminRoleRequest;
use App\Http\Requests\Admin\Administration\SyncAdminRolePermissionsRequest;
use App\Http\Requests\Admin\Administration\UpdateAdminRoleRequest;
use App\Http\Requests\Admin\Administration\UpdateAdminRoleStatusRequest;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Services\Admin\Administration\AdminAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class RoleController extends Controller
{
    public function index(
        AdminAdministrationService $service
    ): View {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('roles.view'),
            403
        );

        return view(
            'admin.roles.index',
            $service->roleIndex()
        );
    }

    public function create(
        AdminAdministrationService $service
    ): View {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('roles.manage'),
            403
        );

        return view(
            'admin.roles.create',
            [
                'permissions' =>
                    $service->permissionGroups(),
            ]
        );
    }

    public function store(
        StoreAdminRoleRequest $request,
        AdminAdministrationService $service
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('roles.manage'),
            403
        );

        $role = $service->createRole(
            $admin,
            $request->validated(),
            $request
        );

        return redirect()
            ->route(
                'admin.administration.roles.permissions',
                $role
            )
            ->with(
                'success',
                'Custom admin role created.'
            );
    }

    public function permissions(
        AdminRole $adminRole,
        AdminAdministrationService $service
    ): View {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('roles.view'),
            403
        );

        return view(
            'admin.roles.permissions',
            $service->rolePermissionsData(
                $admin,
                $adminRole
            )
        );
    }

    public function update(
        UpdateAdminRoleRequest $request,
        AdminRole $adminRole,
        AdminAdministrationService $service
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('roles.manage'),
            403
        );

        $service->updateRole(
            $admin,
            $adminRole,
            $request->validated(),
            $request
        );

        return back()->with(
            'success',
            'Custom role updated.'
        );
    }

    public function updateStatus(
        UpdateAdminRoleStatusRequest $request,
        AdminRole $adminRole,
        AdminAdministrationService $service
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('roles.manage'),
            403
        );

        $service->updateRoleStatus(
            $admin,
            $adminRole,
            $request->validated(),
            $request
        );

        return back()->with(
            'success',
            'Custom role status updated.'
        );
    }

    public function syncPermissions(
        SyncAdminRolePermissionsRequest $request,
        AdminRole $adminRole,
        AdminAdministrationService $service
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('roles.manage'),
            403
        );

        $service->syncRolePermissions(
            $admin,
            $adminRole,
            $request->validated('permission_ids'),
            $request
        );

        return back()->with(
            'success',
            'Role permissions updated.'
        );
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        return $admin;
    }
}
