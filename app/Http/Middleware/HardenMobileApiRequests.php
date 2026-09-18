<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class HardenMobileApiRequests
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        /*
         * API workers may be long lived (Octane / test application reuse).
         * Resolve the bearer principal from the current request every time.
         */
        Auth::forgetGuards();

        $requestId = (string) Str::uuid();

        $request->attributes->set(
            'tradex_request_id',
            $requestId
        );

        $transportFailure = $this->transportFailure($request);

        if ($transportFailure instanceof Response) {
            return $this->withHeaders(
                $transportFailure,
                $request,
                $requestId
            );
        }

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            /*
             * Keep Laravel/custom exception contracts intact while ensuring
             * security headers are also present on 401/403/422/429/5xx paths.
             */
            $handler = app(ExceptionHandler::class);
            $handler->report($exception);
            $response = $handler->render($request, $exception);
        }

        return $this->withHeaders(
            $response,
            $request,
            $requestId
        );
    }

    private function transportFailure(
        Request $request
    ): ?Response {
        $allowedHosts = (array) config(
            'tradex_mobile_api_hardening.transport.allowed_hosts',
            []
        );

        if (
            $allowedHosts !== []
            && ! in_array(
                strtolower($request->getHost()),
                $allowedHosts,
                true
            )
        ) {
            return $this->deny(
                400,
                'INVALID_API_HOST',
                'The requested API host is not allowed.'
            );
        }

        if (
            (bool) config(
                'tradex_mobile_api_hardening.transport.require_https',
                false
            )
            && ! $request->secure()
        ) {
            return $this->deny(
                403,
                'API_HTTPS_REQUIRED',
                'HTTPS is required for this API.'
            );
        }

        return null;
    }

    private function deny(
        int $status,
        string $code,
        string $message
    ): JsonResponse {
        return response()->json(
            [
                'success' => false,
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
            ],
            $status
        );
    }

    private function withHeaders(
        Response $response,
        Request $request,
        string $requestId
    ): Response {
        $requestIdHeader = (string) config(
            'tradex_mobile_api_hardening.headers.request_id',
            'X-Request-Id'
        );

        $response->headers->set(
            $requestIdHeader,
            $requestId
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
            'X-Permitted-Cross-Domain-Policies',
            'none'
        );

        $deviceRequest = filled($request->header('X-TradeX-Device-Code'))
            || filled($request->header('X-TradeX-Device-Token'));

        if (
            (bool) config(
                'tradex_mobile_api_hardening.headers.authenticated_no_store',
                true
            )
            && (filled($request->bearerToken()) || $deviceRequest)
        ) {
            $response->headers->set(
                'Cache-Control',
                'private, no-store, no-cache, max-age=0, must-revalidate'
            );
            $response->headers->set(
                'Pragma',
                'no-cache'
            );
        }

        if (
            $request->secure()
            && (bool) config(
                'tradex_mobile_api_hardening.transport.hsts_enabled',
                false
            )
        ) {
            $maxAge = max(
                300,
                (int) config(
                    'tradex_mobile_api_hardening.transport.hsts_max_age_seconds',
                    31536000
                )
            );

            $value = 'max-age='.$maxAge;

            if (
                (bool) config(
                    'tradex_mobile_api_hardening.transport.hsts_include_subdomains',
                    true
                )
            ) {
                $value .= '; includeSubDomains';
            }

            $response->headers->set(
                'Strict-Transport-Security',
                $value
            );
        }

        return $response;
    }
}
