<?php

namespace App\Http\Controllers\Admin\Security;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Admin\Security\AdminSecurityService;
use Illuminate\View\View;

class ContentProtectionController extends Controller
{
    public function index(
        AdminSecurityService $service
    ): View {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('security.view'),
            403
        );

        $data = $service
            ->contentProtection();

        $data['permissions'] = [
            'view' => true,

            'events' =>
                $admin->hasPermission(
                    'security.events'
                ),

            'manage' =>
                $admin->hasPermission(
                    'security.manage'
                ),
        ];

        return view(
            'admin.security.content-protection',
            $data
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
