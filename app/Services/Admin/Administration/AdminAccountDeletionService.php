<?php

namespace App\Services\Admin\Administration;

use App\Models\AdminUser;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminAccountDeletionService
{
    public function delete(
        AdminUser $actor,
        AdminUser $target,
        string $reason,
        Request $request
    ): AdminUser {
        return DB::transaction(function () use (
            $actor,
            $target,
            $reason,
            $request
        ): AdminUser {
            $locked = AdminUser::query()
                ->lockForUpdate()
                ->findOrFail($target->getKey());

            abort_if(
                $locked->isSuperAdmin(),
                403,
                'The protected Super Admin account cannot be deleted.'
            );

            abort_if(
                $actor->getKey() === $locked->getKey(),
                403,
                'You cannot delete your own admin account.'
            );

            abort_if(
                (bool) $locked->is_active,
                422,
                'Disable this admin account before deleting it.'
            );

            $roleCodes = $locked->roles()
                ->orderBy('code')
                ->pluck('code')
                ->values()
                ->all();

            $locked->delete();

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $actor->getKey(),
                action: 'ADMIN_ACCOUNT_DELETED',
                module: 'ADMINISTRATION',
                targetType: 'ADMIN_USER',
                targetId: $locked->getKey(),
                oldValues: [
                    'email' => $locked->email,
                    'role_codes' => $roleCodes,
                    'is_active' => false,
                ],
                newValues: [
                    'deleted' => true,
                    'reason' => Str::limit(trim($reason), 500, ''),
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $locked;
        }, 3);
    }
}
