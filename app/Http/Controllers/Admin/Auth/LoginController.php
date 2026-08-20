<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\AdminLoginRequest;
use App\Models\AdminUser;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
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
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
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

            $failedAttempts =
                $admin->failed_login_attempts + 1;

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

            $admin->update($updateData);

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
        | Successful Authentication
        |--------------------------------------------------------------------------
        */

        RateLimiter::clear($throttleKey);

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
                route('admin.dashboard')
            );
    }

    public function logout(
        \Illuminate\Http\Request $request
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