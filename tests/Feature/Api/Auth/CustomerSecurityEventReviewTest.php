<?php

namespace Tests\Feature\Api\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\UserRefreshToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class CustomerSecurityEventReviewTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_customer_can_confirm_risk_event_as_their_own_login(): void
    {
        [$user, $token, $current] = $this->currentSession();
        $other = $this->trustedDevice($user, 'Reviewed Phone');
        $event = $this->riskEvent($user, $other);

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/auth/security/events/'.$event->getKey().'/review',
                ['response' => 'YES']
            )
            ->assertOk()
            ->assertJsonPath('data.status', 'CONFIRMED')
            ->assertJsonPath('data.device_revoked', false)
            ->assertJsonPath('data.already_reviewed', false);

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'USER',
            'actor_id' => $user->getKey(),
            'action' => 'USER_SECURITY_EVENT_CONFIRMED',
            'target_type' => 'AUDIT_LOG',
            'target_id' => $event->getKey(),
        ]);

        $this
            ->withToken($token)
            ->getJson('/api/v1/auth/security/notifications')
            ->assertOk()
            ->assertJsonPath('data.notifications.0.review.status', 'CONFIRMED')
            ->assertJsonPath('data.notifications.0.review.can_respond', false);

        $current->refresh();
        $this->assertTrue((bool) $current->is_active);
        $this->assertTrue((bool) $other->fresh()->is_active);
    }

    public function test_customer_can_report_other_suspicious_device_and_revoke_its_sessions(): void
    {
        [$user, $token] = $this->currentSession();
        $other = $this->trustedDevice($user, 'Unknown Phone');
        $refresh = $this->refreshToken($user, $other);
        $event = $this->riskEvent($user, $other, 'USER_SUSPICIOUS_LOGIN_DETECTED');

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/auth/security/events/'.$event->getKey().'/review',
                ['response' => 'NO']
            )
            ->assertOk()
            ->assertJsonPath('data.status', 'REPORTED_UNAUTHORIZED')
            ->assertJsonPath('data.device_revoked', true);

        $other->refresh();
        $refresh->refresh();

        $this->assertFalse((bool) $other->is_active);
        $this->assertFalse((bool) $other->is_trusted);
        $this->assertNotNull($other->revoked_at);
        $this->assertSame('USER_REPORTED_UNAUTHORIZED_LOGIN', $other->revoke_reason);
        $this->assertNotNull($refresh->revoked_at);
        $this->assertSame('USER_REPORTED_UNAUTHORIZED_LOGIN', $refresh->revoke_reason);

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'USER',
            'actor_id' => $user->getKey(),
            'action' => 'USER_SECURITY_EVENT_REPORTED_UNAUTHORIZED',
            'target_type' => 'AUDIT_LOG',
            'target_id' => $event->getKey(),
        ]);
    }

    public function test_customer_cannot_review_another_users_security_event(): void
    {
        [$user, $token] = $this->currentSession();
        $otherUser = $this->createApiUser();
        $otherDevice = $this->trustedDevice($otherUser, 'Other User Phone');
        $event = $this->riskEvent($otherUser, $otherDevice);

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/auth/security/events/'.$event->getKey().'/review',
                ['response' => 'YES']
            )
            ->assertStatus(404)
            ->assertJsonPath('error.code', 'SECURITY_EVENT_NOT_FOUND');

        $this->assertDatabaseMissing('audit_logs', [
            'actor_type' => 'USER',
            'actor_id' => $user->getKey(),
            'target_type' => 'AUDIT_LOG',
            'target_id' => $event->getKey(),
        ]);
    }

    public function test_same_security_review_response_is_idempotent_but_cannot_be_changed(): void
    {
        [$user, $token] = $this->currentSession();
        $other = $this->trustedDevice($user, 'Known Phone');
        $event = $this->riskEvent($user, $other);

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/auth/security/events/'.$event->getKey().'/review',
                ['response' => 'YES']
            )
            ->assertOk()
            ->assertJsonPath('data.already_reviewed', false);

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/auth/security/events/'.$event->getKey().'/review',
                ['response' => 'YES']
            )
            ->assertOk()
            ->assertJsonPath('data.already_reviewed', true);

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/auth/security/events/'.$event->getKey().'/review',
                ['response' => 'NO']
            )
            ->assertStatus(409)
            ->assertJsonPath('error.code', 'SECURITY_EVENT_ALREADY_REVIEWED');
    }

    public function test_customer_cannot_report_current_device_as_unauthorized_from_same_device(): void
    {
        [$user, $token, $current] = $this->currentSession();
        $event = $this->riskEvent($user, $current, 'USER_SUSPICIOUS_LOGIN_DETECTED');

        $this
            ->withToken($token)
            ->postJson(
                '/api/v1/auth/security/events/'.$event->getKey().'/review',
                ['response' => 'NO']
            )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'CURRENT_DEVICE_SECURITY_REVIEW_REQUIRES_RECOVERY'
            );

        $current->refresh();
        $this->assertTrue((bool) $current->is_active);
        $this->assertTrue((bool) $current->is_trusted);
    }

    private function currentSession(): array
    {
        $user = $this->createApiUser();
        $token = $this->issueBoundToken($user);
        $device = $user->devices()
            ->orderByDesc('id')
            ->firstOrFail();

        return [$user, $token, $device];
    }

    private function trustedDevice(User $user, string $name): UserDevice
    {
        return $user->devices()->create([
            'uuid' => (string) Str::uuid(),
            'device_slot' => UserDevice::SLOT_MOBILE,
            'platform' => UserDevice::PLATFORM_ANDROID,
            'device_name' => $name,
            'device_model' => 'Review Test Model',
            'installation_id_hash' => hash('sha256', (string) Str::uuid()),
            'is_trusted' => true,
            'is_active' => true,
            'registered_at' => now(),
            'trusted_at' => now(),
            'last_seen_at' => now(),
            'last_login_at' => now(),
            'last_login_ip' => '203.0.113.55',
        ]);
    }

    private function riskEvent(
        User $user,
        UserDevice $device,
        string $action = 'USER_LOGIN_RISK_DETECTED'
    ): AuditLog {
        return AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: $action,
            module: 'SECURITY',
            targetType: 'USER_DEVICE',
            targetId: $device->getKey(),
            newValues: [
                'risk_level' => $action === 'USER_SUSPICIOUS_LOGIN_DETECTED'
                    ? 'SUSPICIOUS'
                    : 'REVIEW',
                'risk_score' => $action === 'USER_SUSPICIOUS_LOGIN_DETECTED'
                    ? 75
                    : 35,
                'signals' => ['NEW_DEVICE', 'NEW_IP'],
                'device_uuid' => $device->uuid,
                'platform' => $device->platform,
                'device_name' => $device->device_name,
                'device_model' => $device->device_model,
            ],
            ipAddress: '203.0.113.55',
            userAgent: 'TradeX Security Review Test'
        );
    }

    private function refreshToken(
        User $user,
        UserDevice $device
    ): UserRefreshToken {
        return UserRefreshToken::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $user->getKey(),
            'user_device_id' => $device->getKey(),
            'token_hash' => hash('sha256', 'refresh-'.Str::uuid()),
            'family_uuid' => (string) Str::uuid(),
            'expires_at' => now()->addDay(),
            'last_used_at' => now(),
        ]);
    }
}
