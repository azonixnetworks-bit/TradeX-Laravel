<?php

namespace App\Http\Controllers\Admin\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\UserDeviceResetRequest;
use App\Http\Requests\Admin\Users\UserDeviceRevokeRequest;
use App\Http\Requests\Admin\Users\UserSessionRevokeRequest;
use App\Models\AdminUser;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Security\UserDeviceSecurityService;
use Illuminate\Http\RedirectResponse;

class UserDeviceController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Revoke Single Device
    |--------------------------------------------------------------------------
    */

    public function revoke(
        UserDeviceRevokeRequest $request,
        User $user,
        UserDevice $device,
        UserDeviceSecurityService $service
    ): RedirectResponse {

        /*
        |--------------------------------------------------------------------------
        | ADMIN-02E-4C — Authenticated Admin Boundary
        |--------------------------------------------------------------------------
        */

        $admin =
            $this->authenticatedAdmin();


        /*
        |--------------------------------------------------------------------------
        | ADMIN-02E-4C — Permission Boundary
        |--------------------------------------------------------------------------
        */

        $this->requirePermission(
            $admin,
            'users.devices.revoke'
        );


        /*
        |--------------------------------------------------------------------------
        | Defence-In-Depth Ownership Check
        |--------------------------------------------------------------------------
        |
        | ADMIN-02E-4A already applies scopeBindings() to the nested
        | {user}/{device} route.
        |
        | Keep this explicit ownership check as a second security boundary.
        |
        */

        abort_unless(
            (int) $device->user_id === (int) $user->getKey(),
            404
        );


        /*
        |--------------------------------------------------------------------------
        | Already Revoked Failure Path
        |--------------------------------------------------------------------------
        */

        if ($device->isRevoked()) {

            return redirect()
                ->route(
                    'admin.users.show',
                    $user
                )
                ->with(
                    'error',
                    'This device is already revoked.'
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Validated Mutation Input
        |--------------------------------------------------------------------------
        */

        $validated =
            $request->validated();


        /*
        |--------------------------------------------------------------------------
        | Revoke Device
        |--------------------------------------------------------------------------
        */

        $service->revokeDevice(
            device:
                $device,

            reason:
                trim(
                    $validated['reason']
                ),

            admin:
                $admin,

            ipAddress:
                $request->ip(),

            userAgent:
                $request->userAgent()
        );


        return redirect()
            ->route(
                'admin.users.show',
                $user
            )
            ->with(
                'success',
                'Device revoked successfully. Active refresh sessions for this device were also revoked.'
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Reset All User Devices
    |--------------------------------------------------------------------------
    */

    public function reset(
        UserDeviceResetRequest $request,
        User $user,
        UserDeviceSecurityService $service
    ): RedirectResponse {

        /*
        |--------------------------------------------------------------------------
        | ADMIN-02E-4C — Authenticated Admin Boundary
        |--------------------------------------------------------------------------
        */

        $admin =
            $this->authenticatedAdmin();


        /*
        |--------------------------------------------------------------------------
        | ADMIN-02E-4C — Permission Boundary
        |--------------------------------------------------------------------------
        */

        $this->requirePermission(
            $admin,
            'users.devices.reset'
        );


        /*
        |--------------------------------------------------------------------------
        | Validated Mutation Input
        |--------------------------------------------------------------------------
        */

        $validated =
            $request->validated();


        /*
        |--------------------------------------------------------------------------
        | Reset Device Slots
        |--------------------------------------------------------------------------
        */

        $count =
            $service->resetUserDevices(
                user:
                    $user,

                admin:
                    $admin,

                reason:
                    trim(
                        $validated['reason']
                    ),

                ipAddress:
                    $request->ip(),

                userAgent:
                    $request->userAgent()
            );


        return redirect()
            ->route(
                'admin.users.show',
                $user
            )
            ->with(
                'success',
                "{$count} active device(s) revoked and device slots reset successfully."
            );
    }


    /*
    |--------------------------------------------------------------------------
    | Revoke All Refresh Sessions
    |--------------------------------------------------------------------------
    |
    | Trusted devices remain registered.
    | User must authenticate again.
    |
    | ADMIN-02E-4B:
    | Revocation + audit are handled by the security service inside
    | one database transaction.
    |
    */

    public function revokeSessions(
        UserSessionRevokeRequest $request,
        User $user,
        UserDeviceSecurityService $service
    ): RedirectResponse {

        /*
        |--------------------------------------------------------------------------
        | ADMIN-02E-4C — Authenticated Admin Boundary
        |--------------------------------------------------------------------------
        */

        $admin =
            $this->authenticatedAdmin();


        /*
        |--------------------------------------------------------------------------
        | ADMIN-02E-4C — Permission Boundary
        |--------------------------------------------------------------------------
        */

        $this->requirePermission(
            $admin,
            'users.sessions.revoke'
        );


        /*
        |--------------------------------------------------------------------------
        | Validated Mutation Input
        |--------------------------------------------------------------------------
        */

        $validated =
            $request->validated();

        $reason =
            trim(
                $validated['reason']
            );


        /*
        |--------------------------------------------------------------------------
        | Revoke Refresh Sessions
        |--------------------------------------------------------------------------
        */

        $count =
            $service
                ->revokeAllUserRefreshTokens(
                    user:
                        $user,

                    reason:
                        $reason,

                    admin:
                        $admin,

                    ipAddress:
                        $request->ip(),

                    userAgent:
                        $request->userAgent()
                );


        return redirect()
            ->route(
                'admin.users.show',
                $user
            )
            ->with(
                'success',
                "{$count} active refresh session(s) revoked successfully."
            );
    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN-02E-4C — Authenticated Admin Resolver
    |--------------------------------------------------------------------------
    |
    | routes/admin.php is already protected by the separate admin.auth
    | middleware.
    |
    | This explicit controller boundary prevents privileged security
    | services from receiving an invalid or missing admin actor.
    |
    */

    private function authenticatedAdmin(): AdminUser
    {
        $admin =
            auth('admin')->user();


        abort_unless(
            $admin instanceof AdminUser,
            401
        );


        return $admin;
    }


    /*
    |--------------------------------------------------------------------------
    | ADMIN-02E-4C — Permission Boundary
    |--------------------------------------------------------------------------
    */

    private function requirePermission(
        AdminUser $admin,
        string $permission
    ): void {

        abort_unless(
            $admin->hasPermission(
                $permission
            ),
            403
        );
    }
}
