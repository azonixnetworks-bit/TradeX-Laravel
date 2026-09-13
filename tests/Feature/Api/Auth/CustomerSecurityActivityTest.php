<?php

namespace Tests\Feature\Api\Auth;

use App\Models\AuditLog;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class CustomerSecurityActivityTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_customer_security_history_returns_safe_curated_events_only(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $device = $user->devices()->firstOrFail();

        AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: 'USER_SUSPICIOUS_LOGIN_DETECTED',
            module: 'SECURITY',
            targetType: 'USER_DEVICE',
            targetId: $device->getKey(),
            newValues: [
                'risk_level' => 'SUSPICIOUS',
                'risk_score' => 85,
                'signals' => ['NEW_DEVICE', 'NEW_IP', 'NEW_PLATFORM'],
                'device_uuid' => $device->uuid,
                'platform' => $device->platform,
                'device_name' => 'Security Phone',
                'device_model' => 'Model X',
                'session_uuid' => 'DO-NOT-EXPOSE-SESSION',
                'installation_id_hash' => 'DO-NOT-EXPOSE-INSTALLATION',
                'totp_secret' => 'DO-NOT-EXPOSE-TOTP',
            ],
            ipAddress: '203.0.113.40',
            userAgent: 'SECRET-USER-AGENT'
        );

        $response = $this
            ->withToken($token)
            ->getJson('/api/v1/auth/security/history')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.events.0.action', 'USER_SUSPICIOUS_LOGIN_DETECTED')
            ->assertJsonPath('data.events.0.severity', 'HIGH')
            ->assertJsonPath('data.events.0.risk.level', 'SUSPICIOUS')
            ->assertJsonPath('data.events.0.risk.score', 85)
            ->assertJsonPath('data.events.0.device.uuid', $device->uuid)
            ->assertJsonPath('data.events.0.ip_address', '203.0.113.40');

        $json = (string) $response->getContent();

        foreach ([
            'DO-NOT-EXPOSE-SESSION',
            'DO-NOT-EXPOSE-INSTALLATION',
            'DO-NOT-EXPOSE-TOTP',
            'SECRET-USER-AGENT',
            'session_uuid',
            'installation_id_hash',
            'totp_secret',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json);
        }
    }

    public function test_notification_feed_excludes_routine_login_but_keeps_security_alerts(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $device = $user->devices()->firstOrFail();

        AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: 'API_LOGIN_SUCCESS',
            module: 'AUTH',
            targetType: 'USER_DEVICE',
            targetId: $device->getKey(),
            newValues: ['device_uuid' => $device->uuid]
        );

        AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: 'USER_NEW_TRUSTED_DEVICE_ADDED',
            module: 'SECURITY',
            targetType: 'USER_DEVICE',
            targetId: $device->getKey(),
            newValues: [
                'device_uuid' => $device->uuid,
                'platform' => $device->platform,
                'device_name' => 'New Phone',
            ]
        );

        $response = $this
            ->withToken($token)
            ->getJson('/api/v1/auth/security/notifications')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.read_tracking', false)
            ->assertJsonCount(1, 'data.notifications')
            ->assertJsonPath(
                'data.notifications.0.action',
                'USER_NEW_TRUSTED_DEVICE_ADDED'
            )
            ->assertJsonPath(
                'data.notifications.0.title',
                'New trusted device'
            );

        $this->assertStringNotContainsString(
            'API_LOGIN_SUCCESS',
            (string) $response->getContent()
        );
    }

    public function test_customer_cannot_see_another_users_security_events(): void
    {
        $owner = $this->createApiUser();
        $ownerToken = $this->issueBoundToken($owner);

        $other = $this->createApiUser();
        $this->issueBoundToken($other);
        $otherDevice = $other->devices()->firstOrFail();

        AuditLog::record(
            actorType: 'USER',
            actorId: $other->getKey(),
            action: 'USER_SUSPICIOUS_LOGIN_DETECTED',
            module: 'SECURITY',
            targetType: 'USER_DEVICE',
            targetId: $otherDevice->getKey(),
            newValues: [
                'risk_level' => 'SUSPICIOUS',
                'risk_score' => 90,
                'device_uuid' => $otherDevice->uuid,
                'device_name' => 'OTHER-USER-SECRET-DEVICE',
            ]
        );

        $response = $this
            ->withToken($ownerToken)
            ->getJson('/api/v1/auth/security/history')
            ->assertOk();

        $this->assertStringNotContainsString(
            'OTHER-USER-SECRET-DEVICE',
            (string) $response->getContent()
        );
    }

    public function test_customer_sees_admin_force_revoke_event_for_own_device(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $device = $user->devices()->firstOrFail();

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: 999,
            action: 'CUSTOMER_DEVICE_FORCE_REVOKED',
            module: 'SECURITY',
            targetType: 'USER_DEVICE',
            targetId: $device->getKey(),
            newValues: [
                'device_uuid' => $device->uuid,
                'platform' => $device->platform,
                'device_name' => 'Revoked Phone',
            ]
        );

        $this
            ->withToken($token)
            ->getJson('/api/v1/auth/security/notifications')
            ->assertOk()
            ->assertJsonPath(
                'data.notifications.0.action',
                'CUSTOMER_DEVICE_FORCE_REVOKED'
            )
            ->assertJsonPath('data.notifications.0.severity', 'HIGH');
    }

    public function test_security_activity_limit_is_bounded(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $device = $user->devices()->firstOrFail();

        for ($index = 1; $index <= 55; $index++) {
            AuditLog::record(
                actorType: 'USER',
                actorId: $user->getKey(),
                action: 'API_LOGIN_SUCCESS',
                module: 'AUTH',
                targetType: 'USER_DEVICE',
                targetId: $device->getKey(),
                newValues: ['device_uuid' => $device->uuid]
            );
        }

        $this
            ->withToken($token)
            ->getJson('/api/v1/auth/security/history?limit=500')
            ->assertOk()
            ->assertJsonCount(50, 'data.events');
    }
}
