<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ResetPushApiAuthState
{
    public function handle(Request $request, Closure $next): Response
    {
        /*
         * Force bearer-token authentication to be resolved from this request.
         * This is also safe for long-lived application workers where guard
         * instances may otherwise survive longer than a single HTTP request.
         */
        Auth::forgetGuards();

        return $next($request);
    }
}
