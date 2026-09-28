<?php

namespace App\Http\Middleware;

use App\Models\StaffMember;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class StaffAuthenticate
{
    public function handle(Request $request, Closure $next): Response
    {
        $staff = Auth::guard('staff')->user();

        if (! $staff instanceof StaffMember || ! $staff->canLogin()) {
            Auth::guard('staff')->logout();

            return redirect()->route('staff.login')->with('error', 'Please sign in and accept the current employment agreement.');
        }

        return $next($request);
    }
}
