<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SearchEngineIndexPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! app()->environment('production') || $this->isPrivateSurface($request)) {
            $response->headers->set(
                'X-Robots-Tag',
                'noindex, nofollow, noarchive'
            );
        }

        return $response;
    }

    private function isPrivateSurface(Request $request): bool
    {
        return $request->is(
            'admin',
            'admin/*',
            'customer',
            'customer/*',
            'onboarding',
            'onboarding/*',
            'payments',
            'payments/*',
            'api',
            'api/*',
            'up',
        );
    }
}
