<?php

namespace App\Http\Middleware;

use App\Exceptions\AuthApiException;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEducationPremium
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        if (
            ! $user instanceof User
            || ! $user->canAccessPremium()
        ) {
            throw new AuthApiException(
                403,
                'EDUCATION_PREMIUM_REQUIRED',
                'TradeX Education Premium is required before Copy Trading broker onboarding.'
            );
        }

        return $next($request);
    }
}
