<?php

namespace App\Services\Admin\Security;

use App\Models\AdminDevice;
use App\Models\AdminDeviceSession;
use App\Models\AdminUser;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AdminDeviceSessionService
{
    public function touch(AdminUser $admin, Request $request): void
    {
        $deviceId = $request->session()->get(AdminDeviceSecurityService::SESSION);
        $idle = max(1, (int) config('tradex_admin_hardening.session.idle_timeout_minutes', 30));
        $absolute = max($idle, (int) config('tradex_admin_hardening.session.absolute_timeout_minutes', 480));
        $started = (int) $request->session()->get('tradex_admin_session_started_at', now()->timestamp);
        $expires = min(now()->timestamp + $idle * 60, $started + $absolute * 60);
        AdminDeviceSession::updateOrCreate(['session_hash' => hash('sha256', $request->session()->getId())], [
            'admin_user_id' => $admin->id,
            'admin_device_id' => $deviceId,
            'session_version' => $request->session()->get('tradex_admin_device_version'),
            'ip_address' => $request->ip(),
            'last_seen_at' => now(),
            'expires_at' => Carbon::createFromTimestampUTC($expires),
            'ended_at' => null,
        ]);
        AdminDevice::whereKey($deviceId)->update(['last_seen_at' => now(), 'ip_address' => $request->ip()]);
    }

    public function end(string $sessionId): void
    {
        AdminDeviceSession::where('session_hash', hash('sha256', $sessionId))->whereNull('ended_at')->update(['ended_at' => now()]);
    }

    public function summary()
    {
        $admins = AdminUser::query()->withCount([
            'adminDevices as approved_devices_count' => fn ($query) => $query->where('status', 'ACTIVE'),
            'adminDevices as mobile_devices_count' => fn ($query) => $query->where('status', 'ACTIVE')->where('device_slot', 'MOBILE'),
            'adminDevices as desktop_devices_count' => fn ($query) => $query->where('status', 'ACTIVE')->where('device_slot', 'DESKTOP'),
            'adminDevices as blocked_devices_count' => fn ($query) => $query->where('status', 'BLOCKED'),
            'adminDevices as signed_in_devices_count' => fn ($query) => $query->where('status', 'ACTIVE')->whereHas('sessions', fn ($session) => $session->live()),
            'deviceSessions as signed_in_sessions_count' => fn ($query) => $query->live(),
        ])->withMax('deviceSessions', 'last_seen_at')->orderBy('name')->paginate(10, ['*'], 'admin_devices_page')->withQueryString();

        $security = app(AdminDeviceSecurityService::class);
        $admins->getCollection()->each(function (AdminUser $admin) use ($security): void {
            $admin->setAttribute('device_policy', $security->policy($admin));
        });

        return $admins;
    }
}
