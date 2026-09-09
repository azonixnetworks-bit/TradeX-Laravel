<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\AdminLoginRequest;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Support\Admin\AdminAccessMap;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class LoginController extends Controller
{
    private const MAX_RATE_ATTEMPTS = 5;

    private const RATE_DECAY_SECONDS = 60;

    private const ACCOUNT_LOCK_ATTEMPTS = 5;

    private const ACCOUNT_LOCK_MINUTES = 15;

    public function show(): View|RedirectResponse
    {
        $admin = Auth::guard('admin')->user();

        if ($admin instanceof AdminUser) {
            return redirect()->route(
                AdminAccessMap::landingRouteName($admin)
            );
        }

        return view('admin.auth.login');
    }

    public function login(
        AdminLoginRequest $request
    ): RedirectResponse {

        $email = Str::lower(
            trim((string) $request->input('email'))
        );

        $throttleKey = $this->throttleKey(
            $email,
            $request->ip()
        );

        /*
        |--------------------------------------------------------------------------
        | IP + Email Rate Limiting
        |--------------------------------------------------------------------------
        */

        if (
            RateLimiter::tooManyAttempts(
                $throttleKey,
                self::MAX_RATE_ATTEMPTS
            )
        ) {

            $seconds = RateLimiter::availableIn(
                $throttleKey
            );

            throw ValidationException::withMessages([
                'email' =>
                    "Too many login attempts. Try again in {$seconds} seconds.",
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Find Admin
        |--------------------------------------------------------------------------
        */

        $admin = AdminUser::query()
            ->whereRaw(
                'LOWER(email) = ?',
                [$email]
            )
            ->first();

        /*
        |--------------------------------------------------------------------------
        | Invalid Email
        |--------------------------------------------------------------------------
        */

        if (! $admin) {

            RateLimiter::hit(
                $throttleKey,
                self::RATE_DECAY_SECONDS
            );

            AuditLog::record(
                actorType: 'SYSTEM',
                actorId: null,
                action: 'ADMIN_LOGIN_FAILED',
                module: 'AUTH',
                newValues: [
                    'email' => $email,
                    'reason' => 'INVALID_CREDENTIALS',
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            $this->invalidCredentials();
        }

        /*
        |--------------------------------------------------------------------------
        | Disabled Account
        |--------------------------------------------------------------------------
        */

        if (! $admin->is_active) {

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->id,
                action: 'ADMIN_LOGIN_BLOCKED',
                module: 'AUTH',
                newValues: [
                    'reason' => 'ACCOUNT_DISABLED',
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            throw ValidationException::withMessages([
                'email' =>
                    'This admin account is currently unavailable.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Account Lock Check
        |--------------------------------------------------------------------------
        */

        if ($admin->isLocked()) {

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->id,
                action: 'ADMIN_LOGIN_BLOCKED',
                module: 'AUTH',
                newValues: [
                    'reason' => 'ACCOUNT_TEMPORARILY_LOCKED',
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            throw ValidationException::withMessages([
                'email' =>
                    'This account is temporarily locked. Please try again later.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Password Verification
        |--------------------------------------------------------------------------
        */

        if (
            ! Hash::check(
                (string) $request->input('password'),
                $admin->password
            )
        ) {

            RateLimiter::hit(
                $throttleKey,
                self::RATE_DECAY_SECONDS
            );

            /*
             * ADMIN-19: serialize failed-attempt mutation so parallel invalid
             * password requests cannot under-count the account lock threshold.
             */
            $failedAttempts =
                $this->recordPasswordFailure(
                    $admin
                );

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->id,
                action: 'ADMIN_LOGIN_FAILED',
                module: 'AUTH',
                newValues: [
                    'reason' => 'INVALID_CREDENTIALS',
                    'failed_attempts' =>
                        $failedAttempts,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            $this->invalidCredentials();
        }

        /*
        |--------------------------------------------------------------------------
        | Successful Password Verification
        |--------------------------------------------------------------------------
        */

        RateLimiter::clear(
            $throttleKey
        );

        /*
        |--------------------------------------------------------------------------
        | ADMIN-19 — Rollout-Safe TOTP Gate
        |--------------------------------------------------------------------------
        |
        | The default local/test configuration preserves the existing
        | password-only development workflow. Production release readiness
        | requires TRADEX_ADMIN_TOTP_REQUIRED=true after all active admins have
        | enrolled TOTP.
        |
        */

        if (
            (bool) config(
                'tradex_admin_hardening.totp.required',
                false
            )
            || $admin->hasConfirmedTotp()
        ) {
            if (
                ! $admin->hasConfirmedTotp()
            ) {
                return $this
                    ->beginTotpEnrollmentOnlySession(
                        $request,
                        $admin
                    );
            }

            $admin->forceFill([
                'failed_login_attempts' => 0,
                'locked_until' => null,
            ])->save();

            /*
             * Rotate before creating the short-lived second-factor challenge.
             */
            $request
                ->session()
                ->regenerate();

            $request
                ->session()
                ->put([
                    'tradex_admin_pending_login_id' =>
                        $admin->getKey(),

                    'tradex_admin_pending_started_at' =>
                        now()->timestamp,

                    'tradex_admin_pending_remember' =>
                        $request->boolean(
                            'remember'
                        ),
                ]);

            AuditLog::record(
                actorType:
                    'ADMIN',
                actorId:
                    $admin->getKey(),
                action:
                    'ADMIN_LOGIN_PASSWORD_VERIFIED',
                module:
                    'AUTH',
                newValues: [
                    'next_factor' =>
                        'TOTP',
                ],
                ipAddress:
                    $request->ip(),
                userAgent:
                    $request->userAgent()
            );

            return redirect()->route(
                'admin.totp.challenge'
            );
        }

        return $this->completePasswordOnlyLogin(
            $request,
            $admin
        );
    }

    public function logout(
        Request $request
    ): RedirectResponse {

        $admin = Auth::guard('admin')->user();

        if ($admin) {

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->id,
                action: 'ADMIN_LOGOUT',
                module: 'AUTH',
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
        }

        Auth::guard('admin')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()
            ->route('admin.login')
            ->with(
                'success',
                'You have been signed out securely.'
            );
    }

    private function beginTotpEnrollmentOnlySession(
        Request $request,
        AdminUser $admin
    ): RedirectResponse {
        $admin->forceFill([
            'failed_login_attempts' =>
                0,

            'locked_until' =>
                null,
        ])->save();

        /*
         * A real admin guard session is required for current_password:admin
         * validation during enrollment, but ADMIN-19's ForceAdminTotp
         * middleware confines this session to the TOTP enrollment surface and
         * logout until the second factor is confirmed.
         *
         * Remember-me is deliberately disabled for this restricted session.
         */
        Auth::guard('admin')->login(
            $admin,
            false
        );

        $request
            ->session()
            ->regenerate();

        $request
            ->session()
            ->put(
                'tradex_admin_totp_enrollment_only',
                true
            );

        AuditLog::record(
            actorType:
                'ADMIN',
            actorId:
                $admin->getKey(),
            action:
                'ADMIN_LOGIN_PASSWORD_VERIFIED',
            module:
                'AUTH',
            newValues: [
                'next_factor' =>
                    'TOTP_ENROLLMENT',

                'session_scope' =>
                    'ENROLLMENT_ONLY',
            ],
            ipAddress:
                $request->ip(),
            userAgent:
                $request->userAgent()
        );

        return redirect()
            ->route(
                'admin.account.totp.settings'
            )
            ->with(
                'error',
                'Two-factor authentication must be enrolled before this admin session can access TradeX administration.'
            );
    }

    private function completePasswordOnlyLogin(
        Request $request,
        AdminUser $admin
    ): RedirectResponse {
        $admin->update([
            'failed_login_attempts' => 0,
            'locked_until' => null,
            'last_login_at' => now(),
            'last_login_ip' => $request->ip(),
        ]);

        Auth::guard('admin')->login(
            $admin,
            $request->boolean('remember')
        );

        /*
         * Prevent session fixation.
         */
        $request->session()->regenerate();

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->id,
            action: 'ADMIN_LOGIN',
            module: 'AUTH',
            newValues: [
                'login_method' => 'PASSWORD',
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return redirect()
            ->intended(
                route(
                    AdminAccessMap::landingRouteName($admin)
                )
            );
    }

    private function recordPasswordFailure(
        AdminUser $admin
    ): int {
        return DB::transaction(
            function () use (
                $admin
            ): int {
                $locked =
                    AdminUser::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $admin->getKey()
                        );

                $failedAttempts =
                    (int) $locked
                        ->failed_login_attempts
                    + 1;

                $updateData = [
                    'failed_login_attempts' =>
                        $failedAttempts,
                ];

                if (
                    $failedAttempts >=
                    self::ACCOUNT_LOCK_ATTEMPTS
                ) {
                    $updateData['locked_until'] =
                        now()->addMinutes(
                            self::ACCOUNT_LOCK_MINUTES
                        );
                }

                $locked->forceFill(
                    $updateData
                )->save();

                return $failedAttempts;
            },
            3
        );
    }

    private function throttleKey(
        string $email,
        ?string $ipAddress
    ): string {

        return 'admin-login:'
            . Str::transliterate($email)
            . '|'
            . ($ipAddress ?? 'unknown');
    }

    private function invalidCredentials(): never
    {
        throw ValidationException::withMessages([
            'email' =>
                'The email address or password is incorrect.',
        ]);
    }
}
