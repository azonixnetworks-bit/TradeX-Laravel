<?php

namespace Tests\Feature\Api\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\CreatesPasswordRecoveryFlow;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class PasswordRecoveryFlowTest extends TestCase
{
    use CreatesPasswordRecoveryFlow;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePasswordRecoveryTesting();
    }

    public function test_full_recovery_flow_resets_password_and_consumes_session(): void
    {
        $user = $this->createApiUser([
            'password' => 'TradeX-Old-Password-123!',
        ]);

        $flow =
            $this->completePasswordRecoveryFactors(
                $user
            );

        $this->postJson(
            '/api/v1/auth/password-recovery/reset',
            [
                ...$flow,
                'password' =>
                    'TradeX-New-Password-456!',
                'password_confirmation' =>
                    'TradeX-New-Password-456!',
            ]
        )
            ->assertOk()
            ->assertJsonPath('data.completed', true)
            ->assertJsonPath(
                'data.next_action',
                'LOGIN'
            );

        $user->refresh();

        $this->assertTrue(
            Hash::check(
                'TradeX-New-Password-456!',
                (string) $user->password
            )
        );

        $session =
            $this->recoverySession(
                $flow['recovery_id']
            );

        $this->assertNotNull(
            $session->consumed_at
        );

        $this->assertNull(
            $session->reset_token_hash
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_type' => 'USER',
                'actor_id' => $user->getKey(),
                'action' =>
                    'PASSWORD_RECOVERY_COMPLETED',
                'module' => 'AUTH',
            ]
        );
    }

    public function test_current_password_cannot_be_reused(): void
    {
        $user = $this->createApiUser([
            'password' =>
                'TradeX-Current-Password-123!',
        ]);

        $flow =
            $this->completePasswordRecoveryFactors(
                $user
            );

        $this->postJson(
            '/api/v1/auth/password-recovery/reset',
            [
                ...$flow,
                'password' =>
                    'TradeX-Current-Password-123!',
                'password_confirmation' =>
                    'TradeX-Current-Password-123!',
            ]
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'PASSWORD_RECOVERY_PASSWORD_REUSED'
            );
    }

    public function test_reset_token_is_single_use(): void
    {
        $user = $this->createApiUser();

        $flow =
            $this->completePasswordRecoveryFactors(
                $user
            );

        $payload = [
            ...$flow,
            'password' =>
                'TradeX-One-Time-Password-789!',
            'password_confirmation' =>
                'TradeX-One-Time-Password-789!',
        ];

        $this->postJson(
            '/api/v1/auth/password-recovery/reset',
            $payload
        )->assertOk();

        $this->postJson(
            '/api/v1/auth/password-recovery/reset',
            $payload
        )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'PASSWORD_RECOVERY_SESSION_INVALID'
            );
    }
}
