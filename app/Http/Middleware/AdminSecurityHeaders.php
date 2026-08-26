<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminSecurityHeaders
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        if (! $this->isAdminSurface($request)) {
            return $next($request);
        }

        $allowedHosts =
            (array) config(
                'tradex_admin_hardening.transport.allowed_hosts',
                []
            );

        if (
            $allowedHosts !== []
            && ! in_array(
                strtolower(
                    $request->getHost()
                ),
                $allowedHosts,
                true
            )
        ) {
            return $this->withHeaders(
                response(
                    'Invalid admin host.',
                    400
                ),
                $request
            );
        }

        if (
            (bool) config(
                'tradex_admin_hardening.transport.require_https',
                false
            )
            && ! $request->secure()
        ) {
            return $this->withHeaders(
                response(
                    'Admin HTTPS is required.',
                    403
                ),
                $request
            );
        }

        $response =
            $next($request);

        return $this->withHeaders(
            $response,
            $request
        );
    }

    private function withHeaders(
        Response $response,
        Request $request
    ): Response {
        $response->headers->set(
            'Cache-Control',
            'private, no-store, no-cache, max-age=0, must-revalidate'
        );

        $response->headers->set(
            'Pragma',
            'no-cache'
        );

        $response->headers->set(
            'X-Content-Type-Options',
            'nosniff'
        );

        $response->headers->set(
            'Referrer-Policy',
            'no-referrer'
        );

        $response->headers->set(
            'X-Frame-Options',
            'DENY'
        );

        $response->headers->set(
            'X-Robots-Tag',
            'noindex, nofollow, noarchive'
        );

        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()'
        );

        $response->headers->set(
            'Cross-Origin-Opener-Policy',
            'same-origin'
        );

        $response->headers->set(
            'Cross-Origin-Resource-Policy',
            'same-origin'
        );

        if (
            (bool) config(
                'tradex_admin_hardening.headers.csp_enabled',
                true
            )
        ) {
            $response->headers->set(
                'Content-Security-Policy',
                (string) config(
                    'tradex_admin_hardening.headers.content_security_policy'
                )
            );
        }

        if (
            $request->secure()
            && (bool) config(
                'tradex_admin_hardening.transport.hsts_enabled',
                false
            )
        ) {
            $maxAge =
                max(
                    300,
                    (int) config(
                        'tradex_admin_hardening.transport.hsts_max_age_seconds',
                        31536000
                    )
                );

            $value =
                'max-age='
                .$maxAge;

            if (
                (bool) config(
                    'tradex_admin_hardening.transport.hsts_include_subdomains',
                    true
                )
            ) {
                $value .=
                    '; includeSubDomains';
            }

            $response->headers->set(
                'Strict-Transport-Security',
                $value
            );
        }

        return $response;
    }

    private function isAdminSurface(
        Request $request
    ): bool {
        return $request->is('admin')
            || $request->is('admin/*');
    }
}
