<?php

namespace App\Http\Controllers\Admin\Security;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Admin\Security\AdminSecurityService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function index(
        Request $request,
        AdminSecurityService $service
    ): View {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('security.view'),
            403
        );

        $data = $service->sessions(
            $request->only([
                'search',
                'status',
                'slot',
                'platform',
                'from',
                'to',
                'per_page',
            ])
        );

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

            'open_user' =>
                $admin->hasPermission(
                    'users.view_details'
                ),
        ];

        return view(
            'admin.security.sessions',
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
