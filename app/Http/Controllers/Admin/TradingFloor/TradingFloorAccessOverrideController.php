<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TradingFloorAccessOverrideController extends Controller
{
    public function update(Request $request, TradingFloorClient $client): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.access.manage');

        $data = $request->validate([
            'access_override_mode' => ['required', Rule::in([
                TradingFloorClient::ACCESS_OVERRIDE_AUTO,
                TradingFloorClient::ACCESS_OVERRIDE_FORCE_ALLOW,
                TradingFloorClient::ACCESS_OVERRIDE_FORCE_DENY,
            ])],
            'access_override_until' => ['nullable', 'date', 'after:now'],
            'access_override_reason' => [
                Rule::requiredIf(fn () => strtoupper((string) $request->input('access_override_mode')) !== TradingFloorClient::ACCESS_OVERRIDE_AUTO),
                'nullable', 'string', 'max:2000',
            ],
        ]);

        $mode = strtoupper((string) $data['access_override_mode']);

        if ($mode === TradingFloorClient::ACCESS_OVERRIDE_AUTO) {
            $client->update([
                'access_override_mode' => TradingFloorClient::ACCESS_OVERRIDE_AUTO,
                'access_override_until' => null,
                'access_override_reason' => null,
                'access_override_by_admin_id' => null,
            ]);
        } else {
            $client->update([
                'access_override_mode' => $mode,
                'access_override_until' => $data['access_override_until'] ?? null,
                'access_override_reason' => trim((string) $data['access_override_reason']),
                'access_override_by_admin_id' => $admin->id,
            ]);
        }

        return redirect()
            ->route('admin.trading_floor.clients.show', $client)
            ->with('success', 'Customer access override updated.');
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
