<?php

namespace Tests\Feature\Api\Auth;

use App\Models\Product;
use App\Models\User;
use App\Models\UserEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class RegistrationReferralValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_referral_preview_requires_an_eligible_active_premium_referrer(): void
    {
        $referrer = User::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Referrer',
            'email' => 'referrer-eligibility@example.test',
            'whatsapp_number' => '+94770000231',
            'password' => 'Strong-Pass123!',
            'referral_code' => 'REFERCODE2026',
            'country_code' => 'LK',
            'language_code' => 'en',
            'account_type' => 'PREMIUM',
            'status' => 'ACTIVE',
        ]);

        $route = '/api/v1/auth/register/referral/validate';

        $this->postJson($route, [
            'referral_code' => 'MISSING-CODE',
        ])->assertOk()->assertJsonPath('data.valid', false);

        $this->postJson($route, [
            'referral_code' => 'REFERCODE2026',
        ])->assertOk()->assertJsonPath('data.valid', false);

        UserEntitlement::query()->create([
            'user_id' => $referrer->getKey(),
            'product_code' => Product::CODE_EDUCATION_PREMIUM,
            'status' => UserEntitlement::STATUS_ACTIVE,
            'activated_at' => now(),
        ]);

        $this->postJson($route, [
            'referral_code' => 'refercode2026',
        ])->assertOk()->assertJsonPath('data.valid', true);

        $referrer->update(['status' => 'SUSPENDED']);

        $this->postJson($route, [
            'referral_code' => 'REFERCODE2026',
        ])->assertOk()->assertJsonPath('data.valid', false);
    }

    public function test_referral_code_is_mandatory_on_new_registration(): void
    {
        $this->postJson('/api/v1/auth/register/start', [
            'name' => 'New Trader',
            'email' => 'new-trader-eligibility@example.test',
            'whatsapp_number' => '+94770000335',
            'password' => 'Strong-Pass123!',
            'password_confirmation' => 'Strong-Pass123!',
            'country_code' => 'LK',
            'language_code' => 'en',
        ])->assertUnprocessable()->assertJsonValidationErrors('referral_code');
    }
}
