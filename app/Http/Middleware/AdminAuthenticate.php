<?php

namespace App\Http\Middleware;

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

        return $next($request);
    }
}