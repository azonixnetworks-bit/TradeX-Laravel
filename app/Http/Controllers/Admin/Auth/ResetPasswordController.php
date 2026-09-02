<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\AuditLog;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ResetPasswordController extends Controller
{
    public function show(Request $request, string $token): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.reset-password', [
            'token' => $token,
            'email' => (string) $request->query('email', ''),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email:rfc', 'max:190'],
            'password' => [
                'required',
                'confirmed',
                PasswordRule::min(16)
                    ->mixedCase()
                    ->numbers()
                    ->symbols(),
            ],
        ]);

        $email = Str::lower(trim((string) $validated['email']));

        $activeAdmin = AdminUser::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('is_active', true)
            ->first();

        if (! $activeAdmin instanceof AdminUser) {
            throw ValidationException::withMessages([
                'email' => 'This password reset link is invalid or unavailable.',
            ]);
        }

        $status = Password::broker('admins')->reset(
            [
                'email' => $activeAdmin->email,
                'password' => (string) $validated['password'],
                'password_confirmation' => (string) $request->input('password_confirmation'),
                'token' => (string) $validated['token'],
            ],
            function (AdminUser $admin, string $password) use ($request): void {
                /*
                 * Password recovery must not disable or replace an enrolled
                 * TOTP factor. Only password/session lock state is changed.
                 */
                $admin->forceFill([
                    'password' => $password,
                    'remember_token' => Str::random(60),
                    'failed_login_attempts' => 0,
                    'locked_until' => null,
                ])->save();

                event(new PasswordReset($admin));

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->getKey(),
                    action: 'ADMIN_PASSWORD_RESET_COMPLETED',
                    module: 'AUTH',
                    newValues: [
                        'totp_preserved' => $admin->hasConfirmedTotp(),
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'This password reset link is invalid or expired.',
            ]);
        }

        return redirect()
            ->route('admin.login')
            ->with(
                'success',
                'Your admin password has been reset. Sign in with the new password; two-factor verification will still be required when enabled.'
            );
    }
}
