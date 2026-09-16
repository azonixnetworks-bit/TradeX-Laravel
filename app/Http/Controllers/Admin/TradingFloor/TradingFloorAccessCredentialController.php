<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorAccessCredential;
use App\Models\TradingFloorClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TradingFloorAccessCredentialController extends Controller
{
    public function store(Request $request, TradingFloorClient $client): RedirectResponse
    {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.access.manage');

        $data = $request->validate([
            'credential_type' => ['required', Rule::in([
                TradingFloorAccessCredential::TYPE_NFC,
                TradingFloorAccessCredential::TYPE_FINGERPRINT,
            ])],
            'credential_reference' => ['required', 'string', 'min:2', 'max:255'],
            'device_code' => ['nullable', 'string', 'max:100'],
        ]);

        $type = strtoupper(trim((string) $data['credential_type']));
        $referenceHash = $this->referenceHash((string) $data['credential_reference']);

        $alreadyAssigned = TradingFloorAccessCredential::query()
            ->where('credential_type', $type)
            ->where('credential_reference', $referenceHash)
            ->exists();

        if ($alreadyAssigned) {
            return back()
                ->withInput($request->except('credential_reference'))
                ->withErrors(['credential_reference' => 'This NFC card or fingerprint device reference is already assigned.']);
        }

        DB::transaction(function () use ($client, $admin, $data, $type, $referenceHash): void {
            $client->accessCredentials()->create([
                'credential_type' => $type,
                'credential_reference' => $referenceHash,
                'device_code' => filled($data['device_code'] ?? null)
                    ? trim((string) $data['device_code'])
                    : null,
                'status' => TradingFloorAccessCredential::STATUS_ACTIVE,
                'issued_at' => now(),
                'created_by_admin_id' => $admin->id,
            ]);
        });

        return redirect()
            ->route('admin.trading_floor.clients.show', $client)
            ->with('success', 'Access credential assigned. The raw NFC/fingerprint reference was not stored.');
    }

    public function revoke(
        Request $request,
        TradingFloorClient $client,
        TradingFloorAccessCredential $credential
    ): RedirectResponse {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.access.manage');

        abort_unless($credential->trading_floor_client_id === $client->id, 404);

        $data = $request->validate([
            'revocation_reason' => ['required', 'string', 'max:1000'],
        ]);

        if ($credential->status !== TradingFloorAccessCredential::STATUS_REVOKED) {
            $credential->update([
                'status' => TradingFloorAccessCredential::STATUS_REVOKED,
                'revoked_at' => now(),
                'revocation_reason' => trim((string) $data['revocation_reason']),
            ]);
        }

        return redirect()
            ->route('admin.trading_floor.clients.show', $client)
            ->with('success', 'Access credential revoked. Historical access records remain preserved.');
    }

    private function referenceHash(string $reference): string
    {
        return hash_hmac(
            'sha256',
            trim($reference),
            (string) config('app.key')
        );
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
