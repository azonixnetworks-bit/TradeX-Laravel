<?php

namespace App\Http\Controllers\Admin\Security;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Services\Admin\Security\AdminSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SecurityEventController extends Controller
{
    public function home(): RedirectResponse
    {
        $admin = $this->admin();

        if ($admin->hasPermission('security.events')) {
            return redirect()->route(
                'admin.security.events'
            );
        }

        if ($admin->hasPermission('security.view')) {
            return redirect()->route(
                'admin.security.sessions'
            );
        }

        abort(403);
    }

    public function index(
        Request $request,
        AdminSecurityService $service
    ): View {
        $admin = $this->admin();

        abort_unless(
            $admin->hasPermission('security.events'),
            403
        );

        $data = $service->events(
            $request->only([
                'search',
                'action',
                'module',
                'actor_type',
                'from',
                'to',
                'per_page',
            ])
        );

        $data['permissions'] =
            $this->permissions(
                $admin
            );

        return view(
            'admin.security.events',
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

    private function permissions(
        AdminUser $admin
    ): array {
        return [
            'view' =>
                $admin->hasPermission(
                    'security.view'
                ),

            'events' =>
                $admin->hasPermission(
                    'security.events'
                ),

            'manage' =>
                $admin->hasPermission(
                    'security.manage'
                ),
        ];
    }
}
