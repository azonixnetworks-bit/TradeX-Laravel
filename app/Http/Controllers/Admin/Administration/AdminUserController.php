<?php

namespace App\Http\Controllers\Admin\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Administration\StoreAdminUserRequest;
use App\Http\Requests\Admin\Administration\SyncAdminUserRolesRequest;
use App\Http\Requests\Admin\Administration\UpdateAdminUserRequest;
use App\Http\Requests\Admin\Administration\UpdateAdminUserStatusRequest;
use App\Models\AdminUser;
use App\Services\Admin\Administration\AdminAccountDeletionService;
use App\Services\Admin\Administration\AdminAdministrationService;
use App\Services\Admin\Administration\AdminEmailDeliveryService;
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

        $data = $service->adminIndex(
            $request->only([
                'search',
                'status',
                'role',
                'per_page',
            ])
        );

        $data['admins']
            ->getCollection()
            ->load('latestEmailDelivery');

        return view('admin.admins.index', $data);
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
                'roles' => $service->assignableRoles(),
            ]
        );
    }

    public function store(
        StoreAdminUserRequest $request,
        AdminAdministrationService $service,
        AdminEmailDeliveryService $emailService
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

        $created->load('roles');

        $delivery = $emailService->send(
            $created,
            'ADMIN_INVITATION',
            [
                'roles' => $created->roles->pluck('code')->all(),
            ]
        );

        return redirect()
            ->route(
                'admin.administration.admins.edit',
                $created
            )
            ->with(
                'success',
                'Admin account created securely. Email dispatch: '
                    .$delivery->dispatch_status.'.'
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

        $data = $service->adminEditData(
            $admin,
            $adminUser
        );

        $data['emailDeliveries'] = $adminUser
            ->emailDeliveries()
            ->latest('id')
            ->limit(20)
            ->get();

        $data['canDelete'] =
            $admin->isSuperAdmin()
            && ! $adminUser->isSuperAdmin()
            && $admin->getKey() !== $adminUser->getKey();

        return view('admin.admins.edit', $data);
    }

    public function update(
        UpdateAdminUserRequest $request,
        AdminUser $adminUser,
        AdminAdministrationService $service,
        AdminEmailDeliveryService $emailService
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

        $target = $adminUser->fresh();

        if ($target instanceof AdminUser) {
            $emailService->send(
                $target,
                'PROFILE_UPDATED'
            );
        }

        return back()->with(
            'success',
            'Admin profile updated.'
        );
    }

    public function updateStatus(
        UpdateAdminUserStatusRequest $request,
        AdminUser $adminUser,
        AdminAdministrationService $service,
        AdminAccountDeletionService $deletionService,
        AdminEmailDeliveryService $emailService
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('admins.disable'),
            403
        );

        $validated = $request->validated();

        if ($validated['status'] === 'DELETE') {
            abort_unless(
                $admin->isSuperAdmin(),
                403,
                'Only the protected owner Super Admin can delete delegated admin accounts.'
            );

            $deleted = $deletionService->delete(
                $admin,
                $adminUser,
                (string) $validated['reason'],
                $request
            );

            $delivery = $emailService->send(
                $deleted,
                'ACCOUNT_DELETED',
                [
                    'reason' => $validated['reason'],
                ]
            );

            return redirect()
                ->route('admin.administration.admins.index')
                ->with(
                    'success',
                    'Admin account deleted. Email dispatch: '
                        .$delivery->dispatch_status.'.'
                );
        }

        $service->updateAdminStatus(
            $admin,
            $adminUser,
            $validated,
            $request
        );

        $target = $adminUser->fresh();

        if ($target instanceof AdminUser) {
            $emailService->send(
                $target,
                $validated['status'] === 'ACTIVE'
                    ? 'ACCOUNT_ENABLED'
                    : 'ACCOUNT_DISABLED',
                [
                    'reason' => $validated['reason'] ?? null,
                ]
            );
        }

        return back()->with(
            'success',
            'Admin account status updated.'
        );
    }

    public function syncRoles(
        SyncAdminUserRolesRequest $request,
        AdminUser $adminUser,
        AdminAdministrationService $service,
        AdminEmailDeliveryService $emailService
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

        $target = $adminUser->fresh('roles');

        if ($target instanceof AdminUser) {
            $emailService->send(
                $target,
                'ROLES_UPDATED',
                [
                    'roles' => $target->roles->pluck('code')->all(),
                ]
            );
        }

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
