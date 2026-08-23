<?php

namespace Tests\Feature\Api\Profile;

use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class TelegramChangeTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tradex_profile.contact_change.email_driver' =>
                'log',

            'tradex_profile.testing.fixed_otp' =>
                '654321',
        ]);
    }

    public function test_telegram_change_requires_totp_then_email_otp(): void
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
                    '/api/v1/profile/telegram/change/request',
                    [
                        'new_telegram_username' =>
                            '@TradeX_User',

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
                '/api/v1/profile/telegram/change/confirm',
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
                        '654321',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.telegram_username',
                'tradex_user'
            );
    }
}
