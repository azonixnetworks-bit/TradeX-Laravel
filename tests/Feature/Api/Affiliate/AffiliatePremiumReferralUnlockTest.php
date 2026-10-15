<?php

namespace Tests\Feature\Api\Affiliate;

use App\Models\Product;
use App\Models\User;
use App\Models\UserEntitlement;
use App\Models\UserRegistrationSession;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class AffiliatePremiumReferralUnlockTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tradex_trade_ideas.share.affiliate_base_url' => 'https://tradex.example/register',

            'tradex_registration.delivery.email_driver' => 'log',

            'tradex_registration.delivery.whatsapp_driver' => 'log',

            'tradex_registration.testing.fixed_otp' => '123456',
        ]);
    }

    public function test_dashboard_referral_url_is_locked_until_premium_entitlement_is_active(): void
    {
        $user = $this->createApiUser([
            'account_type' => 'FREE',
            'referral_code' => 'TXLOCKED001',
        ]);

        $token = $this->issueBoundToken($user);

        $this
            ->withToken($token)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath(
                'data.account.premium_access',
                false
            )
            ->assertJsonPath(
                'data.referral.code',
                'TXLOCKED001'
            )
            ->assertJsonPath(
                'data.referral.url',
                null
            );

        $this->activatePremiumEntitlement($user);

        $this
            ->withToken($token)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath(
                'data.account.premium_access',
                true
            )
            ->assertJsonPath(
                'data.referral.url',
                'https://tradex.example/register?ref=TXLOCKED001'
            );
    }

    public function test_self_registration_rejects_referral_code_from_active_non_premium_user(): void
    {
        $referrer = $this->createApiUser([
            'account_type' => 'FREE',
            'referral_code' => 'TXFREE00001',
        ]);

        $this
            ->postJson(
                '/api/v1/auth/register/start',
                $this->registrationPayload(
                    $referrer->referral_code
                )
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'INVALID_REFERRAL_CODE'
            );

        $this->assertDatabaseCount(
            'user_registration_sessions',
            0
        );
    }

    public function test_system_referral_code_is_accepted_for_an_active_non_premium_user(): void
    {
        $referrer = $this->createApiUser([
            'account_type' => 'FREE',
            'referral_code' => 'TRXADMIN',
        ]);

        config([
            'tradex_registration.system_referral_codes' => [
                'TRXADMIN',
            ],
        ]);

        $response = $this
            ->postJson(
                '/api/v1/auth/register/start',
                $this->registrationPayload(
                    $referrer->referral_code
                )
            )
            ->assertCreated();

        $session = UserRegistrationSession::query()
            ->where(
                'uuid',
                $response->json(
                    'data.registration_id'
                )
            )
            ->firstOrFail();

        $this->assertSame(
            $referrer->getKey(),
            $session->referred_by_user_id
        );
    }

    public function test_self_registration_accepts_referral_code_after_premium_entitlement_activation(): void
    {
        $referrer = $this->createApiUser([
            'account_type' => 'FREE',
            'referral_code' => 'TXPAID00001',
        ]);

        $this->activatePremiumEntitlement($referrer);

        $response = $this
            ->postJson(
                '/api/v1/auth/register/start',
                $this->registrationPayload(
                    $referrer->referral_code
                )
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.next_action',
                'VERIFY_EMAIL_OTP'
            );

        $session = UserRegistrationSession::query()
            ->where(
                'uuid',
                $response->json(
                    'data.registration_id'
                )
            )
            ->firstOrFail();

        $this->assertSame(
            $referrer->getKey(),
            $session->referred_by_user_id
        );
    }

    public function test_revoked_premium_entitlement_relocks_referral_url_and_code_usage(): void
    {
        $referrer = $this->createApiUser([
            'account_type' => 'FREE',
            'referral_code' => 'TXREVOKE001',
        ]);

        $this->activatePremiumEntitlement($referrer);

        $token = $this->issueBoundToken(
            $referrer
        );

        $this
            ->withToken($token)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath(
                'data.referral.url',
                'https://tradex.example/register?ref=TXREVOKE001'
            );

        UserEntitlement::query()
            ->where(
                'user_id',
                $referrer->getKey()
            )
            ->where(
                'product_code',
                Product::CODE_EDUCATION_PREMIUM
            )
            ->update([
                'status' => UserEntitlement::STATUS_REVOKED,
                'revoked_at' => now(),
                'revoke_reason' => 'Test payment reversal',
            ]);

        $this
            ->withToken($token)
            ->getJson('/api/v1/dashboard')
            ->assertOk()
            ->assertJsonPath(
                'data.account.premium_access',
                false
            )
            ->assertJsonPath(
                'data.referral.url',
                null
            );

        $this
            ->postJson(
                '/api/v1/auth/register/start',
                $this->registrationPayload(
                    $referrer->referral_code
                )
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'INVALID_REFERRAL_CODE'
            );
    }

    private function activatePremiumEntitlement(
        User $user
    ): void {
        UserEntitlement::query()
            ->updateOrCreate(
                [
                    'user_id' => $user->getKey(),
                    'product_code' => Product::CODE_EDUCATION_PREMIUM,
                ],
                [
                    'status' => UserEntitlement::STATUS_ACTIVE,
                    'activated_at' => now(),
                    'payment_id' => null,
                    'revoked_at' => null,
                    'revoke_reason' => null,
                ]
            );
    }

    private function registrationPayload(
        string $referralCode
    ): array {
        $suffix = Str::lower(
            Str::random(8)
        );

        return [
            'name' => 'Affiliate Unlock Buyer',

            'email' => "affiliate-unlock-{$suffix}@example.test",

            'whatsapp_number' => '+9476'
                .random_int(
                    1000000,
                    9999999
                ),

            'password' => 'TradeX-Affiliate-Unlock-123!',

            'password_confirmation' => 'TradeX-Affiliate-Unlock-123!',

            'referral_code' => $referralCode,

            'country_code' => 'LK',

            'language_code' => 'en',
        ];
    }
}
