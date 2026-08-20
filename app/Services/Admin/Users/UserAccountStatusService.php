<?php

namespace App\Services\Admin\Users;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UserAccountStatusService
{
    public function changeStatus(
        User $user,
        AdminUser $admin,
        string $action,
        string $reason,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): User {

        return DB::transaction(function () use (
            $user,
            $admin,
            $action,
            $reason,
            $ipAddress,
            $userAgent
        ) {

            /*
            |--------------------------------------------------------------------------
            | Lock Current User Row
            |--------------------------------------------------------------------------
            */

            $currentUser = User::query()
                ->whereKey($user->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $oldStatus = $currentUser->status;

            $newStatus = match ($action) {

                'ACTIVATE' => 'ACTIVE',

                'SUSPEND' => 'SUSPENDED',

                'BLOCK' => 'BLOCKED',

                default => throw ValidationException::withMessages([
                    'action' => 'Unsupported account action.',
                ]),
            };


            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate State Changes
            |--------------------------------------------------------------------------
            */

            if ($oldStatus === $newStatus) {

                throw ValidationException::withMessages([
                    'action' =>
                        "This user account is already {$newStatus}.",
                ]);
            }


            /*
            |--------------------------------------------------------------------------
            | Validate State Transitions
            |--------------------------------------------------------------------------
            */

            $this->validateTransition(
                $oldStatus,
                $newStatus
            );


            /*
            |--------------------------------------------------------------------------
            | Prepare Lifecycle Fields
            |--------------------------------------------------------------------------
            */

            $updates = [
                'status' => $newStatus,

                'status_reason' => $reason,

                'status_changed_at' => now(),

                'status_changed_by_admin_id' =>
                    $admin->id,
            ];


            /*
            |--------------------------------------------------------------------------
            | ACTIVATE
            |--------------------------------------------------------------------------
            */

            if ($newStatus === 'ACTIVE') {

                $updates['suspended_at'] = null;
                $updates['blocked_at'] = null;
            }


            /*
            |--------------------------------------------------------------------------
            | SUSPEND
            |--------------------------------------------------------------------------
            */

            if ($newStatus === 'SUSPENDED') {

                $updates['suspended_at'] = now();

                $updates['blocked_at'] = null;
            }


            /*
            |--------------------------------------------------------------------------
            | BLOCK
            |--------------------------------------------------------------------------
            */

            if ($newStatus === 'BLOCKED') {

                $updates['blocked_at'] = now();

                $updates['suspended_at'] = null;
            }


            /*
            |--------------------------------------------------------------------------
            | Update User
            |--------------------------------------------------------------------------
            */

            $currentUser->update($updates);


            /*
            |--------------------------------------------------------------------------
            | Audit
            |--------------------------------------------------------------------------
            */

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->id,
                action: 'USER_STATUS_CHANGED',
                module: 'USERS',
                targetType: 'USER',
                targetId: $currentUser->id,
                oldValues: [
                    'status' => $oldStatus,
                ],
                newValues: [
                    'status' => $newStatus,
                    'action' => $action,
                    'reason' => $reason,
                ],
                ipAddress: $ipAddress,
                userAgent: $userAgent
            );


            return $currentUser->fresh([
                'referrer',
                'statusChangedByAdmin',
            ]);
        });
    }


    private function validateTransition(
        string $oldStatus,
        string $newStatus
    ): void {

        $allowedTransitions = [

            'PENDING' => [
                'ACTIVE',
                'BLOCKED',
            ],

            'ACTIVE' => [
                'SUSPENDED',
                'BLOCKED',
            ],

            'SUSPENDED' => [
                'ACTIVE',
                'BLOCKED',
            ],

            'BLOCKED' => [
                'ACTIVE',
            ],
        ];


        $allowed =
            $allowedTransitions[$oldStatus]
            ?? [];


        if (! in_array(
            $newStatus,
            $allowed,
            true
        )) {

            throw ValidationException::withMessages([
                'action' =>
                    "Account status cannot change from {$oldStatus} to {$newStatus}.",
            ]);
        }
    }
}