<?php

namespace App\Services\Admin\Customers;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\CustomerOnboardingInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerOnboardingService
{
    public function createInvitation(
        AdminUser $admin,
        array $data,
        Request $request
    ): CustomerOnboardingInvitation {
        $this->expireStaleInvitations();

        $email = mb_strtolower(
            trim((string) $data['email'])
        );

        $whatsapp = trim(
            (string) $data['whatsapp_number']
        );

        $registeredIdentityExists =
            User::query()
                ->whereRaw(
                    'LOWER(email) = ?',
                    [$email]
                )
                ->orWhere(
                    'whatsapp_number',
                    $whatsapp
                )
                ->exists();

        abort_if(
            $registeredIdentityExists,
            409,
            'A registered customer already uses this email or WhatsApp number.'
        );

        $pendingInvitationExists =
            CustomerOnboardingInvitation::query()
                ->where(
                    'status',
                    CustomerOnboardingInvitation::STATUS_PENDING
                )
                ->where(
                    function ($query) use (
                        $email,
                        $whatsapp
                    ): void {
                        $query
                            ->whereRaw(
                                'LOWER(email) = ?',
                                [$email]
                            )
                            ->orWhere(
                                'whatsapp_number',
                                $whatsapp
                            );
                    }
                )
                ->exists();

        abort_if(
            $pendingInvitationExists,
            409,
            'A pending onboarding invitation already exists for this email or WhatsApp number.'
        );

        $referrer = null;
        $referralCode = $data['referral_code'] ?? null;

        if (filled($referralCode)) {
            $referrer =
                User::query()
                    ->where(
                        'referral_code',
                        $referralCode
                    )
                    ->where(
                        'status',
                        'ACTIVE'
                    )
                    ->first();

            abort_if(
                $referrer === null,
                422,
                'The referral code is invalid or unavailable.'
            );
        }

        $days = max(
            1,
            min(
                30,
                (int) config(
                    'tradex_customers.onboarding_invitation_days',
                    7
                )
            )
        );

        return DB::transaction(
            function () use (
                $admin,
                $data,
                $request,
                $email,
                $whatsapp,
                $referrer,
                $referralCode,
                $days
            ): CustomerOnboardingInvitation {
                $invitation =
                    CustomerOnboardingInvitation::query()
                        ->create([
                            'uuid' =>
                                (string) Str::uuid(),

                            'name' =>
                                $data['name'],

                            'email' =>
                                $email,

                            'whatsapp_number' =>
                                $whatsapp,

                            'country_code' =>
                                strtoupper(
                                    (string) $data['country_code']
                                ),

                            'language_code' =>
                                strtolower(
                                    (string) $data['language_code']
                                ),

                            'referral_code_input' =>
                                $referralCode,

                            'referred_by_user_id' =>
                                $referrer?->getKey(),

                            'status' =>
                                CustomerOnboardingInvitation::STATUS_PENDING,

                            'created_by_admin_id' =>
                                $admin->getKey(),

                            'internal_note' =>
                                $data['internal_note']
                                ?? null,

                            'expires_at' =>
                                now()->addDays($days),
                        ]);

                AuditLog::record(
                    actorType:
                        'ADMIN',
                    actorId:
                        $admin->getKey(),
                    action:
                        'CUSTOMER_ONBOARDING_INVITATION_CREATED',
                    module:
                        'USERS',
                    targetType:
                        'CUSTOMER_ONBOARDING_INVITATION',
                    targetId:
                        $invitation->getKey(),
                    oldValues:
                        null,
                    newValues: [
                        'country_code' =>
                            $invitation->country_code,
                        'language_code' =>
                            $invitation->language_code,
                        'has_referrer' =>
                            $invitation->referred_by_user_id
                            !== null,
                        'expires_at' =>
                            $invitation->expires_at
                                ->toIso8601String(),
                    ],
                    ipAddress:
                        $request->ip(),
                    userAgent:
                        $request->userAgent()
                );

                return $invitation;
            },
            3
        );
    }

    public function reconcilePending(): void
    {
        $this->expireStaleInvitations();

        CustomerOnboardingInvitation::query()
            ->where(
                'status',
                CustomerOnboardingInvitation::STATUS_PENDING
            )
            ->orderBy('id')
            ->chunkById(
                100,
                function ($invitations): void {
                    foreach ($invitations as $invitation) {
                        $emailUser =
                            User::query()
                                ->whereRaw(
                                    'LOWER(email) = ?',
                                    [
                                        mb_strtolower(
                                            $invitation->email
                                        ),
                                    ]
                                )
                                ->first();

                        $whatsappUser =
                            User::query()
                                ->where(
                                    'whatsapp_number',
                                    $invitation->whatsapp_number
                                )
                                ->first();

                        if (
                            $emailUser === null
                            && $whatsappUser === null
                        ) {
                            continue;
                        }

                        if (
                            $emailUser !== null
                            && $whatsappUser !== null
                            && $emailUser->getKey()
                                !== $whatsappUser->getKey()
                        ) {
                            $invitation->forceFill([
                                'status' =>
                                    CustomerOnboardingInvitation::STATUS_CONFLICT,
                            ])->save();

                            AuditLog::record(
                                actorType:
                                    'SYSTEM',
                                actorId:
                                    null,
                                action:
                                    'CUSTOMER_ONBOARDING_MATCH_CONFLICT',
                                module:
                                    'USERS',
                                targetType:
                                    'CUSTOMER_ONBOARDING_INVITATION',
                                targetId:
                                    $invitation->getKey(),
                                newValues: [
                                    'status' =>
                                        CustomerOnboardingInvitation::STATUS_CONFLICT,
                                ]
                            );

                            continue;
                        }

                        $matchedUser =
                            $emailUser
                            ?? $whatsappUser;

                        $invitation->forceFill([
                            'status' =>
                                CustomerOnboardingInvitation::STATUS_MATCHED,
                            'matched_user_id' =>
                                $matchedUser->getKey(),
                            'matched_at' =>
                                now(),
                        ])->save();

                        AuditLog::record(
                            actorType:
                                'SYSTEM',
                            actorId:
                                null,
                            action:
                                'CUSTOMER_ONBOARDING_MATCHED',
                            module:
                                'USERS',
                            targetType:
                                'CUSTOMER_ONBOARDING_INVITATION',
                            targetId:
                                $invitation->getKey(),
                            newValues: [
                                'matched_user_id' =>
                                    $matchedUser->getKey(),
                                'status' =>
                                    CustomerOnboardingInvitation::STATUS_MATCHED,
                            ]
                        );
                    }
                }
            );
    }

    public function invitations(
        ?string $status = null,
        int $perPage = 20
    ): LengthAwarePaginator {
        $this->reconcilePending();

        return CustomerOnboardingInvitation::query()
            ->with([
                'matchedUser:id,name,email,status,account_type',
                'createdByAdmin:id,name,email',
                'referrer:id,name,email,referral_code',
            ])
            ->when(
                filled($status),
                fn ($query) =>
                    $query->where(
                        'status',
                        strtoupper((string) $status)
                    )
            )
            ->orderByDesc('created_at')
            ->paginate($perPage)
            ->withQueryString();
    }

    private function expireStaleInvitations(): void
    {
        CustomerOnboardingInvitation::query()
            ->where(
                'status',
                CustomerOnboardingInvitation::STATUS_PENDING
            )
            ->where(
                'expires_at',
                '<=',
                now()
            )
            ->update([
                'status' =>
                    CustomerOnboardingInvitation::STATUS_EXPIRED,
                'updated_at' =>
                    now(),
            ]);
    }
}
