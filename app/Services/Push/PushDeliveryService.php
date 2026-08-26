<?php

namespace App\Services\Push;

use App\Models\NotificationMessage;
use App\Models\PushDeviceToken;
use App\Models\PushNotificationDelivery;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserNotificationReceipt;
use Illuminate\Support\Facades\DB;
use Throwable;

class PushDeliveryService
{
    public function __construct(
        private readonly PushProviderRouter $providers
    ) {
    }

    public function deliver(int $deliveryId, int $queueAttempt = 1): array
    {
        return DB::transaction(
            function () use ($deliveryId, $queueAttempt): array {
                $delivery = PushNotificationDelivery::query()
                    ->lockForUpdate()
                    ->findOrFail($deliveryId);

                if ($delivery->isTerminal()) {
                    return ['retry' => false, 'status' => $delivery->status];
                }

                $delivery->load([
                    'receipt.notification',
                    'receipt.user',
                ]);

                $token = $delivery->push_device_token_id !== null
                    ? PushDeviceToken::query()
                        ->with('userDevice')
                        ->lockForUpdate()
                        ->find($delivery->push_device_token_id)
                    : null;

                $attempt = max(
                    (int) $delivery->attempts + 1,
                    max(1, $queueAttempt)
                );

                $delivery->forceFill([
                    'status' => PushNotificationDelivery::STATUS_SENDING,
                    'attempts' => $attempt,
                    'next_attempt_at' => null,
                ])->save();

                $receipt = $delivery->receipt;

                if (
                    ! ($receipt instanceof UserNotificationReceipt)
                    || ! ($receipt->notification instanceof NotificationMessage)
                    || ! ($receipt->user instanceof User)
                    || ! ($token instanceof PushDeviceToken)
                    || ! ($token->userDevice instanceof UserDevice)
                ) {
                    return $this->skip($delivery, 'DELIVERY_CONTEXT_MISSING');
                }

                $message = $receipt->notification;
                $user = $receipt->user;
                $device = $token->userDevice;

                if (! $this->messageIsDeliverable($message)) {
                    return $this->skip($delivery, 'MESSAGE_NOT_DELIVERABLE');
                }

                if (
                    $user->status !== 'ACTIVE'
                    || ! $this->audienceStillMatches($message, $user)
                ) {
                    return $this->skip($delivery, 'USER_NO_LONGER_ELIGIBLE');
                }

                if (
                    ! $token->isUsable()
                    || (int) $delivery->user_id !== (int) $user->getKey()
                    || (int) $receipt->user_id !== (int) $user->getKey()
                    || (int) $token->user_id !== (int) $user->getKey()
                    || $delivery->provider !== $token->provider
                    || ! $this->providerMatchesPlatform(
                        $token->provider,
                        $device->platform
                    )
                    || $device->isRevoked()
                    || ! $device->is_trusted
                    || ! $device->isMobile()
                ) {
                    return $this->skip($delivery, 'DEVICE_NOT_ELIGIBLE');
                }

                if (! $this->providerEnabled($token->provider)) {
                    return $this->skip($delivery, 'PUSH_PROVIDER_DISABLED');
                }

                try {
                    $plainToken = $token->revealToken();
                } catch (Throwable) {
                    $token->forceFill([
                        'is_active' => false,
                        'invalidated_at' => now(),
                    ])->save();

                    return $this->invalid(
                        $delivery,
                        null,
                        'TOKEN_DECRYPTION_FAILED'
                    );
                }

                $result = $this->providers->send(
                    $token->provider,
                    $plainToken,
                    $message,
                    $receipt
                );

                return $this->applyProviderResult(
                    $delivery,
                    $token,
                    $result,
                    $attempt
                );
            },
            3
        );
    }

