<?php

namespace App\Services\Push;

use App\Models\NotificationMessage;
use App\Models\PushDeviceToken;
use App\Models\UserNotificationReceipt;

class PushProviderRouter
{
    public function __construct(
        private readonly FcmPushProvider $fcm,
        private readonly ApnsPushProvider $apns
    ) {
    }

    public function send(
        string $provider,
        string $deviceToken,
        NotificationMessage $message,
        UserNotificationReceipt $receipt
    ): PushProviderResult {
        return match ($provider) {
            PushDeviceToken::PROVIDER_FCM =>
                $this->fcm->send($deviceToken, $message, $receipt),
            PushDeviceToken::PROVIDER_APNS =>
                $this->apns->send($deviceToken, $message, $receipt),
            default => PushProviderResult::permanent(
                null,
                'PUSH_PROVIDER_UNSUPPORTED'
            ),
        };
    }
}
