<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorAccessDevice;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TradingFloorAccessDeviceController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.settings.manage');

        $data = $request->validate([
            'device_code' => [
                'required', 'string', 'max:100',
                Rule::unique('trading_floor_access_devices', 'device_code'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'floor_code' => ['nullable', 'string', 'max:50'],
        ]);

        $token = bin2hex(random_bytes(32));
        $device = TradingFloorAccessDevice::create([
            'uuid' => (string) Str::uuid(),
            'device_code' => strtoupper(trim((string) $data['device_code'])),
            'name' => trim((string) $data['name']),
            'floor_code' => filled($data['floor_code'] ?? null)
                ? strtoupper(trim((string) $data['floor_code']))
                : null,
            'token_hash' => $this->tokenHash($token),
            'status' => TradingFloorAccessDevice::STATUS_ACTIVE,
            'created_by_admin_id' => $admin->id,
        ]);

        return redirect()
            ->route('admin.trading_floor.access.settings')
            ->with('success', 'Trading Floor reader provisioned. Copy the token now; it will not be shown again.')
            ->with('new_device_code', $device->device_code)
            ->with('new_device_token', $token);
    }

    public function rotate(TradingFloorAccessDevice $device): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.settings.manage');
        abort_unless($device->status === TradingFloorAccessDevice::STATUS_ACTIVE, 422);

        $token = bin2hex(random_bytes(32));
        $device->update([
            'token_hash' => $this->tokenHash($token),
        ]);

        return redirect()
            ->route('admin.trading_floor.access.settings')
            ->with('success', 'Reader token rotated. The previous token is invalid immediately.')
            ->with('new_device_code', $device->device_code)
            ->with('new_device_token', $token);
    }

    public function revoke(Request $request, TradingFloorAccessDevice $device): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.settings.manage');

        $data = $request->validate([
            'revocation_reason' => ['required', 'string', 'max:1000'],
        ]);

        if ($device->status !== TradingFloorAccessDevice::STATUS_REVOKED) {
            $device->update([
                'status' => TradingFloorAccessDevice::STATUS_REVOKED,
                'revoked_at' => now(),
                'revocation_reason' => trim((string) $data['revocation_reason']),
                'revoked_by_admin_id' => $admin->id,
            ]);
        }

        return redirect()
            ->route('admin.trading_floor.access.settings')
            ->with('success', 'Trading Floor reader revoked. Its device token can no longer access the API.');
    }

    private function tokenHash(string $token): string
    {
        return hash_hmac('sha256', $token, (string) config('app.key'));
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
