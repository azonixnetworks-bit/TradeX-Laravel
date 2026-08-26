<?php

namespace App\Http\Controllers\Admin\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Administration\StoreAdminUserRequest;
use App\Http\Requests\Admin\Administration\SyncAdminUserRolesRequest;
use App\Http\Requests\Admin\Administration\UpdateAdminUserRequest;
use App\Http\Requests\Admin\Administration\UpdateAdminUserStatusRequest;
use App\Models\AdminUser;
use App\Services\Admin\Administration\AdminAdministrationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminUserController extends Controller
{
    public function index(
        Request $request,
        AdminAdministrationService $service
    ): View {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('admins.view'),
            403
        );

        return view(
            'admin.admins.index',
            $service->adminIndex(
                $request->only([
                    'search',
                    'status',
                    'role',
                    'per_page',
                ])
            )
        );
    }

    public function create(
        AdminAdministrationService $service
    ): View {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('admins.create')
            && $admin->hasPermission('roles.manage'),
            403
        );

        return view(
            'admin.admins.create',
            [
                'roles' =>
                    $service->assignableRoles(),
            ]
        );
    }

    public function store(
        StoreAdminUserRequest $request,
        AdminAdministrationService $service
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('admins.create')
            && $admin->hasPermission('roles.manage'),
            403
        );

        $created = $service->createAdmin(
            $admin,
            $request->validated(),
            $request
        );

        return redirect()
            ->route(
                'admin.administration.admins.edit',
                $created
            )
            ->with(
                'success',
                'Admin account created securely.'
            );
    }

    public function edit(
        AdminUser $adminUser,
        AdminAdministrationService $service
    ): View {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('admins.view'),
            403
        );

        return view(
            'admin.admins.edit',
            $service->adminEditData(
                $admin,
                $adminUser
            )
        );
    }

    public function update(
        UpdateAdminUserRequest $request,
        AdminUser $adminUser,
        AdminAdministrationService $service
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('admins.update'),
            403
        );

        $service->updateAdmin(
            $admin,
            $adminUser,
            $request->validated(),
            $request
        );

        return back()->with(
            'success',
            'Admin profile updated.'
        );
    }

    public function updateStatus(
        UpdateAdminUserStatusRequest $request,
        AdminUser $adminUser,
        AdminAdministrationService $service
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('admins.disable'),
            403
        );

        $service->updateAdminStatus(
            $admin,
            $adminUser,
            $request->validated(),
            $request
        );

        return back()->with(
            'success',
            'Admin account status updated.'
        );
    }

    public function syncRoles(
        SyncAdminUserRolesRequest $request,
        AdminUser $adminUser,
        AdminAdministrationService $service
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('admins.update')
            && $admin->hasPermission('roles.manage'),
            403
        );

        $service->syncAdminRoles(
            $admin,
            $adminUser,
            $request->validated('role_ids'),
            $request
        );

        return back()->with(
            'success',
            'Admin role assignments updated.'
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
