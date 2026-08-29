<?php

namespace Tests\Feature\Api\Auth;

use App\Models\AdminUser;
use App\Models\CustomerOnboardingInvitation;
use App\Models\LegalPolicyVersion;
use App\Models\OnboardingLegalPolicyAcceptance;
use App\Models\User;
use App\Models\UserLegalPolicyAcceptance;
use App\Models\UserRegistrationSession;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerInvitationActivationFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'tradex_registration.delivery.email_driver' => 'log',
            'tradex_registration.delivery.whatsapp_driver' => 'log',
            'tradex_registration.testing.fixed_otp' => '123456',
            'tradex_customers.required_onboarding_legal_policies' => [
                'TERMS_OF_SERVICE',
                'PRIVACY_POLICY',
                'TRADING_RISK_DISCLOSURE',
            ],
        ]);
    }

    public function test_admin_invite_completes_secure_activation_only_after_required_steps(): void
    {
        $this->publishRequiredPolicies();

        $plainInvitationToken = str_repeat('a', 64);
        $invitation = $this->createInvitation(
            $plainInvitationToken
        );

        $activate = $this->postJson(
            '/api/v1/auth/invite/activate',
            [
                'invitation_id' => $invitation->uuid,
                'token' => $plainInvitationToken,
            ]
        )
            ->assertCreated()
            ->assertJsonPath(
                'data.source',
                UserRegistrationSession::SOURCE_ADMIN_INVITE
            )
            ->assertJsonPath(
                'data.next_action',
                'VERIFY_EMAIL_OTP'
            );

        $registrationId = $activate->json(
            'data.registration_id'
        );
        $registrationToken = $activate->json(
            'data.registration_token'
        );

        $session = UserRegistrationSession::query()
            ->where('uuid', $registrationId)
            ->firstOrFail();

        $this->assertTrue($session->isAdminInvite());
        $this->assertNull($session->password_set_at);
        $this->assertDatabaseCount('users', 0);

        $invitation->refresh();
        $this->assertNull(
            $invitation->activation_token_hash
        );
        $this->assertNotNull(
            $invitation->activation_consumed_at
        );
        $this->assertSame(
            CustomerOnboardingInvitation::STAGE_EMAIL_OTP_SENT,
            $invitation->onboarding_stage
        );

        $this->postJson(
            '/api/v1/auth/invite/activate',
            [
                'invitation_id' => $invitation->uuid,
                'token' => $plainInvitationToken,
            ]
        )->assertUnauthorized();

        $this->postJson(
            '/api/v1/auth/register/email/verify',
            $this->sessionPayload(
                $registrationId,
                $registrationToken,
                ['otp_code' => '123456']
            )
        )
            ->assertOk()
            ->assertJsonPath(
                'data.next_action',
                'VERIFY_WHATSAPP_OTP'
            );

        $this->postJson(
            '/api/v1/auth/register/whatsapp/verify',
            $this->sessionPayload(
                $registrationId,
                $registrationToken,
                ['otp_code' => '123456']
            )
        )
            ->assertOk()
            ->assertJsonPath(
                'data.next_action',
                'SET_PASSWORD'
            );

        $this->postJson(
            '/api/v1/auth/register/totp/setup',
            $this->sessionPayload(
                $registrationId,
                $registrationToken
            )
        )->assertStatus(409);

        $password = 'TradeX-Invite-Strong-123!';

        $this->postJson(
            '/api/v1/auth/invite/password',
            $this->sessionPayload(
                $registrationId,
                $registrationToken,
                [
                    'password' => $password,
                    'password_confirmation' => $password,
                ]
            )
        )
            ->assertOk()
            ->assertJsonPath(
                'data.next_action',
                'SETUP_TOTP'
            );

        $setup = $this->postJson(
            '/api/v1/auth/register/totp/setup',
            $this->sessionPayload(
                $registrationId,
                $registrationToken
            )
        )
            ->assertOk()
            ->assertJsonPath(
                'data.next_action',
                'CONFIRM_TOTP'
            );

        $secret = (string) $setup->json(
            'data.secret'
        );

        $totpCode = app(TotpService::class)
            ->codeAt(
                $secret,
                time()
            );

        $this->postJson(
            '/api/v1/auth/register/totp/confirm',
            $this->sessionPayload(
                $registrationId,
                $registrationToken,
                ['totp_code' => $totpCode]
            )
        )->assertStatus(409);

        $this->assertDatabaseCount('users', 0);

        $this->postJson(
            '/api/v1/auth/invite/totp/confirm',
            $this->sessionPayload(
                $registrationId,
                $registrationToken,
                ['totp_code' => $totpCode]
            )
        )
            ->assertOk()
            ->assertJsonPath(
                'data.next_action',
                'ACCEPT_LEGAL_TERMS'
            );

        $policies = $this->postJson(
            '/api/v1/auth/invite/legal/policies',
            $this->sessionPayload(
                $registrationId,
                $registrationToken
            )
        )
            ->assertOk()
            ->assertJsonCount(3, 'data.items');

        $acceptances = collect(
            $policies->json('data.items')
        )
            ->map(fn (array $policy): array => [
                'policy_uuid' => $policy['uuid'],
                'content_sha256' =>
                    $policy['content_sha256'],
                'accepted' => true,
            ])
            ->values()
            ->all();

        $this->postJson(
            '/api/v1/auth/invite/legal/accept',
            $this->sessionPayload(
                $registrationId,
                $registrationToken,
                ['acceptances' => $acceptances]
            )
        )
            ->assertOk()
            ->assertJsonPath(
                'data.all_required_accepted',
                true
            )
            ->assertJsonPath(
                'data.next_action',
                'COMPLETE_ONBOARDING'
            );

        $this->assertDatabaseCount(
            'onboarding_legal_policy_acceptances',
            3
        );
        $this->assertDatabaseCount('users', 0);

        $complete = $this->postJson(
            '/api/v1/auth/invite/complete',
            $this->sessionPayload(
                $registrationId,
                $registrationToken
            )
        )
            ->assertOk()
            ->assertJsonPath('data.completed', true)
            ->assertJsonPath('data.account_type', 'FREE')
            ->assertJsonPath('data.status', 'ACTIVE')
            ->assertJsonPath('data.next_action', 'LOGIN');

        $user = User::query()
            ->where(
                'uuid',
                $complete->json('data.user_uuid')
            )
            ->firstOrFail();

        $this->assertSame('ACTIVE', $user->status);
        $this->assertSame('FREE', $user->account_type);
        $this->assertTrue(
            Hash::check($password, $user->password)
        );
        $this->assertTrue($user->hasConfirmedTotp());
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotNull(
            $user->whatsapp_verified_at
        );

        $session->refresh();
        $this->assertTrue($session->isCompleted());
        $this->assertSame(
            $user->getKey(),
            $session->completed_user_id
        );

        $invitation->refresh();
        $this->assertSame(
            CustomerOnboardingInvitation::STATUS_MATCHED,
            $invitation->status
        );
        $this->assertSame(
            CustomerOnboardingInvitation::STAGE_COMPLETED,
            $invitation->onboarding_stage
        );
        $this->assertSame(
            $user->getKey(),
            $invitation->matched_user_id
        );
        $this->assertNull($invitation->active_email_hash);
        $this->assertNull(
            $invitation->active_whatsapp_hash
        );

        $this->assertSame(
            3,
            UserLegalPolicyAcceptance::query()
                ->where('user_id', $user->getKey())
                ->count()
        );

        $this->assertSame(
            3,
            OnboardingLegalPolicyAcceptance::query()
                ->where(
                    'user_registration_session_id',
                    $session->getKey()
                )
                ->count()
        );
    }

    public function test_invalid_invitation_token_cannot_create_registration_session(): void
    {
        $invitation = $this->createInvitation(
            str_repeat('b', 64)
        );

        $this->postJson(
            '/api/v1/auth/invite/activate',
            [
                'invitation_id' => $invitation->uuid,
                'token' => str_repeat('c', 64),
            ]
        )->assertUnauthorized();

        $this->assertDatabaseCount(
            'user_registration_sessions',
            0
        );
        $this->assertDatabaseCount('users', 0);
    }

    private function createInvitation(
        string $plainToken
    ): CustomerOnboardingInvitation {
        $admin = AdminUser::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'ADMIN-20B Test Admin',
            'email' => 'admin20b-'
                .Str::lower(Str::random(8))
                .'@example.test',
            'email_verified_at' => now(),
            'password' => 'Testing-Admin-Pass-123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $email = 'invite.customer20b@example.test';
        $whatsapp = '+94771112233';

        return CustomerOnboardingInvitation::query()
            ->create([
                'uuid' => (string) Str::uuid(),
                'name' => 'ADMIN-20B Customer',
                'email' => $email,
                'whatsapp_number' => $whatsapp,
                'country_code' => 'LK',
                'language_code' => 'en',
                'referral_code_input' => null,
                'referred_by_user_id' => null,
                'status' =>
                    CustomerOnboardingInvitation::STATUS_PENDING,
                'onboarding_stage' =>
                    CustomerOnboardingInvitation::STAGE_INVITE_SENT,
                'activation_token_hash' => hash(
                    'sha256',
                    $plainToken
                ),
                'invited_at' => now(),
                'last_invited_at' => now(),
                'invite_send_count' => 1,
                'active_email_hash' => hash(
                    'sha256',
                    mb_strtolower($email)
                ),
                'active_whatsapp_hash' => hash(
                    'sha256',
                    $whatsapp
                ),
                'created_by_admin_id' => $admin->getKey(),
                'expires_at' => now()->addDays(7),
            ]);
    }

    private function publishRequiredPolicies(): void
    {
        foreach (
            [
                'TERMS_OF_SERVICE',
                'PRIVACY_POLICY',
                'TRADING_RISK_DISCLOSURE',
            ] as $index => $type
        ) {
            $content = $type.' ADMIN-20B policy content.';

            LegalPolicyVersion::query()->create([
                'uuid' => (string) Str::uuid(),
                'policy_type' => $type,
                'version_number' => 1,
                'title' => str_replace('_', ' ', $type),
                'summary' => 'Required onboarding policy.',
                'content' => $content,
                'content_sha256' => hash(
                    'sha256',
                    $content
                ),
                'status' =>
                    LegalPolicyVersion::STATUS_PUBLISHED,
                'effective_at' =>
                    now()->subMinutes(10 + $index),
                'published_at' =>
                    now()->subMinutes(20 + $index),
                'revision_version' => 1,
            ]);
        }
    }

    private function sessionPayload(
        string $registrationId,
        string $registrationToken,
        array $extra = []
    ): array {
        return array_merge(
            [
                'registration_id' => $registrationId,
                'registration_token' =>
                    $registrationToken,
            ],
            $extra
        );
    }
}
