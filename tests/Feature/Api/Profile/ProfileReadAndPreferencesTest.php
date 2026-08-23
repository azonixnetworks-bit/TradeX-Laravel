<?php

namespace Tests\Feature\Api\Profile;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class ProfileReadAndPreferencesTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;

    public function test_profile_requires_authenticated_bound_device_session(): void
    {
        $this
            ->getJson(
                '/api/v1/profile'
            )
            ->assertUnauthorized();
    }

    public function test_profile_returns_locked_identity_and_masked_finance_shape(): void
    {
        $user =
            $this->createApiUser();

        $token =
            $this->issueBoundToken(
                $user
            );

        $this
            ->withToken(
                $token
            )
            ->getJson(
                '/api/v1/profile'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.identity.email',
                $user->email
            )
            ->assertJsonPath(
                'data.identity.locked_fields.0',
                'name'
            )
            ->assertJsonPath(
                'data.financial.paypal.configured',
                false
            )
            ->assertJsonPath(
                'data.security.totp_confirmed',
                true
            );
    }

    public function test_language_preference_can_be_updated_without_unlocking_identity(): void
    {
        $user =
            $this->createApiUser();

        $token =
            $this->issueBoundToken(
                $user
            );

        $this
            ->withToken(
                $token
            )
            ->patchJson(
                '/api/v1/profile/preferences',
                [
                    'language_code' =>
                        'ta',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.language_code',
                'ta'
            );

        $user->refresh();

        $this->assertSame(
            'ta',
            $user->language_code
        );
    }
}
