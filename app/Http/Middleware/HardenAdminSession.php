<?php

namespace App\Http\Middleware;

use App\Models\AdminSession;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Services\Admin\Security\AdminSessionService;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HardenAdminSession
{
    public function __construct(
        private readonly AdminSessionService $adminSessions
    ) {
    }

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        if (! $this->isAdminSurface($request)) {
            return $next($request);
        }

        $admin = Auth::guard('admin')->user();

        if (! $admin instanceof AdminUser) {
            return $next($request);
        }

        /*
         * TOTP enrollment-only sessions are intentionally restricted and are
         * not treated as fully authenticated admin device sessions yet.
         */
        if (
            ! $request->session()->boolean(
                'tradex_admin_totp_enrollment_only'
            )
        ) {
            $tracked = $this->adminSessions->ensureTracked(
                $request,
                $admin
            );

            if (! $tracked->isActive()) {
                return $this->expireTrackedSession(
                    $request,
                    $admin,
                    $tracked
                );
            }
        }

        $now = now()->timestamp;
        $session = $request->session();

        $startedAt = (int) $session->get(
            'tradex_admin_session_started_at',
            0
        );

        $lastActivityAt = (int) $session->get(
            'tradex_admin_last_activity_at',
            0
        );

        if ($startedAt <= 0) {
            $startedAt = $now;

            $session->put(
                'tradex_admin_session_started_at',
                $startedAt
            );
        }

        if ($lastActivityAt <= 0) {
            $lastActivityAt = $now;
        }

        $idleMinutes = max(
            1,
            (int) config(
                'tradex_admin_hardening.session.idle_timeout_minutes',
                30
            )
        );

        $absoluteMinutes = max(
            $idleMinutes,
            (int) config(
                'tradex_admin_hardening.session.absolute_timeout_minutes',
                480
            )
        );

        if ($now - $lastActivityAt > $idleMinutes * 60) {
            return $this->expire(
                $request,
                $admin,
                'IDLE_TIMEOUT'
            );
        }

        if ($now - $startedAt > $absoluteMinutes * 60) {
            return $this->expire(
                $request,
                $admin,
                'ABSOLUTE_TIMEOUT'
            );
        }

        $session->put(
            'tradex_admin_last_activity_at',
            $now
        );

        return $next($request);
    }

    private function expireTrackedSession(
        Request $request,
        AdminUser $admin,
        AdminSession $tracked
    ): RedirectResponse {
        $reason = $tracked->revoked_reason
            ?: 'SESSION_REVOKED';

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: 'ADMIN_SESSION_REVOKED_LOGOUT',
            module: 'AUTH',
            newValues: [
                'reason' => $reason,
                'session_uuid' => $tracked->uuid,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $this->adminSessions->markLoggedOut(
            $request,
            $admin
        );

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $message = $reason === 'NEW_SUPER_ADMIN_LOGIN'
            ? 'Your session ended because this Super Admin account was signed in on another device.'
            : 'Your admin session is no longer active. Please sign in again.';

        return redirect()
            ->route('admin.login')
            ->with('error', $message);
    }

    private function expire(
        Request $request,
        AdminUser $admin,
        string $reason
    ): RedirectResponse {
        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: 'ADMIN_SESSION_EXPIRED',
            module: 'AUTH',
            newValues: [
                'reason' => $reason,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        $this->adminSessions->markLoggedOut(
            $request,
            $admin
        );

        Auth::guard('admin')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('admin.login')
            ->with(
                'error',
                'Your admin session expired. Please sign in again.'
            );
    }

    private function isAdminSurface(
        Request $request
    ): bool {
        return $request->is('admin')
            || $request->is('admin/*');
    }
}
