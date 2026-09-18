<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorAccessAttempt;
use App\Models\TradingFloorAccessDevice;
use App\Models\TradingFloorAccessSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TradingFloorAccessSettingsController extends Controller
{
    public function index(): View
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.settings.manage');

        $settings = TradingFloorAccessSetting::current();
        $devices = TradingFloorAccessDevice::query()
            ->with(['createdByAdmin:id,name,email', 'revokedByAdmin:id,name,email'])
            ->latest('created_at')
            ->get();
        $attempts = TradingFloorAccessAttempt::query()
            ->with(['client:id,uuid,client_code,full_name', 'device:id,uuid,device_code,name'])
            ->latest('occurred_at')
            ->limit(30)
            ->get();

        return view('admin.trading_floor.access.settings', compact('settings', 'devices', 'attempts'));
    }

    public function update(Request $request): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.settings.manage');

        $data = $request->validate([
            'commission_enforcement_mode' => ['required', Rule::in([
                TradingFloorAccessSetting::MODE_NONE,
                TradingFloorAccessSetting::MODE_WARNING,
                TradingFloorAccessSetting::MODE_BLOCK_AFTER_GRACE,
                TradingFloorAccessSetting::MODE_BLOCK_IMMEDIATE,
            ])],
            'block_after_overdue_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        $settings = TradingFloorAccessSetting::current();
        $settings->update([
            'commission_enforcement_mode' => strtoupper((string) $data['commission_enforcement_mode']),
            'block_after_overdue_days' => (int) $data['block_after_overdue_days'],
            'allow_exit_when_blocked' => $request->boolean('allow_exit_when_blocked'),
            'require_active_client' => $request->boolean('require_active_client'),
            'enforce_floor_match' => $request->boolean('enforce_floor_match'),
            'api_enabled' => $request->boolean('api_enabled'),
            'updated_by_admin_id' => $admin->id,
        ]);

        return redirect()
            ->route('admin.trading_floor.access.settings')
            ->with('success', 'Trading Floor access enforcement policy updated.');
    }

    private function admin(): AdminUser
    {
        $admin = auth('admin')->user();
        abort_unless($admin instanceof AdminUser, 401);

        return $admin;
    }

    private function requirePermission(AdminUser $admin, string $permission): void
    {
        abort_unless($admin->isSuperAdmin() || $admin->hasPermission($permission), 403);
    }
}
