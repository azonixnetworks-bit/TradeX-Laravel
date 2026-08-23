<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Models\UserRegistrationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistrationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tradex_registration.delivery.email_driver' =>
                'log',

            'tradex_registration.delivery.whatsapp_driver' =>
                'log',

            'tradex_registration.testing.fixed_otp' =>
                '123456',

            'tradex_registration.otp.resend_cooldown_seconds' =>
                0,
        ]);
    }

    public function test_invalid_registration_token_is_rejected(): void
    {
        [$id] = $this->start();

        $this
            ->postJson(
                '/api/v1/auth/register/status',
                [
                    'registration_id' =>
                        $id,

                    'registration_token' =>
                        str_repeat('a', 64),
                ]
            )
            ->assertUnauthorized()
            ->assertJsonPath(
                'error.code',
                'INVALID_REGISTRATION_SESSION'
            );
    }

    public function test_whatsapp_otp_cannot_be_sent_before_email_verification(): void
    {
        [$id, $token] =
            $this->start();

        $this
            ->postJson(
                '/api/v1/auth/register/whatsapp/resend',
                [
                    'registration_id' =>
                        $id,

                    'registration_token' =>
                        $token,
                ]
            )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'EMAIL_VERIFICATION_REQUIRED'
            );
    }

    public function test_totp_setup_requires_both_contact_verifications(): void
    {
        [$id, $token] =
            $this->start();

        $this
            ->postJson(
                '/api/v1/auth/register/totp/setup',
                [
                    'registration_id' =>
                        $id,

                    'registration_token' =>
                        $token,
                ]
            )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'CONTACT_VERIFICATION_REQUIRED'
            );
    }

    public function test_duplicate_registered_email_is_rejected(): void
    {
        User::query()->create([
            'uuid' =>
                (string) Str::uuid(),

            'name' =>
                'Existing',

            'email' =>
                'existing@example.test',

            'password' =>
                'Existing-Password-123!',

            'referral_code' =>
                'EXISTING01',

            'country_code' =>
                'LK',

            'language_code' =>
                'en',

            'account_type' =>
                'FREE',

            'status' =>
                'ACTIVE',
        ]);

        $this
            ->postJson(
                '/api/v1/auth/register/start',
                $this->payload([
                    'email' =>
                        'existing@example.test',
                ])
            )
            ->assertStatus(409)
            ->assertJsonPath(
                'error.code',
                'EMAIL_ALREADY_REGISTERED'
            );
    }

    public function test_expired_registration_session_is_rejected(): void
    {
        [$id, $token] =
            $this->start();

        UserRegistrationSession::query()
            ->where(
                'uuid',
                $id
            )
            ->update([
                'expires_at' =>
                    now()->subMinute(),
            ]);

        $this
            ->postJson(
                '/api/v1/auth/register/status',
                [
                    'registration_id' =>
                        $id,

                    'registration_token' =>
                        $token,
                ]
            )
            ->assertStatus(410)
            ->assertJsonPath(
                'error.code',
                'REGISTRATION_SESSION_EXPIRED'
            );
    }

    public function test_invalid_email_otp_is_rejected_and_attempted(): void
    {
        [$id, $token] =
            $this->start();

        $this
            ->postJson(
                '/api/v1/auth/register/email/verify',
                [
                    'registration_id' =>
                        $id,

                    'registration_token' =>
                        $token,

                    'otp_code' =>
                        '999999',
                ]
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'INVALID_OTP'
            );
    }

    private function start(): array
    {
        $response = $this
            ->postJson(
                '/api/v1/auth/register/start',
                $this->payload()
            )
            ->assertCreated();

        return [
            $response->json(
                'data.registration_id'
            ),

            $response->json(
                'data.registration_token'
            ),
        ];
    }

    private function payload(
        array $overrides = []
    ): array {
        $suffix =
            Str::lower(
                Str::random(8)
            );

        return array_merge(
            [
                'name' =>
                    'Security User',

                'email' =>
                    "security-{$suffix}@example.test",

                'whatsapp_number' =>
                    '+9478'
                    .random_int(
                        1000000,
                        9999999
                    ),

                'password' =>
                    'TradeX-Secure-Password-123!',

                'password_confirmation' =>
                    'TradeX-Secure-Password-123!',

                'referral_code' =>
                    null,

                'country_code' =>
                    'LK',

                'language_code' =>
                    'en',
            ],
            $overrides
        );
    }
}
