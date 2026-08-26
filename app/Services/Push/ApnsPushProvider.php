<?php

namespace App\Services\Push;

use App\Models\NotificationMessage;
use App\Models\UserNotificationReceipt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

class ApnsPushProvider
{
    public function __construct(
        private readonly ApnsJwtProvider $jwtProvider
    ) {
    }

    public function send(
        string $deviceToken,
        NotificationMessage $message,
        UserNotificationReceipt $receipt
    ): PushProviderResult {
        $bundleId = trim((string) config(
            'tradex_push.apns.bundle_id',
            ''
        ));

        if ($bundleId === '') {
            return PushProviderResult::permanent(
                null,
                'APNS_BUNDLE_ID_NOT_CONFIGURED'
            );
        }

        try {
            $response = $this->request(
                $deviceToken,
                $message,
                $receipt,
                $bundleId,
                false
            );

            if (
                $response->status() === 403
                && $response->json('reason') === 'ExpiredProviderToken'
            ) {
                $response = $this->request(
                    $deviceToken,
                    $message,
                    $receipt,
                    $bundleId,
                    true
                );
            }
        } catch (ConnectionException) {
            return PushProviderResult::transient(
                null,
                'APNS_NETWORK_ERROR'
            );
        } catch (Throwable) {
            return PushProviderResult::permanent(
                null,
                'APNS_AUTH_CONFIGURATION'
            );
        }

        if ($response->successful()) {
            return PushProviderResult::sent(
                $response->status(),
                $response->header('apns-id')
            );
        }

        $reason = trim((string) $response->json('reason', ''));
        $code = $this->safeCode($reason !== '' ? $reason : 'HTTP_'.$response->status());

        if (
            $response->status() === 410
            || in_array(
                $reason,
                ['BadDeviceToken', 'DeviceTokenNotForTopic', 'Unregistered'],
                true
            )
        ) {
            return PushProviderResult::invalidToken(
                $response->status(),
                'APNS_'.$code
            );
        }

        if (in_array($response->status(), [429, 500, 502, 503, 504], true)) {
            return PushProviderResult::transient(
                $response->status(),
                'APNS_'.$code
            );
        }

        return PushProviderResult::permanent(
            $response->status(),
            'APNS_'.$code
        );
    }

    private function request(
        string $deviceToken,
        NotificationMessage $message,
        UserNotificationReceipt $receipt,
        string $bundleId,
        bool $forceRefresh
    ): Response {
        $jwt = $this->jwtProvider->token($forceRefresh);
        $environment = strtolower((string) config(
            'tradex_push.apns.environment',
            'production'
        ));

        $host = $environment === 'sandbox'
            ? (string) config('tradex_push.apns.sandbox_host')
            : (string) config('tradex_push.apns.production_host');

        return Http::withHeaders([
            'authorization' => 'bearer '.$jwt,
            'apns-topic' => $bundleId,
            'apns-push-type' => 'alert',
            'apns-priority' => '10',
            'apns-collapse-id' => (string) $message->uuid,
        ])
            ->withOptions(['version' => 2.0])
            ->acceptJson()
            ->timeout((int) config(
                'tradex_push.apns.timeout_seconds',
                10
            ))
            ->post(
                rtrim($host, '/').'/3/device/'.rawurlencode($deviceToken),
                [
                    'aps' => [
                        'alert' => [
                            'title' => (string) config(
                                'tradex_push.privacy_safe_title',
                                'TradeX OS'
                            ),
                            'body' => (string) config(
                                'tradex_push.privacy_safe_body',
                                'You have a new notification.'
                            ),
                        ],
                        'sound' => 'default',
                        'thread-id' => (string) $message->category,
                    ],
                    'tradex' => [
                        'type' => 'TRADEX_NOTIFICATION',
                        'receipt_uuid' => (string) $receipt->uuid,
                        'notification_uuid' => (string) $message->uuid,
                        'category' => (string) $message->category,
                        'priority' => (string) $message->priority,
                    ],
                ]
            );
    }

    private function safeCode(string $value): string
    {
        $normalized = strtoupper(
            preg_replace('/[^A-Za-z0-9_]+/', '_', $value) ?? 'UNKNOWN'
        );

        return trim($normalized, '_') ?: 'UNKNOWN';
    }
}
