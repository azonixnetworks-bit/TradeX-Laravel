<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ForceAdminTotp
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        if (
            ! $this->isAdminSurface(
                $request
            )
            || ! (bool) config(
                'tradex_admin_hardening.totp.required',
                false
            )
        ) {
            return $next($request);
        }

        $admin =
            Auth::guard('admin')
                ->user();

        /*
         * Public login/TOTP challenge requests have no authenticated guard.
         */
        if (
            ! $admin instanceof AdminUser
        ) {
            return $next($request);
        }

        $isEnrollmentSurface =
            $request->is(
                'admin/account/totp'
            )
            || $request->is(
                'admin/account/totp/*'
            );

        $isLogout =
            $request->is(
                'admin/logout'
            );

        /*
         * Password-verified but not-yet-MFA-authenticated sessions are
         * deliberately confined to enrollment + logout.
         */
        if (
            (bool) $request
                ->session()
                ->get(
                    'tradex_admin_totp_enrollment_only',
                    false
                )
        ) {
            if (
                $isEnrollmentSurface
                || $isLogout
            ) {
                return $next($request);
            }

            return redirect()
                ->route(
                    'admin.account.totp.settings'
                )
                ->with(
                    'error',
                    'Complete admin two-factor authentication enrollment before continuing.'
                );
        }

        /*
         * Self-enrollment and logout must remain reachable for old sessions
         * during rollout and break-glass recovery.
         */
        if (
            $isEnrollmentSurface
            || $isLogout
        ) {
            return $next($request);
        }

        if (
            ! $admin
                ->hasConfirmedTotp()
        ) {
            return redirect()
                ->route(
                    'admin.account.totp.settings'
                )
                ->with(
                    'error',
                    'Admin TOTP enrollment is required before continuing.'
                );
        }

        if (
            ! $request
                ->session()
                ->has(
                    'tradex_admin_totp_authenticated_at'
                )
        ) {
            Auth::guard('admin')->logout();

            $request
                ->session()
                ->invalidate();

            $request
                ->session()
                ->regenerateToken();

            return redirect()
                ->route(
                    'admin.login'
                )
                ->with(
                    'error',
                    'Please sign in again to complete two-factor authentication.'
                );
        }

        return $next($request);
    }

    private function isAdminSurface(
        Request $request
    ): bool {
        return $request->is('admin')
            || $request->is('admin/*');
    }
}
