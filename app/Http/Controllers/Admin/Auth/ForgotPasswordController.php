<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ForgotPasswordController extends Controller
{
    public function show(): View|RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.auth.forgot-password');
    }

    public function send(Request $request): RedirectResponse
    {
        if (Auth::guard('admin')->check()) {
            return redirect()->route('admin.dashboard');
        }

        $validated = $request->validate([
            'email' => ['required', 'email:rfc', 'max:190'],
        ]);

        $email = Str::lower(trim((string) $validated['email']));

        $admin = AdminUser::query()
            ->whereRaw('LOWER(email) = ?', [$email])
            ->where('is_active', true)
            ->first();

        if ($admin instanceof AdminUser) {
            Password::broker('admins')->sendResetLink([
                'email' => $admin->email,
            ]);

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->getKey(),
                action: 'ADMIN_PASSWORD_RESET_REQUESTED',
                module: 'AUTH',
                newValues: ['delivery' => 'EMAIL'],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
        }

        /* Never disclose whether an admin email exists or is active. */
        return back()->with(
            'status',
            'If an active admin account matches that email, a password reset link has been sent.'
        );
    }
}
