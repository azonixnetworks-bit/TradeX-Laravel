<?php

namespace Tests\Feature\Api\Auth;

use App\Models\User;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationFlowTest extends TestCase
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
        ]);
    }

    public function test_full_registration_flow_creates_active_verified_user(): void
    {
        $referrer = User::query()
            ->create([
                'uuid' =>
                    (string) \Illuminate\Support\Str::uuid(),

                'name' =>
                    'Referrer',

                'email' =>
                    'referrer@example.test',

                'email_verified_at' =>
                    now(),

                'whatsapp_number' =>
                    '+94770000001',

                'whatsapp_verified_at' =>
                    now(),

                'password' =>
                    'Strong-Password-123!',

                'referral_code' =>
                    'REFACTIVE1',

                'country_code' =>
                    'LK',

                'language_code' =>
                    'en',

                'account_type' =>
                    'FREE',

                'status' =>
                    'ACTIVE',
            ]);

        $start = $this
            ->postJson(
                '/api/v1/auth/register/start',
                [
                    'name' =>
                        'New Trader',

                    'email' =>
                        'newtrader@example.test',

                    'whatsapp_number' =>
                        '+94770000002',

                    'password' =>
                        'TradeX-Strong-Password-123!',

                    'password_confirmation' =>
                        'TradeX-Strong-Password-123!',

                    'referral_code' =>
                        $referrer->referral_code,

                    'country_code' =>
                        'LK',

                    'language_code' =>
                        'en',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'success',
                true
            )
            ->assertJsonPath(
                'data.next_action',
                'VERIFY_EMAIL_OTP'
            );

        $registrationId =
            $start->json(
                'data.registration_id'
            );

        $registrationToken =
            $start->json(
                'data.registration_token'
            );

        $this
            ->postJson(
                '/api/v1/auth/register/email/verify',
                [
                    'registration_id' =>
                        $registrationId,

                    'registration_token' =>
                        $registrationToken,

                    'otp_code' =>
                        '123456',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.email_verified',
                true
            );

        $this
            ->postJson(
                '/api/v1/auth/register/whatsapp/verify',
                [
                    'registration_id' =>
                        $registrationId,

                    'registration_token' =>
                        $registrationToken,

                    'otp_code' =>
                        '123456',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.whatsapp_verified',
                true
            );

        $setup = $this
            ->postJson(
                '/api/v1/auth/register/totp/setup',
                [
                    'registration_id' =>
                        $registrationId,

                    'registration_token' =>
                        $registrationToken,
                ]
            )
            ->assertOk()
            ->assertHeader(
                'Cache-Control',
                'private, no-store, no-cache, max-age=0, must-revalidate'
            )
            ->assertJsonPath(
                'data.next_action',
                'CONFIRM_TOTP'
            )
            ->assertJsonPath(
                'data.qr_code_mime_type',
                'image/svg+xml'
            );

        $qrDataUri = (string) $setup->json(
            'data.qr_code_data_uri'
        );

        $this->assertStringStartsWith(
            'data:image/svg+xml;base64,',
            $qrDataUri
        );

        $svg = base64_decode(
            substr(
                $qrDataUri,
                strlen('data:image/svg+xml;base64,')
            ),
            true
        );

        $this->assertIsString($svg);
        $this->assertStringContainsString(
            '<svg',
            $svg
        );

        $secret =
            $setup->json(
                'data.secret'
            );

        $code = app(
            TotpService::class
        )->codeAt(
            $secret,
            time()
        );

        $this
            ->postJson(
                '/api/v1/auth/register/totp/confirm',
                [
                    'registration_id' =>
                        $registrationId,

                    'registration_token' =>
                        $registrationToken,

                    'totp_code' =>
                        $code,
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.completed',
                true
            )
            ->assertJsonPath(
                'data.next_action',
                'LOGIN'
            );

        $user = User::query()
            ->where(
                'email',
                'newtrader@example.test'
            )
            ->firstOrFail();

        $this->assertSame(
            'ACTIVE',
            $user->status
        );

        $this->assertSame(
            'FREE',
            $user->account_type
        );

        $this->assertNotNull(
            $user->email_verified_at
        );

        $this->assertNotNull(
            $user->whatsapp_verified_at
        );

        $this->assertTrue(
            $user->hasConfirmedTotp()
        );

        $this->assertSame(
            $referrer->getKey(),
            $user->referred_by_user_id
        );

        $this->assertNotEmpty(
            $user->referral_code
        );
    }
}
