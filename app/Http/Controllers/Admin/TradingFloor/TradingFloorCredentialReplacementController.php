<?php

namespace App\Http\Controllers\Admin\TradingFloor;

use App\Http\Controllers\Controller;
use App\Models\AdminUser;
use App\Models\TradingFloorAccessCredential;
use App\Models\TradingFloorClient;
use App\Models\TradingFloorCredentialReplacement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TradingFloorCredentialReplacementController extends Controller
{
    public function store(
        Request $request,
        TradingFloorClient $client,
        TradingFloorAccessCredential $credential
    ): RedirectResponse {
        $admin = $this->admin();
        $this->requirePermission($admin, 'trading_floor.access.manage');

        abort_unless($credential->trading_floor_client_id === $client->id, 404);
        abort_unless($credential->credential_type === TradingFloorAccessCredential::TYPE_NFC, 422);
        abort_unless($credential->status === TradingFloorAccessCredential::STATUS_ACTIVE, 422);

        $data = $request->validate([
            'new_credential_reference' => ['required', 'string', 'min:2', 'max:255'],
            'replacement_reason' => ['required', 'string', 'max:2000'],
        ]);

        $referenceHash = hash_hmac(
            'sha256',
            TradingFloorAccessCredential::TYPE_NFC.'|GLOBAL|'.trim((string) $data['new_credential_reference']),
            (string) config('app.key')
        );

        $alreadyAssigned = TradingFloorAccessCredential::query()
            ->where('credential_type', TradingFloorAccessCredential::TYPE_NFC)
            ->where('credential_reference', $referenceHash)
            ->exists();

        if ($alreadyAssigned) {
            return back()
                ->withInput($request->except('new_credential_reference'))
                ->withErrors(['new_credential_reference' => 'This NFC card is already registered.']);
        }

        DB::transaction(function () use ($client, $credential, $admin, $referenceHash, $data): void {
            $reason = trim((string) $data['replacement_reason']);

            $credential->update([
                'status' => TradingFloorAccessCredential::STATUS_REVOKED,
                'revoked_at' => now(),
                'revocation_reason' => 'REPLACED: '.$reason,
            ]);

            $newCredential = $client->accessCredentials()->create([
                'credential_type' => TradingFloorAccessCredential::TYPE_NFC,
                'credential_reference' => $referenceHash,
                'device_code' => $credential->device_code,
                'status' => TradingFloorAccessCredential::STATUS_ACTIVE,
                'issued_at' => now(),
                'created_by_admin_id' => $admin->id,
            ]);

            TradingFloorCredentialReplacement::create([
                'trading_floor_client_id' => $client->id,
                'old_credential_id' => $credential->id,
                'new_credential_id' => $newCredential->id,
                'replacement_reason' => $reason,
                'replaced_at' => now(),
                'replaced_by_admin_id' => $admin->id,
            ]);
        });

        return redirect()
            ->route('admin.trading_floor.clients.show', $client)
            ->with('success', 'Lost/replaced NFC card revoked and replacement card activated. Raw card UID was not stored.');
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
