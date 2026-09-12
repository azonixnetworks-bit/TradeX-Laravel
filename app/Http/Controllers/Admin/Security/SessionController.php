<?php

namespace App\Http\Controllers\Admin\Security;

use App\Http\Controllers\Controller;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Services\Admin\Security\AdminSecurityService;
use App\Services\Admin\Security\AdminSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function index(
        Request $request,
        AdminSecurityService $service,
        AdminSessionService $adminSessions
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

        $data['adminSessions'] = $adminSessions->inventory(
            $request->only([
                'admin_search',
                'admin_status',
                'admin_per_page',
            ])
        );

        $data['currentAdminSessionUuid'] = (string) $request
            ->session()
            ->get(AdminSessionService::SESSION_KEY, '');

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

    public function revokeAdminSession(
        Request $request,
        AdminSession $adminSession,
        AdminSessionService $service
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('security.manage'),
            403
        );

        $revoked = $service->revoke(
            $adminSession,
            $admin,
            $request
        );

        return redirect()
            ->route('admin.security.sessions')
            ->with(
                $revoked ? 'success' : 'error',
                $revoked
                    ? 'The selected admin device session was signed out.'
                    : 'That session is already inactive or is your current session.'
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
