<?php

namespace App\Http\Controllers\Web\CustomerOnboarding;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class CustomerActivationPageController extends Controller
{
    public function __invoke(): Response
    {
        return response()
            ->view(
                'customer.onboarding.activate'
            )
            ->withHeaders([
                'Cache-Control' =>
                    'private, no-store, no-cache, max-age=0, must-revalidate',
                'Pragma' => 'no-cache',
                'Referrer-Policy' => 'no-referrer',
                'X-Content-Type-Options' => 'nosniff',
                'X-Frame-Options' => 'DENY',
                'X-Robots-Tag' =>
                    'noindex, nofollow, noarchive',
                'Permissions-Policy' =>
                    'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
                'Content-Security-Policy' =>
                    "default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline'; connect-src 'self'; frame-ancestors 'none'; base-uri 'none'; form-action 'self'",
            ]);
    }
}
