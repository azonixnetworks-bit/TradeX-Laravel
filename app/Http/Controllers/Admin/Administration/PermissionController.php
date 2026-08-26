<?php

namespace App\Http\Controllers\Admin\Administration;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Admin\Administration\AdminAdministrationService;
use Illuminate\View\View;

class PermissionController extends Controller
{
    public function index(
        AdminAdministrationService $service
    ): View {
        $admin = auth('admin')->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        abort_unless(
            $admin->hasPermission('roles.view'),
            403
        );

        return view(
            'admin.roles.catalog',
            [
                'permissionGroups' =>
                    $service->permissionGroups(),
            ]
        );
    }
}
