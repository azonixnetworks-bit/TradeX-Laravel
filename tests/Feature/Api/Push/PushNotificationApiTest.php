<?php

namespace Tests\Feature\Api\Push;

use App\Jobs\SendPushDeliveryJob;
use App\Models\NotificationMessage;
use App\Models\PushDeviceToken;
use App\Models\PushNotificationDelivery;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserNotificationReceipt;
use App\Services\Push\ApnsJwtProvider;
use App\Services\Push\ApnsPushProvider;
use App\Services\Push\FcmAccessTokenProvider;
use App\Services\Push\PushDeliveryService;
use App\Services\Push\PushFanoutService;
use App\Services\Push\PushProviderResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use Mockery\MockInterface;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class PushNotificationApiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_push_device_routes_require_authentication(): void
    {
        $this->getJson('/api/v1/push/device-token')
            ->assertUnauthorized();

        $this->putJson('/api/v1/push/device-token', [
            'token' => $this->fcmToken(),
        ])->assertUnauthorized();

        $this->deleteJson('/api/v1/push/device-token')
            ->assertUnauthorized();
    }

    public function test_android_trusted_device_can_register_fcm_token_idempotently(): void
    {
        $user = $this->createApiUser();
        $accessToken = $this->issueBoundToken($user);
        $rawToken = $this->fcmToken();

        $this->withToken($accessToken)
            ->putJson('/api/v1/push/device-token', [
                'token' => $rawToken,
            ])
            ->assertCreated()
            ->assertJsonPath('data.created', true)
            ->assertJsonPath('data.registered', true)
            ->assertJsonPath('data.provider', 'FCM')
            ->assertJsonPath('data.platform', 'ANDROID');

        $stored = PushDeviceToken::query()->firstOrFail();

        $this->assertNotSame($rawToken, $stored->token_ciphertext);
        $this->assertNotSame($rawToken, $stored->token_hash);
        $this->assertSame($rawToken, $stored->revealToken());

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'USER',
            'actor_id' => $user->getKey(),
            'action' => 'PUSH_DEVICE_TOKEN_REGISTERED',
            'module' => 'PUSH_NOTIFICATIONS',
        ]);

        $this->withToken($accessToken)
            ->putJson('/api/v1/push/device-token', [
                'token' => $rawToken,
            ])
            ->assertOk()
            ->assertJsonPath('data.created', false);

        $this->assertDatabaseCount('push_device_tokens', 1);

        $this->withToken($accessToken)
            ->getJson('/api/v1/push/device-token')
            ->assertOk()
            ->assertJsonPath('data.registered', true)
            ->assertJsonPath('data.provider', 'FCM');
    }

    public function test_ios_trusted_device_infers_apns_and_normalizes_hex_token(): void
    {
        $user = $this->createApiUser();
        $accessToken = $this->issueBoundToken($user);

        UserDevice::query()
            ->where('user_id', $user->getKey())
            ->update(['platform' => UserDevice::PLATFORM_IOS]);

        $normalized = str_repeat('ab', 32);
        $formatted = '<'.implode(
            ' ',
            str_split($normalized, 8)
        ).'>';

        $this->withToken($accessToken)
            ->putJson('/api/v1/push/device-token', [
                'token' => $formatted,
            ])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'APNS')
            ->assertJsonPath('data.platform', 'IOS');

        $stored = PushDeviceToken::query()->firstOrFail();
        $this->assertSame($normalized, $stored->revealToken());
    }

    public function test_unsupported_mobile_platform_is_rejected(): void
    {
        $user = $this->createApiUser();
        $accessToken = $this->issueBoundToken($user);

        UserDevice::query()
            ->where('user_id', $user->getKey())
            ->update(['platform' => UserDevice::PLATFORM_WINDOWS]);

        $this->withToken($accessToken)
            ->putJson('/api/v1/push/device-token', [
                'token' => $this->fcmToken(),
            ])
            ->assertUnprocessable()
            ->assertJsonPath('error.code', 'PUSH_PLATFORM_UNSUPPORTED');
    }

    public function test_unregister_deactivates_current_device_push_token(): void
    {
        $user = $this->createApiUser();
        $accessToken = $this->issueBoundToken($user);

        $this->withToken($accessToken)
            ->putJson('/api/v1/push/device-token', [
                'token' => $this->fcmToken(),
            ])
            ->assertCreated();

        $this->withToken($accessToken)
            ->deleteJson('/api/v1/push/device-token')
            ->assertOk()
            ->assertJsonPath('data.registered', false)
            ->assertJsonPath('data.revoked_token_count', 1);

        $stored = PushDeviceToken::query()->firstOrFail();
        $this->assertFalse($stored->fresh()->is_active);
        $this->assertNotNull($stored->fresh()->revoked_at);
    }

    public function test_same_provider_token_is_reassigned_to_latest_proving_device(): void
    {
        $firstUser = $this->createApiUser();
        $firstAccess = $this->issueBoundToken($firstUser);

        $secondUser = $this->createApiUser();
        $secondAccess = $this->issueBoundToken($secondUser);

        $sharedToken = $this->fcmToken('shared');

        $this->withToken($firstAccess)
            ->putJson('/api/v1/push/device-token', [
                'token' => $sharedToken,
            ])
            ->assertCreated();

        $this->withToken($secondAccess)
            ->putJson('/api/v1/push/device-token', [
                'token' => $sharedToken,
            ])
            ->assertOk();

        $this->assertDatabaseCount('push_device_tokens', 1);

        $stored = PushDeviceToken::query()->firstOrFail();
        $this->assertSame($secondUser->getKey(), $stored->user_id);

        $this->withToken($firstAccess)
            ->getJson('/api/v1/push/device-token')
            ->assertOk()
            ->assertJsonPath('data.registered', false);
    }

    public function test_sweep_creates_delivery_only_for_due_current_receipt(): void
    {
        Queue::fake();
        config()->set('tradex_push.enabled', true);
        config()->set('tradex_push.fcm.enabled', true);

        $user = $this->createApiUser();
        $accessToken = $this->issueBoundToken($user);

        $this->withToken($accessToken)
            ->putJson('/api/v1/push/device-token', [
                'token' => $this->fcmToken(),
            ])
            ->assertCreated();

        PushDeviceToken::query()->update([
            'registered_at' => now()->subMinutes(2),
        ]);

        [, $receipt] = $this->notificationReceipt(
            $user,
            now()->subMinute()
        );

        $result = app(PushFanoutService::class)->sweep();

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['queued']);

        $this->assertDatabaseHas('push_notification_deliveries', [
            'user_notification_receipt_id' => $receipt->getKey(),
            'status' => PushNotificationDelivery::STATUS_QUEUED,
        ]);

        Queue::assertPushed(
            SendPushDeliveryJob::class,
            fn (SendPushDeliveryJob $job): bool =>
                $job->deliveryId
                    === PushNotificationDelivery::query()->value('id')
        );
    }

    public function test_sweep_does_not_backfill_old_notification_to_new_token(): void
    {
        Queue::fake();
        config()->set('tradex_push.enabled', true);
        config()->set('tradex_push.fcm.enabled', true);

        $user = $this->createApiUser();
        $accessToken = $this->issueBoundToken($user);

        [, $receipt] = $this->notificationReceipt(
            $user,
            now()->subMinutes(5)
        );

        $this->withToken($accessToken)
            ->putJson('/api/v1/push/device-token', [
                'token' => $this->fcmToken(),
            ])
            ->assertCreated();

        PushDeviceToken::query()->update([
            'registered_at' => now()->subMinute(),
        ]);

        $result = app(PushFanoutService::class)->sweep();

        $this->assertSame(0, $result['created']);
        $this->assertDatabaseMissing('push_notification_deliveries', [
            'user_notification_receipt_id' => $receipt->getKey(),
        ]);
        Queue::assertNothingPushed();
    }

    public function test_fcm_delivery_is_privacy_safe_and_marks_sent(): void
    {
        config()->set('tradex_push.enabled', true);
        config()->set('tradex_push.fcm.enabled', true);
        config()->set('tradex_push.fcm.project_id', 'tradex-test');

        $this->mock(
            FcmAccessTokenProvider::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('token')
                    ->once()
                    ->andReturn('oauth-test-token');
            }
        );

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/tradex-test/messages:send' =>
                Http::response([
                    'name' => 'projects/tradex-test/messages/abc123',
                ], 200),
        ]);

        [$delivery, $message, $receipt] = $this->deliveryFixture(
            'Sensitive payment content must not leave API-10.'
        );

        $result = app(PushDeliveryService::class)->deliver(
            $delivery->getKey(),
            1
        );

        $this->assertFalse($result['retry']);
        $this->assertSame(
            PushNotificationDelivery::STATUS_SENT,
            $delivery->fresh()->status
        );

        Http::assertSent(function (HttpRequest $request) use (
            $message,
            $receipt
        ): bool {
            $payload = $request->data();
            $encoded = json_encode($payload);

            return $request->hasHeader(
                'Authorization',
                'Bearer oauth-test-token'
            )
                && data_get($payload, 'message.data.receipt_uuid')
                    === $receipt->uuid
                && data_get($payload, 'message.notification.body')
                    === 'You have a new notification.'
                && is_string($encoded)
                && ! str_contains($encoded, $message->body)
                && ! str_contains($encoded, $message->title);
        });
    }

    public function test_fcm_unregistered_response_invalidates_token(): void
    {
        config()->set('tradex_push.enabled', true);
        config()->set('tradex_push.fcm.enabled', true);
        config()->set('tradex_push.fcm.project_id', 'tradex-test');

        $this->mock(
            FcmAccessTokenProvider::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('token')
                    ->once()
                    ->andReturn('oauth-test-token');
            }
        );

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/tradex-test/messages:send' =>
                Http::response([
                    'error' => [
                        'details' => [
                            ['errorCode' => 'UNREGISTERED'],
                        ],
                    ],
                ], 404),
        ]);

        [$delivery] = $this->deliveryFixture();

        app(PushDeliveryService::class)->deliver(
            $delivery->getKey(),
            1
        );

        $this->assertSame(
            PushNotificationDelivery::STATUS_INVALID_TOKEN,
            $delivery->fresh()->status
        );

        $token = PushDeviceToken::query()->firstOrFail()->fresh();
        $this->assertFalse($token->is_active);
        $this->assertNotNull($token->invalidated_at);
    }

    public function test_transient_fcm_failure_is_marked_for_queue_retry(): void
    {
        config()->set('tradex_push.enabled', true);
        config()->set('tradex_push.fcm.enabled', true);
        config()->set('tradex_push.fcm.project_id', 'tradex-test');

        $this->mock(
            FcmAccessTokenProvider::class,
            function (MockInterface $mock): void {
                $mock->shouldReceive('token')
                    ->once()
                    ->andReturn('oauth-test-token');
            }
        );

        Http::fake([
            'https://fcm.googleapis.com/v1/projects/tradex-test/messages:send' =>
                Http::response([], 503),
        ]);

        [$delivery] = $this->deliveryFixture();

        $result = app(PushDeliveryService::class)->deliver(
            $delivery->getKey(),
            1
        );

        $this->assertTrue($result['retry']);
        $this->assertSame(
            PushNotificationDelivery::STATUS_RETRYING,
            $delivery->fresh()->status
        );
        $this->assertNotNull($delivery->fresh()->next_attempt_at);
    }

    public function test_apns_provider_uses_http2_contract_and_privacy_safe_payload(): void
    {
        config()->set('tradex_push.apns.bundle_id', 'com.tradex.mobile');
        config()->set('tradex_push.apns.environment', 'sandbox');

        $jwt = Mockery::mock(ApnsJwtProvider::class);
        $jwt->shouldReceive('token')
            ->once()
            ->with(false)
            ->andReturn('apns-jwt');

        Http::fake([
            'https://api.sandbox.push.apple.com/3/device/*' =>
                Http::response([], 200, [
                    'apns-id' => 'apns-message-id',
                ]),
        ]);

        $user = $this->createApiUser();
        [$message, $receipt] = $this->notificationReceipt(
            $user,
            now()->subMinute(),
            'Private security notice must stay in authenticated API.'
        );

        $provider = new ApnsPushProvider($jwt);
        $result = $provider->send(
            str_repeat('ab', 32),
            $message,
            $receipt
        );

        $this->assertSame(PushProviderResult::KIND_SENT, $result->kind);
        $this->assertSame('apns-message-id', $result->providerMessageId);

        Http::assertSent(function (HttpRequest $request) use ($message): bool {
            $encoded = json_encode($request->data());

            return $request->hasHeader('apns-topic', 'com.tradex.mobile')
                && $request->hasHeader('apns-push-type', 'alert')
                && $request->hasHeader('Authorization', 'bearer apns-jwt')
                && data_get($request->data(), 'aps.alert.body')
                    === 'You have a new notification.'
                && is_string($encoded)
                && ! str_contains($encoded, $message->body)
                && ! str_contains($encoded, $message->title);
        });
    }

    private function fcmToken(string $suffix = 'device'): string
    {
        return 'fcm-'.$suffix.'-'.Str::random(80).':'.Str::random(40);
    }

    private function notificationReceipt(
        User $user,
        $publishedAt,
        string $body = 'Notification body'
    ): array {
        $message = NotificationMessage::query()->create([
            'audience_access_level' => NotificationMessage::AUDIENCE_ALL,
            'category' => NotificationMessage::CATEGORY_SYSTEM,
            'priority' => NotificationMessage::PRIORITY_HIGH,
            'title' => 'Sensitive notification title',
            'body' => $body,
            'action_type' => NotificationMessage::ACTION_DASHBOARD,
            'is_active' => true,
            'published_at' => $publishedAt,
        ]);

        $receipt = UserNotificationReceipt::query()->create([
            'notification_message_id' => $message->getKey(),
            'user_id' => $user->getKey(),
        ]);

        return [$message, $receipt];
    }

    private function deliveryFixture(
        string $body = 'Notification body'
    ): array {
        $user = $this->createApiUser();
        $accessToken = $this->issueBoundToken($user);
        $device = UserDevice::query()
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        $plainToken = $this->fcmToken();

        $pushToken = PushDeviceToken::query()->create([
            'user_id' => $user->getKey(),
            'user_device_id' => $device->getKey(),
            'provider' => PushDeviceToken::PROVIDER_FCM,
            'platform' => UserDevice::PLATFORM_ANDROID,
            'token_hash' => hash('sha256', $plainToken),
            'token_ciphertext' => Crypt::encryptString($plainToken),
            'is_active' => true,
            'registered_at' => now()->subMinutes(2),
            'last_seen_at' => now(),
        ]);

        [$message, $receipt] = $this->notificationReceipt(
            $user,
            now()->subMinute(),
            $body
        );

        $delivery = PushNotificationDelivery::query()->create([
            'user_notification_receipt_id' => $receipt->getKey(),
            'push_device_token_id' => $pushToken->getKey(),
            'user_id' => $user->getKey(),
            'provider' => PushDeviceToken::PROVIDER_FCM,
            'status' => PushNotificationDelivery::STATUS_QUEUED,
            'queued_at' => now(),
        ]);

        return [$delivery, $message, $receipt, $accessToken];
    }
}