    private function applyProviderResult(
        PushNotificationDelivery $delivery,
        PushDeviceToken $token,
        PushProviderResult $result,
        int $attempt
    ): array {
        if ($result->kind === PushProviderResult::KIND_SENT) {
            $delivery->forceFill([
                'status' => PushNotificationDelivery::STATUS_SENT,
                'last_http_status' => $result->httpStatus,
                'provider_message_id' => $result->providerMessageId,
                'last_error_code' => null,
                'sent_at' => now(),
                'failed_at' => null,
                'next_attempt_at' => null,
            ])->save();

            return ['retry' => false, 'status' => $delivery->status];
        }

        if ($result->kind === PushProviderResult::KIND_INVALID_TOKEN) {
            $token->forceFill([
                'is_active' => false,
                'invalidated_at' => now(),
            ])->save();

            return $this->invalid(
                $delivery,
                $result->httpStatus,
                $result->code ?: 'INVALID_PUSH_TOKEN'
            );
        }

        if ($result->kind === PushProviderResult::KIND_TRANSIENT) {
            $maxAttempts = max(1, (int) config(
                'tradex_push.max_attempts',
                5
            ));

            if ($attempt >= $maxAttempts) {
                return $this->fail(
                    $delivery,
                    $result->httpStatus,
                    $result->code ?: 'PUSH_RETRY_EXHAUSTED'
                );
            }

            $delay = $this->retryDelay($attempt);

            $delivery->forceFill([
                'status' => PushNotificationDelivery::STATUS_RETRYING,
                'last_http_status' => $result->httpStatus,
                'last_error_code' => $this->safeErrorCode(
                    $result->code ?: 'PUSH_TRANSIENT_FAILURE'
                ),
                'next_attempt_at' => now()->addSeconds($delay),
            ])->save();

            return [
                'retry' => true,
                'status' => $delivery->status,
                'retry_after_seconds' => $delay,
            ];
        }

        return $this->fail(
            $delivery,
            $result->httpStatus,
            $result->code ?: 'PUSH_PERMANENT_FAILURE'
        );
    }

    private function messageIsDeliverable(NotificationMessage $message): bool
    {
        return $message->is_active
            && $message->published_at !== null
            && $message->published_at->lessThanOrEqualTo(now())
            && (
                $message->expires_at === null
                || $message->expires_at->greaterThan(now())
            );
    }

    private function audienceStillMatches(
        NotificationMessage $message,
        User $user
    ): bool {
        return match ($message->audience_access_level) {
            NotificationMessage::AUDIENCE_ALL => true,
            NotificationMessage::AUDIENCE_FREE =>
                $user->account_type === 'FREE',
            NotificationMessage::AUDIENCE_PREMIUM =>
                $user->account_type === 'PREMIUM',
            default => false,
        };
    }

    private function providerEnabled(string $provider): bool
    {
        if (! (bool) config('tradex_push.enabled', false)) {
            return false;
        }

        return match ($provider) {
            PushDeviceToken::PROVIDER_FCM =>
                (bool) config('tradex_push.fcm.enabled', false),
            PushDeviceToken::PROVIDER_APNS =>
                (bool) config('tradex_push.apns.enabled', false),
            default => false,
        };
    }

    private function providerMatchesPlatform(
        string $provider,
        string $platform
    ): bool {
        return match ($provider) {
            PushDeviceToken::PROVIDER_FCM =>
                $platform === UserDevice::PLATFORM_ANDROID,
            PushDeviceToken::PROVIDER_APNS =>
                $platform === UserDevice::PLATFORM_IOS,
            default => false,
        };
    }

    private function retryDelay(int $attempt): int
    {
        $delays = array_values((array) config(
            'tradex_push.retry_backoff_seconds',
            [60, 300, 900, 3600]
        ));

        if ($delays === []) {
            return 60;
        }

        $index = min(max(0, $attempt - 1), count($delays) - 1);

        return max(1, (int) $delays[$index]);
    }

    private function skip(
        PushNotificationDelivery $delivery,
        string $code
    ): array {
        $delivery->forceFill([
            'status' => PushNotificationDelivery::STATUS_SKIPPED,
            'last_error_code' => $this->safeErrorCode($code),
            'failed_at' => now(),
            'next_attempt_at' => null,
        ])->save();

        return ['retry' => false, 'status' => $delivery->status];
    }

    private function invalid(
        PushNotificationDelivery $delivery,
        ?int $httpStatus,
        string $code
    ): array {
        $delivery->forceFill([
            'status' => PushNotificationDelivery::STATUS_INVALID_TOKEN,
            'last_http_status' => $httpStatus,
            'last_error_code' => $this->safeErrorCode($code),
            'failed_at' => now(),
            'next_attempt_at' => null,
        ])->save();

        return ['retry' => false, 'status' => $delivery->status];
    }

    private function fail(
        PushNotificationDelivery $delivery,
        ?int $httpStatus,
        string $code
    ): array {
        $delivery->forceFill([
            'status' => PushNotificationDelivery::STATUS_FAILED,
            'last_http_status' => $httpStatus,
            'last_error_code' => $this->safeErrorCode($code),
            'failed_at' => now(),
            'next_attempt_at' => null,
        ])->save();

        return ['retry' => false, 'status' => $delivery->status];
    }

    private function safeErrorCode(string $code): string
    {
        $value = strtoupper(
            preg_replace('/[^A-Za-z0-9_]+/', '_', $code) ?? 'UNKNOWN'
        );

        return mb_substr(trim($value, '_') ?: 'UNKNOWN', 0, 100);
    }
}
