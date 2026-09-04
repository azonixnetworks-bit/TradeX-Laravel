<?php

namespace Tests\Feature\Api\Profile;

use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class WhatsAppChangeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tradex_profile.contact_change.email_driver' =>
                'log',

            'tradex_profile.testing.fixed_email_otp' =>
                '65432109',
        ]);
    }

    public function test_whatsapp_change_requires_totp_then_email_otp(): void
    {
        $user =
            $this->createApiUser();

        $token =
            $this->issueBoundToken(
                $user
            );

        $totp =
            app(
                TotpService::class
            )->codeAt(
                (string) $user->totp_secret,
                time()
            );

        $requestChange =
            $this
                ->withToken(
                    $token
                )
                ->postJson(
                    '/api/v1/profile/whatsapp/change/request',
                    [
                        'new_whatsapp_number' =>
                            '+94781234567',

                        'totp_code' =>
                            $totp,
                    ]
                )
                ->assertOk()
                ->assertJsonPath(
                    'data.verification_channel',
                    'EMAIL'
                );

        $this
            ->withToken(
                $token
            )
            ->postJson(
                '/api/v1/profile/whatsapp/change/confirm',
                [
                    'challenge_id' =>
                        $requestChange->json(
                            'data.challenge_id'
                        ),

                    'challenge_token' =>
                        $requestChange->json(
                            'data.challenge_token'
                        ),

                    'otp_code' =>
                        'TRX-65 43 21 09',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.whatsapp_number',
                '+94781234567'
            )
            ->assertJsonPath(
                'data.whatsapp_verified',
                true
            );
    }

    public function test_invalid_profile_change_token_is_rejected(): void
    {
        $user =
            $this->createApiUser();

        $token =
            $this->issueBoundToken(
                $user
            );

        $totp =
            app(
                TotpService::class
            )->codeAt(
                (string) $user->totp_secret,
                time()
            );

        $requestChange =
            $this
                ->withToken(
                    $token
                )
                ->postJson(
                    '/api/v1/profile/whatsapp/change/request',
                    [
                        'new_whatsapp_number' =>
                            '+94781234568',

                        'totp_code' =>
                            $totp,
                    ]
                )
                ->assertOk();

        $this
            ->withToken(
                $token
            )
            ->postJson(
                '/api/v1/profile/whatsapp/change/confirm',
                [
                    'challenge_id' =>
                        $requestChange->json(
                            'data.challenge_id'
                        ),

                    'challenge_token' =>
                        str_repeat(
                            'a',
                            64
                        ),

                    'otp_code' =>
                        '654321',
                ]
            )
            ->assertUnauthorized()
            ->assertJsonPath(
                'error.code',
                'INVALID_PROFILE_CHANGE_CHALLENGE'
            );
    }

    public function test_invalid_email_otp_attempt_is_persisted(): void
    {
        $user =
            $this->createApiUser();

        $token =
            $this->issueBoundToken(
                $user
            );

        $totp =
            app(
                TotpService::class
            )->codeAt(
                (string) $user->totp_secret,
                time()
            );

        $requestChange =
            $this
                ->withToken(
                    $token
                )
                ->postJson(
                    '/api/v1/profile/whatsapp/change/request',
                    [
                        'new_whatsapp_number' =>
                            '+94781234569',

                        'totp_code' =>
                            $totp,
                    ]
                )
                ->assertOk();

        $this
            ->withToken(
                $token
            )
            ->postJson(
                '/api/v1/profile/whatsapp/change/confirm',
                [
                    'challenge_id' =>
                        $requestChange->json(
                            'data.challenge_id'
                        ),

                    'challenge_token' =>
                        $requestChange->json(
                            'data.challenge_token'
                        ),

                    'otp_code' =>
                        '111111',
                ]
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'INVALID_PROFILE_CHANGE_OTP'
            );

        $this->assertDatabaseHas(
            'user_profile_change_challenges',
            [
                'uuid' =>
                    $requestChange->json(
                        'data.challenge_id'
                    ),

                'attempts' =>
                    1,
            ]
        );
    }

}
