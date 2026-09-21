<?php

namespace Tests\Feature\Admin\Integrations;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\IntegrationProviderState;
use App\Models\PaymentReceivingAccount;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class IntegrationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(
            AdminRoleSeeder::class
        );

        $this->seed(
            AdminPermissionSeeder::class
        );
    }

    public function test_guest_is_redirected_from_integrations(): void
    {
        $this->get(
            route(
                'admin.integrations.index'
            )
        )->assertRedirect(
            route('admin.login')
        );
    }

    public function test_integrations_view_permission_is_required(): void
    {
        $admin =
            $this->adminWithPermissions(
                []
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.integrations.index'
                )
            )
            ->assertForbidden();
    }

    public function test_index_lists_exactly_four_locked_payment_integrations(): void
    {
        $admin =
            $this->adminWithPermissions([
                'integrations.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.integrations.index'
                )
            )
            ->assertOk()
            ->assertSee('PayPal')
            ->assertSee('Stripe')
            ->assertSee('NOWPayments')
            ->assertSee('Local Bank Deposit');
    }

    public function test_unknown_provider_returns_not_found(): void
    {
        $admin =
            $this->adminWithPermissions([
                'integrations.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                '/admin/integrations/UNKNOWN'
            )
            ->assertNotFound();
    }

    public function test_manage_permission_is_required_for_update(): void
    {
        $admin =
            $this->adminWithPermissions([
                'integrations.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.integrations.update',
                    'STRIPE'
                ),
                $this->updatePayload()
            )
            ->assertForbidden();
    }

    public function test_authorized_admin_can_save_disabled_control_state_without_credentials(): void
    {
        $admin =
            $this->adminWithPermissions([
                'integrations.view',
                'integrations.manage',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.integrations.update',
                    'STRIPE'
                ),
                $this->updatePayload([
                    'countries' => 'LK,IN',
                ])
            )
            ->assertRedirect();

        $state =
            IntegrationProviderState::query()
                ->where(
                    'provider_code',
                    'STRIPE'
                )
                ->firstOrFail();

        $this->assertFalse(
            $state->is_enabled
        );

        $this->assertSame(
            ['LK', 'IN'],
            $state->countries
        );

        $this->assertSame(
            1,
            $state->version
        );
    }

    public function test_country_codes_are_normalized_and_deduplicated(): void
    {
        $admin =
            $this->adminWithPermissions([
                'integrations.view',
                'integrations.manage',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.integrations.update',
                    'STRIPE'
                ),
                $this->updatePayload([
                    'countries' => 'lk, IN,lk',
                ])
            )
            ->assertRedirect();

        $this->assertSame(
            ['LK', 'IN'],
            IntegrationProviderState::query()
                ->where(
                    'provider_code',
                    'STRIPE'
                )
                ->firstOrFail()
                ->countries
        );
    }

    public function test_invalid_country_code_is_rejected(): void
    {
        $admin =
            $this->adminWithPermissions([
                'integrations.view',
                'integrations.manage',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.integrations.update',
                    'STRIPE'
                ),
                $this->updatePayload([
                    'countries' => 'LKA',
                ])
            )
            ->assertSessionHasErrors(
                'countries.0'
            );
    }

    public function test_provider_cannot_be_enabled_without_country_scope(): void
    {
        $this->configureStripe();

        $admin =
            $this->adminWithPermissions([
                'integrations.view',
                'integrations.manage',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.integrations.update',
                    'STRIPE'
                ),
                $this->updatePayload([
                    'is_enabled' => true,

                    'eligibility_confirmed' => true,

                    'countries' => '',
                ])
            )
            ->assertStatus(422);

        $this->assertDatabaseMissing(
            'integration_provider_states',
            [
                'provider_code' => 'STRIPE',

                'is_enabled' => true,
            ]
        );
    }

    public function test_provider_cannot_be_enabled_without_eligibility_confirmation(): void
    {
        $this->configureStripe();

        $admin =
            $this->adminWithPermissions([
                'integrations.view',
                'integrations.manage',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.integrations.update',
                    'STRIPE'
                ),
                $this->updatePayload([
                    'is_enabled' => true,

                    'eligibility_confirmed' => false,
                ])
            )
            ->assertStatus(422);
    }

    public function test_provider_cannot_be_enabled_when_configuration_is_incomplete(): void
    {
        config()->set(
            'tradex_integrations.stripe.publishable_key',
            null
        );

        config()->set(
            'tradex_integrations.stripe.secret_key',
            null
        );

        config()->set(
            'tradex_integrations.stripe.webhook_secret',
            null
        );

        $admin =
            $this->adminWithPermissions([
                'integrations.view',
                'integrations.manage',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.integrations.update',
                    'STRIPE'
                ),
                $this->updatePayload([
                    'is_enabled' => true,

                    'eligibility_confirmed' => true,
                ])
            )
            ->assertStatus(409);
    }

    public function test_configured_stripe_can_be_approved_with_existing_runtime(): void
    {
        $this->configureStripe();

        $admin =
            $this->adminWithPermissions([
                'integrations.view',
                'integrations.manage',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.integrations.update',
                    'STRIPE'
                ),
                $this->updatePayload([
                    'is_enabled' => true,

                    'eligibility_confirmed' => true,

                    'countries' => 'LK',
                ])
            )
            ->assertRedirect();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.integrations.show',
                    'STRIPE'
                )
            )
            ->assertOk()
            ->assertSee(
                'APPROVED_EXISTING_RUNTIME'
            )
            ->assertSee(
                'ACTIVE_EXISTING'
            );
    }

    public function test_nowpayments_wildcard_enables_all_countries_when_configuration_is_ready(): void
    {
        config()->set('tradex_integrations.nowpayments.enabled', true);
        config()->set('tradex_integrations.nowpayments.countries', ['*']);
        config()->set('tradex_integrations.nowpayments.environment', 'SANDBOX');
        config()->set('tradex_integrations.nowpayments.api_key', 'sandbox-api-key');
        config()->set('tradex_integrations.nowpayments.ipn_secret', 'sandbox-ipn-secret');
        config()->set('tradex_integrations.nowpayments.sandbox_base_url', 'https://api-sandbox.nowpayments.io');
        config()->set('tradex_integrations.nowpayments.success_url', 'https://tradex.example.test/payments/nowpayments/return');
        config()->set('tradex_integrations.nowpayments.cancel_url', 'https://tradex.example.test/payments/nowpayments/cancel');

        $admin = $this->adminWithPermissions([
            'integrations.view',
            'integrations.manage',
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.integrations.update', 'NOWPAYMENTS'),
                $this->updatePayload([
                    'is_enabled' => true,
                    'eligibility_confirmed' => true,
                    'countries' => '*',
                ])
            )
            ->assertRedirect();

        $state = IntegrationProviderState::query()
            ->where('provider_code', 'NOWPAYMENTS')
            ->firstOrFail();

        $this->assertTrue($state->is_enabled);
        $this->assertSame(['*'], $state->countries);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.integrations.show', 'NOWPAYMENTS'))
            ->assertOk()
            ->assertSee('READY')
            ->assertSee('All Countries')
            ->assertSee('APPROVED_EXISTING_RUNTIME')
            ->assertSee('ACTIVE_EXISTING_RUNTIME');
    }

    public function test_active_database_bank_account_allows_local_bank_integration_to_be_enabled(): void
    {
        config()->set('tradex_payments.local_bank.enabled', true);
        config()->set('tradex_payments.local_bank.countries', ['LK']);
        config()->set('tradex_payments.product.education_premium.local_bank.amount_minor', 0);
        config()->set('tradex_payments.local_bank.bank_name', null);
        config()->set('tradex_payments.local_bank.branch', null);
        config()->set('tradex_payments.local_bank.account_holder', null);
        config()->set('tradex_payments.local_bank.account_number', null);

        PaymentReceivingAccount::query()->create([
            'type' => PaymentReceivingAccount::TYPE_BANK,
            'display_name' => 'Primary Local Bank',
            'country_code' => 'LK',
            'currency' => 'LKR',
            'tender_amount_decimal' => '25000.00',
            'amount_scale' => 2,
            'bank_name' => 'TradeX Test Bank',
            'bank_branch' => 'Colombo',
            'account_holder' => 'TradeX',
            'account_number' => '001234567890',
            'is_active' => true,
        ]);

        $admin = $this->adminWithPermissions([
            'integrations.view',
            'integrations.manage',
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.integrations.update', 'LOCAL_BANK'),
                $this->updatePayload([
                    'is_enabled' => true,
                    'eligibility_confirmed' => true,
                    'environment' => 'MANUAL',
                    'countries' => 'LK',
                ])
            )
            ->assertRedirect();

        $this->assertDatabaseHas('integration_provider_states', [
            'provider_code' => 'LOCAL_BANK',
            'is_enabled' => true,
            'environment' => 'MANUAL',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.integrations.show', 'LOCAL_BANK'))
            ->assertOk()
            ->assertSee('READY')
            ->assertSee('Active payment receiving account configured');
    }

    public function test_nowpayments_api_and_ipn_secrets_are_never_rendered(): void
    {
        $apiCanary =
            'NOW-API-CANARY-ADMIN16';

        $ipnCanary =
            'NOW-IPN-CANARY-ADMIN16';

        config()->set(
            'tradex_integrations.nowpayments.api_key',
            $apiCanary
        );

        config()->set(
            'tradex_integrations.nowpayments.ipn_secret',
            $ipnCanary
        );

        $admin =
            $this->adminWithPermissions([
                'integrations.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.integrations.show',
                    'NOWPAYMENTS'
                )
            )
            ->assertOk()
            ->assertDontSee(
                $apiCanary
            )
            ->assertDontSee(
                $ipnCanary
            )
            ->assertSee(
                'API key configured'
            )
            ->assertSee(
                'IPN secret configured'
            );
    }

    public function test_stripe_secret_values_are_never_rendered(): void
    {
        $publishable =
            'pk_test_ADMIN16_CANARY';

        $secret =
            'sk_test_ADMIN16_SECRET_CANARY';

        $webhook =
            'whsec_ADMIN16_WEBHOOK_CANARY';

        config()->set(
            'tradex_integrations.stripe.publishable_key',
            $publishable
        );

        config()->set(
            'tradex_integrations.stripe.secret_key',
            $secret
        );

        config()->set(
            'tradex_integrations.stripe.webhook_secret',
            $webhook
        );

        $admin =
            $this->adminWithPermissions([
                'integrations.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.integrations.show',
                    'STRIPE'
                )
            )
            ->assertOk()
            ->assertDontSee(
                $publishable
            )
            ->assertDontSee(
                $secret
            )
            ->assertDontSee(
                $webhook
            );
    }

    public function test_local_bank_account_number_is_never_rendered(): void
    {
        $accountCanary =
            '00998877665544332211';

        config()->set(
            'tradex_payments.product.education_premium.local_bank.amount_minor',
            1500000
        );

        config()->set(
            'tradex_payments.local_bank.bank_name',
            'Admin 16 Test Bank'
        );

        config()->set(
            'tradex_payments.local_bank.branch',
            'Colombo'
        );

        config()->set(
            'tradex_payments.local_bank.account_holder',
            'TradeX Test'
        );

        config()->set(
            'tradex_payments.local_bank.account_number',
            $accountCanary
        );

        $admin =
            $this->adminWithPermissions([
                'integrations.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.integrations.show',
                    'LOCAL_BANK'
                )
            )
            ->assertOk()
            ->assertDontSee(
                $accountCanary
            )
            ->assertSee(
                'Account number configured'
            );
    }

    public function test_local_bank_only_accepts_manual_environment(): void
    {
        $admin =
            $this->adminWithPermissions([
                'integrations.view',
                'integrations.manage',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.integrations.update',
                    'LOCAL_BANK'
                ),
                $this->updatePayload([
                    'environment' => 'LIVE',
                ])
            )
            ->assertStatus(422);
    }

    public function test_stale_version_is_rejected(): void
    {
        $admin =
            $this->adminWithPermissions([
                'integrations.view',
                'integrations.manage',
            ]);

        IntegrationProviderState::query()
            ->create([
                'provider_code' => 'STRIPE',

                'is_enabled' => false,

                'environment' => 'SANDBOX',

                'countries' => ['LK'],

                'eligibility_confirmed' => false,

                'version' => 2,

                'updated_by_admin_id' => $admin->id,
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.integrations.update',
                    'STRIPE'
                ),
                $this->updatePayload([
                    'version' => 1,
                ])
            )
            ->assertStatus(409);

        $this->assertSame(
            2,
            IntegrationProviderState::query()
                ->where(
                    'provider_code',
                    'STRIPE'
                )
                ->value(
                    'version'
                )
        );
    }

    public function test_update_audit_contains_safe_state_only(): void
    {
        $secretCanary =
            'sk_test_STRIPE-SECRET-CANARY-DO-NOT-AUDIT';

        $this->configureStripe();

        config()->set(
            'tradex_integrations.stripe.publishable_key',
            'pk_test_admin16'
        );

        config()->set(
            'tradex_integrations.stripe.secret_key',
            $secretCanary
        );

        config()->set(
            'tradex_integrations.stripe.webhook_secret',
            'whsec_admin16'
        );

        $admin =
            $this->adminWithPermissions([
                'integrations.view',
                'integrations.manage',
            ]);

        $reasonCanary =
            'REASON-CANARY-ADMIN16-DO-NOT-COPY';

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.integrations.update',
                    'STRIPE'
                ),
                $this->updatePayload([
                    'is_enabled' => true,

                    'eligibility_confirmed' => true,

                    'change_reason' => $reasonCanary,
                ])
            )
            ->assertRedirect();

        $audit =
            AuditLog::query()
                ->where(
                    'action',
                    'INTEGRATION_PROVIDER_STATE_UPDATED'
                )
                ->latest('id')
                ->firstOrFail();

        $json =
            json_encode(
                $audit->new_values,
                JSON_THROW_ON_ERROR
            );

        $this->assertStringNotContainsString(
            $secretCanary,
            $json
        );

        $this->assertStringNotContainsString(
            $reasonCanary,
            $json
        );

        $this->assertStringContainsString(
            'STRIPE',
            $json
        );
    }

    public function test_unexpected_secret_fields_are_not_persisted(): void
    {
        $admin =
            $this->adminWithPermissions([
                'integrations.view',
                'integrations.manage',
            ]);

        $payload =
            $this->updatePayload();

        $payload['secret_key'] =
            'MUST-NOT-PERSIST';

        $payload['webhook_secret'] =
            'MUST-NOT-PERSIST-EITHER';

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.integrations.update',
                    'STRIPE'
                ),
                $payload
            )
            ->assertRedirect();

        $state =
            IntegrationProviderState::query()
                ->where(
                    'provider_code',
                    'STRIPE'
                )
                ->firstOrFail()
                ->toArray();

        $this->assertArrayNotHasKey(
            'secret_key',
            $state
        );

        $this->assertArrayNotHasKey(
            'webhook_secret',
            $state
        );
    }

    public function test_integration_pages_send_no_store_headers(): void
    {
        $admin =
            $this->adminWithPermissions([
                'integrations.view',
            ]);

        $response =
            $this->actingAs(
                $admin,
                'admin'
            )
                ->get(
                    route(
                        'admin.integrations.index'
                    )
                )
                ->assertOk()
                ->assertHeader(
                    'Referrer-Policy',
                    'no-referrer'
                )
                ->assertHeader(
                    'X-Content-Type-Options',
                    'nosniff'
                );

        $this->assertStringContainsString(
            'no-store',
            (string) $response
                ->headers
                ->get(
                    'Cache-Control'
                )
        );
    }

    public function test_admin_16_has_exactly_three_routes_and_no_delete_route(): void
    {
        $routes =
            collect(
                app('router')
                    ->getRoutes()
                    ->getRoutes()
            )
                ->filter(
                    fn ($route): bool => str_starts_with(
                        (string) $route->uri(),
                        'admin/integrations'
                    )
                );

        $this->assertCount(
            3,
            $routes
        );

        foreach (
            $routes as $route
        ) {
            $this->assertNotContains(
                'DELETE',
                $route->methods()
            );
        }
    }

    private function configureStripe(): void
    {
        config()->set(
            'tradex_integrations.stripe.environment',
            'SANDBOX'
        );

        config()->set(
            'tradex_integrations.stripe.publishable_key',
            'pk_test_admin16'
        );

        config()->set(
            'tradex_integrations.stripe.secret_key',
            'sk_test_admin16'
        );

        config()->set(
            'tradex_integrations.stripe.webhook_secret',
            'whsec_admin16'
        );

        config()->set(
            'tradex_integrations.stripe.base_url',
            'https://api.stripe.com'
        );

        config()->set(
            'tradex_integrations.stripe.success_url',
            'https://tradex.example.test/payments/stripe/success'
        );

        config()->set(
            'tradex_integrations.stripe.cancel_url',
            'https://tradex.example.test/payments/stripe/cancel'
        );
    }

    private function updatePayload(
        array $overrides = []
    ): array {
        return array_merge(
            [
                'is_enabled' => false,

                'eligibility_confirmed' => false,

                'environment' => 'SANDBOX',

                'countries' => 'LK',

                'version' => 0,

                'change_reason' => 'ADMIN-16 test change reason.',
            ],
            $overrides
        );
    }

    private function adminWithPermissions(
        array $codes
    ): AdminUser {
        $admin =
            AdminUser::query()
                ->create([
                    'uuid' => Str::uuid()
                        ->toString(),

                    'name' => 'ADMIN-16 Test Actor',

                    'email' => 'admin16-'
                        .Str::lower(
                            Str::random(10)
                        )
                        .'@example.test',

                    'email_verified_at' => now(),

                    'password' => 'Testing-Admin-Pass-123!',

                    'is_active' => true,

                    'failed_login_attempts' => 0,
                ]);

        $role =
            AdminRole::query()
                ->create([
                    'code' => 'ADMIN16_TEST_'
                        .Str::upper(
                            Str::random(8)
                        ),

                    'name' => 'ADMIN-16 Test Role',

                    'description' => null,

                    'is_system' => false,

                    'is_active' => true,
                ]);

        $permissionIds = [];

        foreach (
            $codes as $code
        ) {
            $permission =
                AdminPermission::query()
                    ->where(
                        'code',
                        $code
                    )
                    ->firstOrFail();

            $permissionIds[] =
                $permission->id;
        }

        $role->permissions()->sync(
            $permissionIds
        );

        $admin->roles()->sync([
            $role->id,
        ]);

        return $admin->fresh(
            'roles'
        );
    }
}
