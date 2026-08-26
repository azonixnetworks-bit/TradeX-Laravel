<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\AdminTotpChallengeRequest;
use App\Http\Requests\Admin\Auth\AdminTotpConfirmRequest;
use App\Http\Requests\Admin\Auth\AdminTotpSetupRequest;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Services\Admin\Security\AdminTotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TotpController extends Controller
{
    public function challengePage(
        Request $request
    ): View|RedirectResponse {
        if (
            Auth::guard('admin')->check()
        ) {
            return redirect()->route(
                'admin.dashboard'
            );
        }

        $admin =
            $this->pendingAdmin(
                $request
            );

        if (! $admin) {
            return redirect()
                ->route('admin.login')
                ->with(
                    'error',
                    'The admin sign-in challenge expired. Please sign in again.'
                );
        }

        return view(
            'admin.auth.totp_challenge',
            [
                'challengeMinutes' =>
                    max(
                        1,
                        (int) config(
                            'tradex_admin_hardening.totp.login_challenge_minutes',
                            5
                        )
                    ),
            ]
        );
    }

    public function challenge(
        AdminTotpChallengeRequest $request,
        AdminTotpService $totp
    ): RedirectResponse {
        $admin =
            $this->pendingAdmin(
                $request
            );

        if (! $admin) {
            return redirect()
                ->route('admin.login')
                ->with(
                    'error',
                    'The admin sign-in challenge expired. Please sign in again.'
                );
        }

        if (! $admin->canLogin()) {
            $this->clearPending(
                $request
            );

            return redirect()
                ->route('admin.login')
                ->with(
                    'error',
                    'This admin account is currently unavailable.'
                );
        }

        $key =
            'admin-totp-login:'
            .$admin->getKey()
            .'|'
            .($request->ip() ?? 'unknown');

        $maxAttempts =
            max(
                1,
                (int) config(
                    'tradex_admin_hardening.totp.challenge_attempts_per_minute',
                    5
                )
            );

        if (
            RateLimiter::tooManyAttempts(
                $key,
                $maxAttempts
            )
        ) {
            $seconds =
                RateLimiter::availableIn(
                    $key
                );

            throw ValidationException::withMessages([
                'code' =>
                    "Too many TOTP attempts. Try again in {$seconds} seconds.",
            ]);
        }

        try {
            $totp->consumeLoginCode(
                $admin,
                (string) $request->input(
                    'code'
                )
            );
        } catch (ValidationException $exception) {
            RateLimiter::hit(
                $key,
                60
            );

            AuditLog::record(
                actorType:
                    'ADMIN',
                actorId:
                    $admin->getKey(),
                action:
                    'ADMIN_TOTP_LOGIN_FAILED',
                module:
                    'AUTH',
                newValues: [
                    'reason' =>
                        'INVALID_OR_REPLAYED_TOTP',
                ],
                ipAddress:
                    $request->ip(),
                userAgent:
                    $request->userAgent()
            );

            throw $exception;
        }

        RateLimiter::clear(
            $key
        );

        $remember =
            (bool) $request
                ->session()
                ->pull(
                    'tradex_admin_pending_remember',
                    false
                );

        $this->clearPending(
            $request,
            keepRememberRemoved:
                true
        );

        Auth::guard('admin')->login(
            $admin,
            $remember
        );

        /*
         * Rotate the session identifier after the second factor succeeds.
         */
        $request
            ->session()
            ->regenerate();

        $request
            ->session()
            ->put(
                'tradex_admin_totp_authenticated_at',
                now()->timestamp
            );

        $admin->forceFill([
            'failed_login_attempts' =>
                0,

            'locked_until' =>
                null,

            'last_login_at' =>
                now(),

            'last_login_ip' =>
                $request->ip(),
        ])->save();

        AuditLog::record(
            actorType:
                'ADMIN',
            actorId:
                $admin->getKey(),
            action:
                'ADMIN_LOGIN',
            module:
                'AUTH',
            newValues: [
                'login_method' =>
                    'PASSWORD_TOTP',
            ],
            ipAddress:
                $request->ip(),
            userAgent:
                $request->userAgent()
        );

        return redirect()
            ->intended(
                route(
                    'admin.dashboard'
                )
            );
    }

    public function settings(
        Request $request,
        AdminTotpService $totp
    ): View {
        $admin =
            $this->admin();

        return view(
            'admin.account.totp',
            [
                'admin' =>
                    $admin,

                'provisioning' =>
                    $totp->provisioningData(
                        $admin
                    ),

                'totpRequired' =>
                    (bool) config(
                        'tradex_admin_hardening.totp.required',
                        false
                    ),
            ]
        );
    }

    public function startSetup(
        AdminTotpSetupRequest $request,
        AdminTotpService $totp
    ): RedirectResponse {
        $admin =
            $this->admin();

        $totp->startSetup(
            $admin,
            $request
        );

        return redirect()
            ->route(
                'admin.account.totp.settings'
            )
            ->with(
                'success',
                'TOTP setup started. Add the secret to your authenticator and confirm a fresh code.'
            );
    }

    public function confirmSetup(
        AdminTotpConfirmRequest $request,
        AdminTotpService $totp
    ): RedirectResponse {
        $admin =
            $this->admin();

        $totp->confirmSetup(
            $admin,
            (string) $request->input(
                'code'
            ),
            $request
        );

        if (
            (bool) config(
                'tradex_admin_hardening.totp.required',
                false
            )
        ) {
            /*
             * The confirmation request supplied the current password and a
             * valid fresh TOTP. Promote the restricted enrollment session to
             * a full MFA-authenticated admin session and rotate the ID.
             */
            $request
                ->session()
                ->forget(
                    'tradex_admin_totp_enrollment_only'
                );

            $request
                ->session()
                ->regenerate();

            $request
                ->session()
                ->put(
                    'tradex_admin_totp_authenticated_at',
                    now()->timestamp
                );

            $admin->refresh();

            $admin->forceFill([
                'failed_login_attempts' =>
                    0,

                'locked_until' =>
                    null,

                'last_login_at' =>
                    now(),

                'last_login_ip' =>
                    $request->ip(),
            ])->save();

            AuditLog::record(
                actorType:
                    'ADMIN',
                actorId:
                    $admin->getKey(),
                action:
                    'ADMIN_LOGIN',
                module:
                    'AUTH',
                newValues: [
                    'login_method' =>
                        'PASSWORD_TOTP_ENROLLMENT',
                ],
                ipAddress:
                    $request->ip(),
                userAgent:
                    $request->userAgent()
            );

            return redirect()
                ->route(
                    'admin.dashboard'
                )
                ->with(
                    'success',
                    'Admin TOTP is enabled and this session is now fully authenticated.'
                );
        }

        return redirect()
            ->route(
                'admin.account.totp.settings'
            )
            ->with(
                'success',
                'Admin TOTP is now enabled.'
            );
    }

    private function pendingAdmin(
        Request $request
    ): ?AdminUser {
        $id =
            $request
                ->session()
                ->get(
                    'tradex_admin_pending_login_id'
                );

        $startedAt =
            (int) $request
                ->session()
                ->get(
                    'tradex_admin_pending_started_at',
                    0
                );

        if (
            ! $id
            || $startedAt <= 0
        ) {
            return null;
        }

        $minutes =
            max(
                1,
                (int) config(
                    'tradex_admin_hardening.totp.login_challenge_minutes',
                    5
                )
            );

        if (
            now()->timestamp
            - $startedAt
            > $minutes * 60
        ) {
            $this->clearPending(
                $request
            );

            return null;
        }

        $admin =
            AdminUser::query()
                ->find(
                    $id
                );

        if (! $admin) {
            $this->clearPending(
                $request
            );

            return null;
        }

        return $admin;
    }

    private function clearPending(
        Request $request,
        bool $keepRememberRemoved = false
    ): void {
        $keys = [
            'tradex_admin_pending_login_id',
            'tradex_admin_pending_started_at',
        ];

        if (! $keepRememberRemoved) {
            $keys[] =
                'tradex_admin_pending_remember';
        }

        $request
            ->session()
            ->forget(
                $keys
            );
    }

    private function admin(): AdminUser
    {
        $admin =
            Auth::guard('admin')
                ->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        return $admin;
    }
}
