<?php

namespace App\Support\Admin;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class AdminWebExceptionResponder
{
    /**
     * These statuses are normal recoverable admin-form outcomes. When a
     * browser supplied a previous-page referrer, keep the admin on that page
     * and show a controlled notification instead of an exception document.
     */
    private const REDIRECTABLE_STATUSES = [
        409,
        419,
        422,
        429,
    ];

    /**
     * Never flash authentication/security material back into the session as
     * old input when an admin request fails.
     */
    private const SENSITIVE_KEY_FRAGMENTS = [
        'password',
        'passcode',
        'secret',
        'token',
        'otp',
        'totp',
        'recovery',
        'api_key',
        'apikey',
        'private_key',
        'credential',
        'authorization',
        'cookie',
        'signature',
    ];

    public function render(
        Throwable $exception,
        Request $request
    ): Response|RedirectResponse|null {
        if (! $this->shouldHandle($request, $exception)) {
            return null;
        }

        $status = $this->statusCode($exception);
        $reference = $status >= 500
            ? $this->incidentReference()
            : null;

        if ($status >= 500) {
            $this->logUnexpectedFailure(
                $exception,
                $request,
                $status,
                $reference
            );
        }

        $notice = $this->notice(
            $exception,
            $status,
            $reference
        );

        if ($this->shouldRedirectBack($request, $status)) {
            $response = redirect()
                ->back()
                ->with(
                    'admin_notice',
                    $notice
                );

            $safeInput = $this->safeInput(
                $request->all()
            );

            if ($safeInput !== []) {
                $response->withInput(
                    $safeInput
                );
            }

            return $response;
        }

        return response()->view(
            'admin.errors.friendly',
            [
                'status' => $status,
                'type' => $notice['type'],
                'title' => $notice['title'],
                'message' => $notice['message'],
                'reference' => $reference,
            ],
            $status
        );
    }

    private function shouldHandle(
        Request $request,
        Throwable $exception
    ): bool {
        if (
            $request->expectsJson()
            || ! (
                $request->is('admin')
                || $request->is('admin/*')
            )
        ) {
            return false;
        }

        /*
         * Laravel already provides correct redirect + field-error semantics
         * for FormRequest validation. Do not replace that contract.
         */
        if (
            $exception instanceof ValidationException
            || $exception instanceof HttpResponseException
        ) {
            return false;
        }

        return true;
    }

    private function statusCode(Throwable $exception): int
    {
        if ($exception instanceof TokenMismatchException) {
            return 419;
        }

        if ($exception instanceof AuthorizationException) {
            return 403;
        }

        if ($exception instanceof AuthenticationException) {
            return 401;
        }

        if ($exception instanceof ModelNotFoundException) {
            return 404;
        }

        if ($exception instanceof HttpExceptionInterface) {
            return max(
                400,
                min(
                    599,
                    $exception->getStatusCode()
                )
            );
        }

        return 500;
    }

    private function shouldRedirectBack(
        Request $request,
        int $status
    ): bool {
        $isMutation = ! in_array(
            strtoupper($request->method()),
            ['GET', 'HEAD', 'OPTIONS'],
            true
        );

        if (! $isMutation) {
            return false;
        }

        /*
         * Login/TOTP pages are standalone security views and intentionally do
         * not consume the main admin toast component. Keep failures there on
         * the branded status page instead of creating an invisible flash.
         */
        if (
            $request->is('admin/login')
            || $request->is('admin/totp/*')
        ) {
            return false;
        }

        /*
         * Unexpected server failures are recoverable from an admin form too;
         * the transaction has already unwound before the renderer is called.
         */
        $redirectable = $status >= 500
            || in_array(
                $status,
                self::REDIRECTABLE_STATUSES,
                true
            );

        if (! $redirectable) {
            return false;
        }

        /*
         * A real browser form sends a same-origin Referer. Keeping this guard
         * avoids inventing a redirect target for scripts/tests/API-like calls;
         * those receive the branded status page with the original HTTP code.
         */
        return trim(
            (string) $request->headers->get('referer', '')
        ) !== '';
    }

