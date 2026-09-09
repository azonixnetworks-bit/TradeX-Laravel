<?php

namespace App\Http\Middleware;

use App\Models\AdminUser;
use App\Support\Admin\AdminAccessMap;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::guard('admin')->check()) {
            return redirect()
                ->route('admin.login')
                ->with('error', 'Please sign in to continue.');
        }

        $admin = Auth::guard('admin')->user();

        if (! $admin || ! $admin->canLogin()) {
            Auth::guard('admin')->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->with('error', 'Your admin session is no longer available.');
        }

        /*
         * ADMIN-RBAC-02: /admin is a neutral entry point, not a permission in
         * itself. Route it to the first module this administrator can actually
         * open so dashboard-less custom roles never bounce into a 403 page.
         */
        if (
            $request->is('admin')
            && $admin instanceof AdminUser
        ) {
            return redirect()->route(
                AdminAccessMap::landingRouteName($admin)
            );
        }

        return $next($request);
    }
}
