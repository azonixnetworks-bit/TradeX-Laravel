<?php

namespace App\Console\Commands;

use App\Models\AdminDevice;
use App\Models\AdminUser;
use App\Models\AuditLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RevokeAdminDeviceCommand extends Command
{
    protected $signature = 'tradex:admin-device-revoke {email} {device} {--reason=}';

    protected $description = 'Server-owner recovery: revoke one trusted Super Admin browser with an audit reason';

    public function handle(): int
    {
        $admin = AdminUser::where('email', $this->argument('email'))->where('is_active', true)->first();
        $reason = trim((string) $this->option('reason'));
        if (! $admin?->isSuperAdmin() || strlen($reason) < 5 || strlen($reason) > 500 || ! ctype_digit((string) $this->argument('device'))) {
            $this->error('Supply an active Super Admin email, numeric device ID and a reason of 5–500 characters.');

            return self::FAILURE;
        }

        return DB::transaction(function () use ($admin, $reason) {
            AdminUser::whereKey($admin->id)->lockForUpdate()->firstOrFail();
            $device = AdminDevice::where('admin_user_id', $admin->id)->whereKey($this->argument('device'))->lockForUpdate()->first();
            if (! $device) {
                $this->error('Device does not belong to this Super Admin.');

                return self::FAILURE;
            }
            $device->update(['status' => 'REVOKED', 'active_slot' => null, 'revoked_at' => now(), 'session_version' => $device->session_version + 1]);
            AuditLog::record(actorType: 'SYSTEM', actorId: null, action: 'ADMIN_DEVICE_SERVER_RECOVERY_REVOKE', module: 'SECURITY', newValues: ['device_id' => $device->id, 'admin_user_id' => $admin->id, 'reason' => $reason]);
            $this->info('Device revoked. Sign in from a new browser with the required credentials.');

            return self::SUCCESS;
        });
    }
}
