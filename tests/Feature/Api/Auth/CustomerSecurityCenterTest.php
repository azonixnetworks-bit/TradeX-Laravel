<?php

namespace Tests\Feature\Api\Auth;

use App\Models\AuditLog;
use App\Services\Auth\CustomerSecurityEventReviewService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class CustomerSecurityCenterTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_security_center_returns_mobile_ready_summary_and_current_device(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $device = $user->devices()->firstOrFail();

        $response = $this
            ->withToken($token)
            ->getJson('/api/v1/auth/security/center')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.summary.trusted_devices', 1)
            ->assertJsonPath('data.summary.max_trusted_devices', 2)
            ->assertJsonPath('data.summary.pending_review_count', 0)
            ->assertJsonPath('data.summary.has_pending_review', false)
            ->assertJsonPath('data.banner.visible', false)
            ->assertJsonPath('data.current_device.uuid', $device->uuid)
            ->assertJsonPath('data.current_device.is_current', true)
            ->assertJsonCount(1, 'data.devices');

        $json = (string) $response->getContent();

        foreach ([
            'installation_id_hash',
            'device_public_key',
            'access_token',
            'refresh_token',
            'session_uuid',
            'totp_secret',
        ] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $json);
        }
    }

    public function test_security_center_surfaces_pending_suspicious_login_as_urgent_banner(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $device = $user->devices()->firstOrFail();

        $risk = AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: 'USER_SUSPICIOUS_LOGIN_DETECTED',
            module: 'SECURITY',
            targetType: 'USER_DEVICE',
            targetId: $device->getKey(),
            newValues: [
                'risk_level' => 'SUSPICIOUS',
                'risk_score' => 80,
                'signals' => ['NEW_DEVICE', 'NEW_IP'],
                'device_uuid' => $device->uuid,
                'platform' => $device->platform,
                'device_name' => 'Risk Phone',
            ]
        );

        $this
            ->withToken($token)
            ->getJson('/api/v1/auth/security/center')
            ->assertOk()
            ->assertJsonPath('data.summary.pending_review_count', 1)
            ->assertJsonPath('data.summary.has_pending_review', true)
            ->assertJsonPath('data.summary.highest_pending_severity', 'HIGH')
            ->assertJsonPath('data.banner.visible', true)
            ->assertJsonPath('data.banner.severity', 'HIGH')
            ->assertJsonPath('data.banner.event_id', $risk->getKey())
            ->assertJsonPath('data.banner.action', 'REVIEW_SECURITY_EVENT')
            ->assertJsonPath('data.notifications.0.review.status', 'PENDING')
            ->assertJsonPath('data.notifications.0.review.can_respond', true);
    }

    public function test_reviewed_security_event_disappears_from_pending_summary_and_banner(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $device = $user->devices()->firstOrFail();

        $risk = AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: 'USER_LOGIN_RISK_DETECTED',
            module: 'SECURITY',
            targetType: 'USER_DEVICE',
            targetId: $device->getKey(),
            newValues: [
                'risk_level' => 'REVIEW',
                'risk_score' => 35,
                'signals' => ['NEW_DEVICE'],
                'device_uuid' => $device->uuid,
                'platform' => $device->platform,
                'device_name' => 'Reviewed Phone',
            ]
        );

        AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: CustomerSecurityEventReviewService::ACTION_CONFIRMED,
            module: 'SECURITY',
            targetType: 'AUDIT_LOG',
            targetId: $risk->getKey(),
            newValues: [
                'response' => CustomerSecurityEventReviewService::RESPONSE_YES,
            ]
        );

        $this
            ->withToken($token)
            ->getJson('/api/v1/auth/security/center')
            ->assertOk()
            ->assertJsonPath('data.summary.pending_review_count', 0)
            ->assertJsonPath('data.summary.has_pending_review', false)
            ->assertJsonPath('data.summary.highest_pending_severity', null)
            ->assertJsonPath('data.banner.visible', false)
            ->assertJsonPath('data.notifications.0.review.status', 'CONFIRMED')
            ->assertJsonPath('data.notifications.0.review.can_respond', false);
    }

    public function test_security_center_pending_count_is_not_limited_to_preview_size(): void
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $device = $user->devices()->firstOrFail();

        for ($index = 1; $index <= 12; $index++) {
            AuditLog::record(
                actorType: 'USER',
                actorId: $user->getKey(),
                action: 'USER_LOGIN_RISK_DETECTED',
                module: 'SECURITY',
                targetType: 'USER_DEVICE',
                targetId: $device->getKey(),
                newValues: [
                    'risk_level' => 'REVIEW',
                    'risk_score' => 30,
                    'signals' => ['NEW_DEVICE'],
                    'device_uuid' => $device->uuid,
                    'device_name' => 'Pending Phone '.$index,
                ]
            );
        }

        $this
            ->withToken($token)
            ->getJson('/api/v1/auth/security/center')
            ->assertOk()
            ->assertJsonPath('data.summary.pending_review_count', 12)
            ->assertJsonPath('data.summary.highest_pending_severity', 'MEDIUM')
            ->assertJsonCount(10, 'data.notifications')
            ->assertJsonCount(10, 'data.history_preview');
    }
}
