<?php

namespace App\Http\Controllers\Admin\Administration;

use App\Http\Controllers\Controller;
use App\Models\AdminDevice;
use App\Models\AdminDeviceAlert;
use App\Models\AdminDeviceIncident;
use App\Models\AdminDeviceSession;
use App\Models\AdminUser;
use App\Services\Admin\Security\AdminDeviceSecurityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AdminDeviceController extends Controller
{
    public function index(AdminUser $adminUser): View
    {
        abort_unless(auth('admin')->user()?->isSuperAdmin(), 403);
        $incidents = AdminDeviceIncident::where('admin_user_id', $adminUser->id)->latest('id')->limit(30)->get();

        return view('admin.admins.devices', [
            'adminUser' => $adminUser,
            'devices' => AdminDevice::where('admin_user_id', $adminUser->id)->withCount(['sessions as live_sessions_count' => fn ($query) => $query->live()])->latest('id')->paginate(20),
            'sessions' => AdminDeviceSession::where('admin_user_id', $adminUser->id)->live()->latest('last_seen_at')->limit(100)->get(),
            'incidents' => $incidents,
            'alerts' => AdminDeviceAlert::whereIn('admin_device_incident_id', $incidents->pluck('id'))->get()->groupBy('admin_device_incident_id'),
            'devicePolicy' => app(AdminDeviceSecurityService::class)->policy($adminUser),
        ]);
    }

    public function update(Request $request, AdminUser $adminUser, AdminDevice $adminDevice, AdminDeviceSecurityService $security): RedirectResponse
    {
        abort_unless(auth('admin')->user()?->isSuperAdmin(), 403);
        $data = $request->validate(['action' => ['required', 'in:revoke,approve'], 'reason' => ['required', 'string', 'min:5', 'max:500']]);
        $security->manage(auth('admin')->user(), $adminUser, $adminDevice, $data['action'], $data['reason'], $request);

        return back()->with('success', 'Device access updated. The admin must sign in again on an approved device.');
    }
}
