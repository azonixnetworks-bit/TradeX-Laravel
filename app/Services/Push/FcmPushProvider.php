<?php

namespace App\Services\Push;

use App\Models\NotificationMessage;
use App\Models\UserNotificationReceipt;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

class FcmPushProvider
{
    public function __construct(
        private readonly FcmAccessTokenProvider $accessTokenProvider
    ) {
    }

    public function send(
        string $deviceToken,
        NotificationMessage $message,
        UserNotificationReceipt $receipt
    ): PushProviderResult {
        $projectId = trim((string) config(
            'tradex_push.fcm.project_id',
            ''
        ));

        if ($projectId === '') {
            return PushProviderResult::permanent(
                null,
                'FCM_PROJECT_NOT_CONFIGURED'
            );
        }

        try {
            $accessToken = $this->accessTokenProvider->token();
        } catch (Throwable) {
            return PushProviderResult::transient(
                null,
                'FCM_AUTH_UNAVAILABLE'
            );
        }

        $endpoint = sprintf(
            (string) config('tradex_push.fcm.send_endpoint'),
            rawurlencode($projectId)
        );

        try {
            $response = Http::withToken($accessToken)
                ->acceptJson()
                ->timeout((int) config(
                    'tradex_push.fcm.timeout_seconds',
                    10
                ))
                ->post($endpoint, [
                    'message' => [
                        'token' => $deviceToken,
                        'notification' => [
                            'title' => (string) config(
                                'tradex_push.privacy_safe_title',
                                'TradeX OS'
                            ),
                            'body' => (string) config(
                                'tradex_push.privacy_safe_body',
                                'You have a new notification.'
                            ),
                        ],
                        'data' => [
                            'type' => 'TRADEX_NOTIFICATION',
                            'receipt_uuid' => (string) $receipt->uuid,
                            'notification_uuid' => (string) $message->uuid,
                            'category' => (string) $message->category,
                            'priority' => (string) $message->priority,
                        ],
                        'android' => [
                            'priority' =>
                                $message->priority
                                    === NotificationMessage::PRIORITY_NORMAL
                                        ? 'normal'
                                        : 'high',
                        ],
                    ],
                ]);
        } catch (ConnectionException) {
            return PushProviderResult::transient(
                null,
                'FCM_NETWORK_ERROR'
            );
        }

        if ($response->successful()) {
            return PushProviderResult::sent(
                $response->status(),
                $response->json('name')
            );
        }

        $code = $this->fcmErrorCode((array) $response->json());

        if ($code === 'UNREGISTERED') {
            return PushProviderResult::invalidToken(
                $response->status(),
                'FCM_UNREGISTERED'
            );
        }

        if (in_array($response->status(), [429, 500, 502, 503, 504], true)) {
            return PushProviderResult::transient(
                $response->status(),
                'FCM_'.($code ?: 'TEMPORARY_FAILURE')
            );
        }

        return PushProviderResult::permanent(
            $response->status(),
            'FCM_'.($code ?: 'HTTP_'.$response->status())
        );
    }

    private function fcmErrorCode(array $payload): ?string
    {
        $details = $payload['error']['details'] ?? [];

        if (! is_array($details)) {
            return null;
        }

        foreach ($details as $detail) {
            if (is_array($detail) && filled($detail['errorCode'] ?? null)) {
                return strtoupper((string) $detail['errorCode']);
            }
        }

        return null;
    }
}
