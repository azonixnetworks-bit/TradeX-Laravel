<?php

namespace Tests\Feature\Api\Auth;

use App\Models\PasswordRecoverySession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesPasswordRecoveryFlow;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class PasswordRecoveryEnumerationTest extends TestCase
{
    use CreatesPasswordRecoveryFlow;
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->configurePasswordRecoveryTesting();
    }

    public function test_start_response_does_not_reveal_account_existence(): void
    {
        $user = $this->createApiUser();

        $real = $this->postJson(
            '/api/v1/auth/password-recovery/start',
            [
                'email' => $user->email,
                'whatsapp_number' =>
                    $user->whatsapp_number,
            ]
        )->assertAccepted();

        $fake = $this->postJson(
            '/api/v1/auth/password-recovery/start',
            [
                'email' => 'missing@example.test',
                'whatsapp_number' => '+94779999999',
            ]
        )->assertAccepted();

        foreach ([$real, $fake] as $response) {
            $response
                ->assertJsonPath('success', true)
                ->assertJsonStructure([
                    'data' => [
                        'recovery_id',
                        'recovery_token',
                        'next_action',
                        'message',
                    ],
                ]);
        }

        $this->assertSame(
            $real->json('data.next_action'),
            $fake->json('data.next_action')
        );

        $fakeSession =
            PasswordRecoverySession::query()
                ->where(
                    'uuid',
                    $fake->json('data.recovery_id')
                )
                ->firstOrFail();

        $this->assertNull($fakeSession->user_id);

        $payload = json_encode(
            $fake->json(),
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            'missing@example.test',
            $payload
        );

        $this->assertStringNotContainsString(
            '+94779999999',
            $payload
        );
    }

    public function test_mismatched_whatsapp_creates_decoy_session(): void
    {
        $user = $this->createApiUser();

        $response = $this->postJson(
            '/api/v1/auth/password-recovery/start',
            [
                'email' => $user->email,
                'whatsapp_number' => '+94778888888',
            ]
        )->assertAccepted();

        $session =
            PasswordRecoverySession::query()
                ->where(
                    'uuid',
                    $response->json('data.recovery_id')
                )
                ->firstOrFail();

        $this->assertNull($session->user_id);
    }
}
