<?php

namespace Tests\Feature\Api\Profile;

use App\Models\UserProfile;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class FinancialProfileTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;

    public function test_financial_details_require_totp_and_are_encrypted_at_rest(): void
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

        $paypal =
            'payments@example.test';

        $this
            ->withToken(
                $token
            )
            ->patchJson(
                '/api/v1/profile/financial',
                [
                    'totp_code' =>
                        $totp,

                    'paypal_email' =>
                        $paypal,

                    'binance_email' =>
                        'binance@example.test',

                    'usdt_trc20_address' =>
                        'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE',

                    'bank_name' =>
                        'Example Bank',

                    'bank_branch' =>
                        'Colombo',

                    'bank_account_holder' =>
                        'Profile User',

                    'bank_account_number' =>
                        '1234567890',
                ]
            )
            ->assertOk()
            ->assertJsonPath(
                'data.paypal.configured',
                true
            );

        $profile =
            UserProfile::query()
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->firstOrFail();

        $this->assertSame(
            $paypal,
            $profile->paypal_email
        );

        $raw =
            DB::table(
                'user_profiles'
            )
                ->where(
                    'id',
                    $profile->getKey()
                )
                ->value(
                    'paypal_email'
                );

        $this->assertNotSame(
            $paypal,
            $raw
        );
    }

    public function test_non_sri_lanka_account_cannot_add_sri_lanka_local_bank(): void
    {
        $user =
            $this->createApiUser([
                'country_code' =>
                    'IN',
            ]);

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

        $this
            ->withToken(
                $token
            )
            ->patchJson(
                '/api/v1/profile/financial',
                [
                    'totp_code' =>
                        $totp,

                    'bank_name' =>
                        'Example Bank',

                    'bank_branch' =>
                        'Colombo',

                    'bank_account_holder' =>
                        'Profile User',

                    'bank_account_number' =>
                        '1234567890',
                ]
            )
            ->assertStatus(422)
            ->assertJsonPath(
                'error.code',
                'LOCAL_BANK_NOT_AVAILABLE'
            );
    }
}
