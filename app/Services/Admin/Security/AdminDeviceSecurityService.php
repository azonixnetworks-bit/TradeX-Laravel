<?php

namespace App\Services\Admin\Security;

use App\Models\AdminDevice;
use App\Models\AdminDeviceIncident;
use App\Models\AdminUser;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AdminDeviceSecurityService
{
    public const COOKIE = 'tradex_admin_device';

    public const SESSION = 'tradex_admin_device_id';

    public const BLOCK_MESSAGE = 'This device is temporarily blocked. Contact Super Admin to review your devices.';

    public function slot(Request $request): string
    {
        return preg_match('/Android|iPhone|iPad|iPod|Mobile/i', (string) $request->userAgent()) ? 'MOBILE' : 'DESKTOP';
    }

    public function token(Request $request): ?string
    {
        $token = $request->cookie(self::COOKIE);

        return is_string($token) && preg_match('/^[a-f0-9]{64}$/D', $token) ? $token : null;
    }

    // Called only after all required authentication factors succeed.
    public function bind(AdminUser $admin, Request $request): void
    {
        if (! config('tradex_admin_devices.enabled')) {
            return;
        }
        $token = $this->token($request);
        if ($token === null) {
            throw ValidationException::withMessages(['device' => 'Enable cookies and open the login page before signing in.']);
        }
        [$device, $incident] = DB::transaction(function () use ($admin, $request, $token) {
            $locked = AdminUser::query()->lockForUpdate()->findOrFail($admin->id);
            if (! $locked->canLogin()) {
                throw ValidationException::withMessages(['device' => 'This admin account is currently unavailable.']);
            }
            $slot = $this->slot($request);
            $device = AdminDevice::where('admin_user_id', $admin->id)->where('token_hash', hash('sha256', $token))->first();
            $reason = null;
            if ($device && $device->status !== 'ACTIVE') {
                $reason = 'DEVICE_'.$device->status;
            } elseif ($device && $device->device_slot !== $slot) {
                $reason = 'DEVICE_CATEGORY_CHANGED';
            } elseif (! $device && AdminDevice::where('admin_user_id', $admin->id)->where('active_slot', $slot)->exists()) {
                $reason = 'DEVICE_SLOT_OCCUPIED';
            }
            if (! $device) {
                $device = AdminDevice::create([
                    'admin_user_id' => $admin->id,
                    'token_hash' => hash('sha256', $token),
                    'device_slot' => $slot,
                    'active_slot' => $reason ? null : $slot,
                    'status' => $reason ? 'BLOCKED' : 'ACTIVE',
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
                    'last_seen_at' => now(),
                ]);
            }
            if ($reason) {
                // One alert per device/reason per hour, including repeated valid-factor attempts.
                $incident = null;
                if (! AdminDeviceIncident::where('admin_device_id', $device->id)->where('reason', $reason)->where('created_at', '>', now()->subHour())->exists()) {
                    $incident = AdminDeviceIncident::create([
                        'admin_user_id' => $admin->id,
                        'admin_device_id' => $device->id,
                        'reason' => $reason,
                        'device_slot' => $slot,
                        'ip_address' => $request->ip(),
                        'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000),
                    ]);
                    AuditLog::record(actorType: 'ADMIN', actorId: $admin->id, action: 'ADMIN_DEVICE_LOGIN_BLOCKED', module: 'AUTH', newValues: ['device_id' => $device->id, 'reason' => $reason], ipAddress: $request->ip(), userAgent: $request->userAgent());
                }

                return [null, $incident];
            }
            $device->update(['ip_address' => $request->ip(), 'user_agent' => mb_substr((string) $request->userAgent(), 0, 2000), 'last_seen_at' => now()]);

            return [$device, null];
        }, 3);

        if ($incident) {
            try {
                app(AdminDeviceAlertService::class)->schedule($incident);
            } catch (\Throwable) {
                // A broken notification provider must never allow a blocked login.
                Log::warning('Admin device security alert scheduling failed.', ['incident_id' => $incident->id]);
            }
        }
        if (! $device) {
            $request->session()->forget(['tradex_admin_pending_login_id', 'tradex_admin_pending_started_at', 'tradex_admin_pending_remember']);
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            throw ValidationException::withMessages(['device' => self::BLOCK_MESSAGE])->redirectTo(route('admin.login'));
        }
        $request->session()->put(self::SESSION, $device->id);
        $request->session()->put('tradex_admin_device_version', $device->session_version);
    }

    public function validSession(AdminUser $admin, Request $request): bool
    {
        $token = $this->token($request);
        if (! $token) {
            return false;
        }

        return AdminDevice::whereKey($request->session()->get(self::SESSION))
            ->where('admin_user_id', $admin->id)
            ->where('token_hash', hash('sha256', $token))
            ->where('device_slot', $this->slot($request))
            ->where('session_version', $request->session()->get('tradex_admin_device_version', 0))
            ->where('status', 'ACTIVE')->exists();
    }

    public function manage(AdminUser $actor, AdminUser $owner, AdminDevice $device, string $action, string $reason, Request $request): void
    {
        abort_unless($actor->isSuperAdmin(), 403);
        abort_unless($device->admin_user_id === $owner->id, 404);
        DB::transaction(function () use ($actor, $owner, $device, $action, $reason, $request) {
            AdminUser::whereKey($owner->id)->lockForUpdate()->firstOrFail();
            $device = AdminDevice::whereKey($device->id)->lockForUpdate()->firstOrFail();
            $device->increment('session_version');
            \App\Models\AdminDeviceSession::where('admin_device_id', $device->id)->whereNull('ended_at')->update(['ended_at' => now()]);
            if ($action === 'approve') {
                if (AdminDevice::where('admin_user_id', $owner->id)->where('active_slot', $device->device_slot)->where('id', '!=', $device->id)->exists()) {
                    throw ValidationException::withMessages(['device' => 'Revoke the existing device in this category before approving another.']);
                }
                $device->update(['status' => 'ACTIVE', 'active_slot' => $device->device_slot, 'revoked_at' => null]);
            } else {
                $device->update(['status' => 'REVOKED', 'active_slot' => null, 'revoked_at' => now()]);
            }
            AuditLog::record(actorType: 'ADMIN', actorId: $actor->id, action: 'ADMIN_DEVICE_'.strtoupper($action), module: 'SECURITY', newValues: ['device_id' => $device->id, 'admin_user_id' => $owner->id, 'reason' => $reason], ipAddress: $request->ip(), userAgent: $request->userAgent());
        }, 3);
    }
}
