<?php

namespace App\Http\Controllers\Admin\Security;

use App\Http\Controllers\Controller;
use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Services\Admin\Security\AdminSecurityService;
use App\Services\Admin\Security\AdminSessionRiskService;
use App\Services\Admin\Security\AdminSessionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SessionController extends Controller
{
    public function index(
        Request $request,
        AdminSecurityService $service,
        AdminSessionService $adminSessions,
        AdminSessionRiskService $riskService
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

        $data['adminSessions']->getCollection()->transform(
            function (AdminSession $session) use ($riskService): AdminSession {
                $risk = $riskService->analyze($session);

                $session->setAttribute('risk_level', $risk['level']);
                $session->setAttribute('risk_score', $risk['score']);
                $session->setAttribute('risk_signals', $risk['signals']);

                return $session;
            }
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

    public function showAdminSession(
        Request $request,
        AdminSession $adminSession,
        AdminSessionRiskService $riskService
    ): View {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('security.view'),
            403
        );

        $adminSession->load([
            'admin.roles' => fn ($query) => $query
                ->select('admin_roles.id', 'admin_roles.name', 'admin_roles.code')
                ->orderBy('admin_roles.name'),
        ]);

        $risk = $riskService->analyze($adminSession);

        return view(
            'admin.security.admin-session-show',
            [
                'adminSession' => $adminSession,
                'risk' => $risk,
                'riskService' => $riskService,
                'currentAdminSessionUuid' => (string) $request
                    ->session()
                    ->get(AdminSessionService::SESSION_KEY, ''),
                'canManage' => $admin->hasPermission('security.manage'),
            ]
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

    public function revokeAllAdminSessions(
        Request $request,
        AdminUser $adminUser,
        AdminSessionService $service
    ): RedirectResponse {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('security.manage'),
            403
        );

        $count = $service->revokeAllForAdmin(
            $adminUser,
            $admin,
            $request
        );

        return redirect()
            ->route('admin.security.sessions')
            ->with(
                $count > 0 ? 'success' : 'error',
                $count > 0
                    ? "{$count} admin session(s) were signed out. The current browser session, if applicable, was preserved."
                    : 'No other active sessions were available to sign out.'
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