    private function notice(
        Throwable $exception,
        int $status,
        ?string $reference
    ): array {
        $type = match (true) {
            $status === 409,
            $status === 419,
            $status === 429 => 'warning',
            $status >= 500 => 'error',
            $status >= 400 => 'error',
            default => 'info',
        };

        $title = match ($status) {
            401 => 'Admin session required',
            403 => 'Access denied',
            404 => 'Not found',
            409 => 'Conflict detected',
            419 => 'Session expired',
            422 => 'Request needs attention',
            429 => 'Too many requests',
            500 => 'Request could not be completed',
            default => $status >= 500
                ? 'Request could not be completed'
                : 'Request could not be processed',
        };

        return [
            'type' => $type,
            'title' => $title,
            'message' => $this->message(
                $exception,
                $status,
                $reference
            ),
            'status' => $status,
            'reference' => $reference,
        ];
    }

    private function message(
        Throwable $exception,
        int $status,
        ?string $reference
    ): string {
        if (in_array($status, [409, 422], true)) {
            $businessMessage = trim(
                strip_tags(
                    $exception->getMessage()
                )
            );

            if ($businessMessage !== '') {
                return Str::limit(
                    $businessMessage,
                    600,
                    '…'
                );
            }
        }

        return match ($status) {
            400 => 'The request could not be processed. Check the information and try again.',
            401 => 'Your admin session is no longer valid. Sign in again and retry the action.',
            403 => 'You do not have permission to perform this admin action.',
            404 => 'The requested admin page or record could not be found.',
            419 => 'Your secure admin session expired. Refresh the page and submit the action again.',
            429 => 'Too many requests were received. Wait a moment and try again.',
            default => $status >= 500
                ? 'TradeX could not complete this request. Nothing should be assumed saved. Try again; if the issue repeats, provide support reference '.($reference ?? 'unavailable').'.'
                : 'TradeX could not process this admin request. Review the action and try again.',
        };
    }

    private function safeInput(array $input): array
    {
        $safe = [];

        foreach ($input as $key => $value) {
            $normalizedKey = strtolower(
                (string) $key
            );

            if (
                $normalizedKey === '_token'
                || $this->isSensitiveKey(
                    $normalizedKey
                )
            ) {
                continue;
            }

            if (is_array($value)) {
                $safe[$key] = $this->safeInput(
                    $value
                );
                continue;
            }

            if (
                is_scalar($value)
                || $value === null
            ) {
                $safe[$key] = $value;
            }
        }

        return $safe;
    }

    private function isSensitiveKey(string $key): bool
    {
        foreach (
            self::SENSITIVE_KEY_FRAGMENTS
            as $fragment
        ) {
            if (str_contains($key, $fragment)) {
                return true;
            }
        }

        return false;
    }

    private function incidentReference(): string
    {
        return 'TX-ADM-'.Str::upper(
            Str::random(10)
        );
    }

    private function logUnexpectedFailure(
        Throwable $exception,
        Request $request,
        int $status,
        ?string $reference
    ): void {
        $adminId = null;

        try {
            $adminId = auth('admin')->id();
        } catch (Throwable) {
            /* Do not let incident-context lookup break the error boundary. */
        }

        $routeName = null;

        try {
            $routeName = $request->route()?->getName();
        } catch (Throwable) {
            /* Route resolution can itself be part of the failing request. */
        }

        Log::error(
            'TradeX admin web request failed.',
            [
                'reference' => $reference,
                'status' => $status,
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => $routeName,
                'admin_id' => $adminId,
                'exception' => $exception::class,
                'exception_message' => Str::limit(
                    $exception->getMessage(),
                    1000,
                    '…'
                ),
            ]
        );
    }
}
