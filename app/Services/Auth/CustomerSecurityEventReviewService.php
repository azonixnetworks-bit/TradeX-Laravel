<?php

namespace App\Services\Auth;

use App\Exceptions\AuthApiException;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserRefreshToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerSecurityEventReviewService
{
    public const RESPONSE_YES = 'YES';

    public const RESPONSE_NO = 'NO';

    public const ACTION_CONFIRMED = 'USER_SECURITY_EVENT_CONFIRMED';

    public const ACTION_REPORTED = 'USER_SECURITY_EVENT_REPORTED_UNAUTHORIZED';

    private const REVIEWABLE_ACTIONS = [
        'USER_LOGIN_RISK_DETECTED',
        'USER_SUSPICIOUS_LOGIN_DETECTED',
    ];

    public function __construct(
        private readonly AccessTokenService $accessTokens
    ) {
    }

    public function respond(
        User $user,
        UserDevice $currentDevice,
        int $eventId,
        string $response,
        Request $request
    ): array {
        $response = strtoupper(trim($response));

        if (! in_array($response, [self::RESPONSE_YES, self::RESPONSE_NO], true)) {
            throw new AuthApiException(
                422,
                'INVALID_SECURITY_REVIEW_RESPONSE',
                'The security review response must be YES or NO.'
            );
        }

        return DB::transaction(function () use (
            $user,
            $currentDevice,
            $eventId,
            $response,
            $request
        ): array {
            $event = AuditLog::query()
                ->whereKey($eventId)
                ->where('actor_type', 'USER')
                ->where('actor_id', $user->getKey())
                ->whereIn('action', self::REVIEWABLE_ACTIONS)
                ->lockForUpdate()
                ->first();

            if (! $event instanceof AuditLog) {
                throw new AuthApiException(
                    404,
                    'SECURITY_EVENT_NOT_FOUND',
                    'The selected security event was not found.'
                );
            }

            $existing = AuditLog::query()
                ->where('actor_type', 'USER')
                ->where('actor_id', $user->getKey())
                ->where('target_type', 'AUDIT_LOG')
                ->where('target_id', $event->getKey())
                ->whereIn('action', [
                    self::ACTION_CONFIRMED,
                    self::ACTION_REPORTED,
                ])
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($existing instanceof AuditLog) {
                $existingResponse = $existing->action === self::ACTION_CONFIRMED
                    ? self::RESPONSE_YES
                    : self::RESPONSE_NO;

                if ($existingResponse !== $response) {
                    throw new AuthApiException(
                        409,
                        'SECURITY_EVENT_ALREADY_REVIEWED',
                        'This security event has already been reviewed with a different response.'
                    );
                }

                return $this->result(
                    $event,
                    $existingResponse,
                    false,
                    true
                );
            }

            $targetDevice = null;

            if ($event->target_type === 'USER_DEVICE' && $event->target_id !== null) {
                $targetDevice = UserDevice::query()
                    ->whereKey($event->target_id)
                    ->where('user_id', $user->getKey())
                    ->lockForUpdate()
                    ->first();
            }

            if ($response === self::RESPONSE_NO) {
                if (! $targetDevice instanceof UserDevice) {
                    throw new AuthApiException(
                        409,
                        'SECURITY_EVENT_DEVICE_UNAVAILABLE',
                        'The device linked to this security event is no longer available.'
                    );
                }

                if ($targetDevice->getKey() === $currentDevice->getKey()) {
                    throw new AuthApiException(
                        409,
                        'CURRENT_DEVICE_SECURITY_REVIEW_REQUIRES_RECOVERY',
                        'This alert belongs to the device you are using now. Review it from another trusted device or use the account recovery process before revoking this device.'
                    );
                }

                $refreshCount = UserRefreshToken::query()
                    ->where('user_id', $user->getKey())
                    ->where('user_device_id', $targetDevice->getKey())
                    ->whereNull('revoked_at')
                    ->update([
                        'revoked_at' => now(),
                        'revoke_reason' => 'USER_REPORTED_UNAUTHORIZED_LOGIN',
                    ]);

                $accessCount = $this->accessTokens->revokeDevice(
                    $user,
                    $targetDevice
                );

                if ($targetDevice->is_active || $targetDevice->revoked_at === null) {
                    $targetDevice->forceFill([
                        'is_active' => false,
                        'is_trusted' => false,
                        'revoked_at' => now(),
                        'revoked_by_admin_id' => null,
                        'revoke_reason' => 'USER_REPORTED_UNAUTHORIZED_LOGIN',
                    ])->save();
                }

                AuditLog::record(
                    actorType: 'USER',
                    actorId: $user->getKey(),
                    action: self::ACTION_REPORTED,
                    module: 'SECURITY',
                    targetType: 'AUDIT_LOG',
                    targetId: $event->getKey(),
                    newValues: [
                        'response' => self::RESPONSE_NO,
                        'source_action' => $event->action,
                        'device_uuid' => $targetDevice->uuid,
                        'platform' => $targetDevice->platform,
                        'device_name' => $targetDevice->device_name,
                        'refresh_tokens_revoked' => $refreshCount,
                        'access_tokens_revoked' => $accessCount,
                    ],
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return $this->result(
                    $event,
                    self::RESPONSE_NO,
                    true,
                    false
                );
            }

            AuditLog::record(
                actorType: 'USER',
                actorId: $user->getKey(),
                action: self::ACTION_CONFIRMED,
                module: 'SECURITY',
                targetType: 'AUDIT_LOG',
                targetId: $event->getKey(),
                newValues: [
                    'response' => self::RESPONSE_YES,
                    'source_action' => $event->action,
                    'device_uuid' => $targetDevice?->uuid,
                    'platform' => $targetDevice?->platform,
                    'device_name' => $targetDevice?->device_name,
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            return $this->result(
                $event,
                self::RESPONSE_YES,
                false,
                false
            );
        });
    }

    private function result(
        AuditLog $event,
        string $response,
        bool $deviceRevoked,
        bool $alreadyReviewed
    ): array {
        return [
            'event_id' => (int) $event->getKey(),
            'response' => $response,
            'status' => $response === self::RESPONSE_YES
                ? 'CONFIRMED'
                : 'REPORTED_UNAUTHORIZED',
            'device_revoked' => $deviceRevoked,
            'already_reviewed' => $alreadyReviewed,
        ];
    }
}
