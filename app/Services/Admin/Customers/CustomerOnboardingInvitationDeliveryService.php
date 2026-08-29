<?php

namespace App\Services\Admin\Customers;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\CustomerOnboardingInvitation;
use App\Models\UserRegistrationSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class CustomerOnboardingInvitationDeliveryService
{
    public function send(
        CustomerOnboardingInvitation $invitation,
        AdminUser $admin,
        Request $request,
        bool $extendExpiry = false
    ): bool {
        abort_unless(
            $invitation->status
                === CustomerOnboardingInvitation::STATUS_PENDING,
            409,
            'Only pending onboarding invitations can be sent.'
        );

        $plainToken = bin2hex(
            random_bytes(32)
        );

        $newTokenHash = hash(
            'sha256',
            $plainToken
        );

        $previous = [
            'activation_token_hash' =>
                $invitation->activation_token_hash,
            'activation_consumed_at' =>
                $invitation->activation_consumed_at,
            'onboarding_stage' =>
                $invitation->onboarding_stage,
            'expires_at' =>
                $invitation->expires_at,
        ];

        $expiresAt = $extendExpiry
            ? now()->addDays(
                $this->invitationDays()
            )
            : $invitation->expires_at;

        $invitation->forceFill([
            'activation_token_hash' =>
                $newTokenHash,
            'activation_consumed_at' => null,
            'onboarding_stage' =>
                CustomerOnboardingInvitation::STAGE_INVITE_CREATED,
            'expires_at' => $expiresAt,
        ])->save();

        try {
            $this->deliverEmail(
                $invitation,
                $plainToken
            );
        } catch (Throwable $exception) {
            CustomerOnboardingInvitation::query()
                ->whereKey($invitation->getKey())
                ->where(
                    'activation_token_hash',
                    $newTokenHash
                )
                ->update([
                    'activation_token_hash' =>
                        $previous['activation_token_hash'],
                    'activation_consumed_at' =>
                        $previous['activation_consumed_at'],
                    'onboarding_stage' =>
                        $previous['onboarding_stage'],
                    'expires_at' =>
                        $previous['expires_at'],
                ]);

            $invitation->refresh();

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->getKey(),
                action: 'CUSTOMER_ONBOARDING_INVITATION_DELIVERY_FAILED',
                module: 'USERS',
                targetType: 'CUSTOMER_ONBOARDING_INVITATION',
                targetId: $invitation->getKey(),
                oldValues: null,
                newValues: [
                    'delivery_channel' => 'EMAIL',
                    'status' => $invitation->status,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            report($exception);

            return false;
        }

        $now = now();

        $updated = CustomerOnboardingInvitation::query()
            ->whereKey($invitation->getKey())
            ->where(
                'status',
                CustomerOnboardingInvitation::STATUS_PENDING
            )
            ->where(
                'activation_token_hash',
                $newTokenHash
            )
            ->update([
                'onboarding_stage' =>
                    CustomerOnboardingInvitation::STAGE_INVITE_SENT,
                'invited_at' =>
                    $invitation->invited_at ?? $now,
                'last_invited_at' => $now,
                'invite_send_count' =>
                    ((int) $invitation->invite_send_count) + 1,
            ]);

        $invitation->refresh();

        if ($updated !== 1) {
            AuditLog::record(
                actorType: 'SYSTEM',
                actorId: null,
                action: 'CUSTOMER_ONBOARDING_INVITATION_SEND_SUPERSEDED',
                module: 'USERS',
                targetType: 'CUSTOMER_ONBOARDING_INVITATION',
                targetId: $invitation->getKey(),
                oldValues: null,
                newValues: [
                    'status' => $invitation->status,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return false;
        }

        /*
         * A successfully reissued invitation becomes the only supported
         * onboarding path. Any older registration session linked to this
         * invitation is superseded so two parallel credentials cannot remain
         * usable after an admin intentionally resends the activation link.
         */
        $supersededSessions = $invitation
            ->registrationSessions()
            ->where(
                'status',
                UserRegistrationSession::STATUS_PENDING
            )
            ->update([
                'status' =>
                    UserRegistrationSession::STATUS_SUPERSEDED,
            ]);

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: 'CUSTOMER_ONBOARDING_INVITATION_SENT',
            module: 'USERS',
            targetType: 'CUSTOMER_ONBOARDING_INVITATION',
            targetId: $invitation->getKey(),
            oldValues: null,
            newValues: [
                'delivery_channel' => 'EMAIL',
                'onboarding_stage' =>
                    CustomerOnboardingInvitation::STAGE_INVITE_SENT,
                'expires_at' =>
                    $invitation->expires_at->toIso8601String(),
                'invite_send_count' =>
                    $invitation->invite_send_count,
                'superseded_registration_sessions' =>
                    $supersededSessions,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return true;
    }

    public function revoke(
        CustomerOnboardingInvitation $invitation,
        AdminUser $admin,
        Request $request
    ): void {
        abort_unless(
            $invitation->status
                === CustomerOnboardingInvitation::STATUS_PENDING,
            409,
            'Only pending onboarding invitations can be revoked.'
        );

        $invitation->forceFill([
            'status' =>
                CustomerOnboardingInvitation::STATUS_REVOKED,
            'activation_token_hash' => null,
            'active_email_hash' => null,
            'active_whatsapp_hash' => null,
            'revoked_at' => now(),
            'revoked_by_admin_id' => $admin->getKey(),
        ])->save();

        $supersededSessions = $invitation
            ->registrationSessions()
            ->where(
                'status',
                UserRegistrationSession::STATUS_PENDING
            )
            ->update([
                'status' =>
                    UserRegistrationSession::STATUS_SUPERSEDED,
            ]);

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: 'CUSTOMER_ONBOARDING_INVITATION_REVOKED',
            module: 'USERS',
            targetType: 'CUSTOMER_ONBOARDING_INVITATION',
            targetId: $invitation->getKey(),
            oldValues: null,
            newValues: [
                'status' =>
                    CustomerOnboardingInvitation::STATUS_REVOKED,
                'superseded_registration_sessions' =>
                    $supersededSessions,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );
    }

    private function deliverEmail(
        CustomerOnboardingInvitation $invitation,
        string $plainToken
    ): void {
        $url = $this->activationUrl(
            $invitation,
            $plainToken
        );

        Mail::raw(
            "You have been invited to TradeX OS.\n\n"
            ."Open this secure activation link to continue onboarding:\n{$url}\n\n"
            ."The link expires on "
            .$invitation->expires_at->toDayDateTimeString()
            .". If you did not expect this invitation, ignore this email.",
            function ($message) use ($invitation): void {
                $message
                    ->to($invitation->email)
                    ->subject(
                        'Activate your TradeX OS account'
                    );
            }
        );
    }

    private function activationUrl(
        CustomerOnboardingInvitation $invitation,
        string $plainToken
    ): string {
        $baseUrl = trim(
            (string) config(
                'tradex_customers.activation_url'
            )
        );

        if ($baseUrl === '') {
            $baseUrl = rtrim(
                (string) config('app.url'),
                '/'
            ).'/onboarding/activate';
        }

        $separator = str_contains(
            $baseUrl,
            '?'
        ) ? '&' : '?';

        return $baseUrl
            .$separator
            .'invitation_id='
            .rawurlencode($invitation->uuid)
            .'&token='
            .rawurlencode($plainToken);
    }

    private function invitationDays(): int
    {
        return max(
            1,
            min(
                30,
                (int) config(
                    'tradex_customers.onboarding_invitation_days',
                    7
                )
            )
        );
    }
}
