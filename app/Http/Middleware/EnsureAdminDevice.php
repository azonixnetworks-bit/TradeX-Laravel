<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use App\Services\Admin\Security\AdminDeviceSecurityService;
use App\Services\Admin\Security\AdminDeviceSessionService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('tradex_admin_devices.enabled') || ! ($request->is('admin') || $request->is('admin/*'))) {
            return $next($request);
        }
        $security = app(AdminDeviceSecurityService::class);
        $sessions = app(AdminDeviceSessionService::class);
        $previousSessionId = $request->session()->getId();
        $deviceToken = $security->token($request);

        if (! $deviceToken) {
            $deviceToken = bin2hex(
                random_bytes(32)
            );
        }

        /*
         * The admin device token is a server-generated, encrypted, HttpOnly
         * binding cookie. SameSite=Lax is required for trusted top-level OAuth
         * redirects (for example Google -> TradeX YouTube callback). Strict
         * prevents the browser from sending this cookie on that callback and
         * incorrectly invalidates an otherwise valid admin device session.
         *
         * Re-issuing the current token also upgrades existing Strict cookies
         * without changing the registered device identity.
         */
        Cookie::queue(
            Cookie::make(
                AdminDeviceSecurityService::COOKIE,
                $deviceToken,
                525600,
                '/admin',
                null,
                $request->isSecure(),
                true,
                false,
                'lax'
            )
        );

        $admin = Auth::guard('admin')->user();
        if ($admin instanceof AdminUser
            && ! $request->session()->get('tradex_admin_totp_enrollment_only')
            && ! $security->validSession($admin, $request)) {
            $sessions->end($previousSessionId);
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with('error', 'Sign in again to verify this device. If it was revoked or blocked, contact Super Admin.');
        }

        $response = $next($request);
        $currentAdmin = Auth::guard('admin')->user();
        if ($currentAdmin instanceof AdminUser
            && ! $request->session()->get('tradex_admin_totp_enrollment_only')
            && $security->validSession($currentAdmin, $request)) {
            if ($previousSessionId !== $request->session()->getId()) {
                $sessions->end($previousSessionId);
            }
            $sessions->touch($currentAdmin, $request);
        } else {
            $sessions->end($previousSessionId);
        }

        return $response;
    }
}
