<?php

namespace Tests\Feature\Admin\Settings;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\SystemSetting;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SystemSettingsManagementTest extends TestCase
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

    public function test_guest_is_redirected_from_settings(): void
    {
        $this->get(
            route(
                'admin.settings.general'
            )
        )->assertRedirect(
            route('admin.login')
        );
    }

    public function test_settings_view_permission_is_required(): void
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
                    'admin.settings.general'
                )
            )
            ->assertForbidden();
    }

    public function test_settings_home_redirects_authorized_admin_to_general(): void
    {
        $admin =
            $this->adminWithPermissions([
                'settings.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.settings.home'
                )
            )
            ->assertRedirect(
                route(
                    'admin.settings.general'
                )
            );
    }

    public function test_general_page_uses_safe_defaults_without_writing_rows_on_get(): void
    {
        $admin =
            $this->adminWithPermissions([
                'settings.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.settings.general'
                )
            )
            ->assertOk()
            ->assertSee(
                'TradeX OS'
            )
            ->assertSee(
                'SAFE OPERATIONAL REGISTRY'
            );

        $this->assertDatabaseCount(
            'system_settings',
            0
        );
    }

    public function test_settings_manage_alone_is_not_enough_to_update_general_settings(): void
    {
        $admin =
            $this->adminWithPermissions([
                'settings.manage',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.settings.general.update'
                ),
                $this->generalPayload()
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'system_settings',
            0
        );
    }

    public function test_authorized_admin_can_update_allowlisted_general_settings(): void
    {
        $admin =
            $this->adminWithPermissions([
                'settings.view',
                'settings.manage',
            ]);

        $payload =
            $this->generalPayload([
                'platform_name' =>
                    'TradeX Global',

                'support_email' =>
                    'support@tradex.test',

                'support_whatsapp' =>
                    '+94771234567',

                'maintenance_notice_enabled' =>
                    '1',

                'maintenance_notice' =>
                    'Scheduled platform maintenance.',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.settings.general.update'
                ),
                $payload
            )
            ->assertRedirect(
                route(
                    'admin.settings.general'
                )
            );

        $this->assertSame(
            'TradeX Global',
            SystemSetting::query()
                ->where(
                    'setting_key',
                    'general.platform_name'
                )
                ->value(
                    'value'
                )
        );

        $this->assertSame(
            '+94771234567',
            SystemSetting::query()
                ->where(
                    'setting_key',
                    'general.support_whatsapp'
                )
                ->value(
                    'value'
                )
        );

        $this->assertDatabaseCount(
            'system_settings',
            5
        );
    }

    public function test_disabling_maintenance_notice_clears_notice_content(): void
    {
        $admin =
            $this->adminWithPermissions([
                'settings.view',
                'settings.manage',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.settings.general.update'
                ),
                $this->generalPayload([
                    'maintenance_notice_enabled' =>
                        '0',

                    'maintenance_notice' =>
                        'THIS MUST NOT REMAIN',
                ])
            )
            ->assertRedirect();

        $this->assertNull(
            SystemSetting::query()
                ->where(
                    'setting_key',
                    'general.maintenance_notice'
                )
                ->value(
                    'value'
                )
        );
    }

    public function test_arbitrary_request_keys_are_not_created_as_system_settings(): void
    {
        $admin =
            $this->adminWithPermissions([
                'settings.view',
                'settings.manage',
            ]);

        $payload =
            $this->generalPayload();

        $payload[
            'paypal_client_secret'
        ] =
            'MUST-NOT-BE-STORED';

        $payload[
            'app_key'
        ] =
            'MUST-NOT-BE-STORED';

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.settings.general.update'
                ),
                $payload
            )
            ->assertRedirect();

        $this->assertDatabaseMissing(
            'system_settings',
            [
                'setting_key' =>
                    'paypal_client_secret',
            ]
        );

        $this->assertDatabaseMissing(
            'system_settings',
            [
                'setting_key' =>
                    'app_key',
            ]
        );
    }

    public function test_general_update_audit_does_not_copy_submitted_contact_or_notice_values(): void
    {
        $admin =
            $this->adminWithPermissions([
                'settings.view',
                'settings.manage',
            ]);

        $emailCanary =
            'secret-contact-canary@example.test';

        $whatsappCanary =
            '+94770000001';

        $noticeCanary =
            'PRIVATE-NOTICE-CANARY';

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.settings.general.update'
                ),
                $this->generalPayload([
                    'support_email' =>
                        $emailCanary,

                    'support_whatsapp' =>
                        $whatsappCanary,

                    'maintenance_notice_enabled' =>
                        '1',

                    'maintenance_notice' =>
                        $noticeCanary,
                ])
            )
            ->assertRedirect();

        $audit =
            AuditLog::query()
                ->where(
                    'action',
                    'SYSTEM_SETTINGS_UPDATED'
                )
                ->latest('id')
                ->firstOrFail();

        $json = json_encode(
            $audit->new_values,
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            $emailCanary,
            $json
        );

        $this->assertStringNotContainsString(
            $whatsappCanary,
            $json
        );

        $this->assertStringNotContainsString(
            $noticeCanary,
            $json
        );

        $this->assertStringContainsString(
            'changed_keys',
            $json
        );
    }

    public function test_stale_general_setting_versions_are_rejected_with_conflict(): void
    {
        $admin =
            $this->adminWithPermissions([
                'settings.view',
                'settings.manage',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.settings.general.update'
                ),
                $this->generalPayload([
                    'platform_name' =>
                        'TradeX First Save',
                ])
            )
            ->assertRedirect();

        /*
         * Reuse version zero for all fields after platform_name has moved to
         * version one. The stale update must be rejected.
         */
        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.settings.general.update'
                ),
                $this->generalPayload([
                    'platform_name' =>
                        'TradeX Stale Save',
                ])
            )
            ->assertStatus(409);

        $this->assertSame(
            'TradeX First Save',
            SystemSetting::query()
                ->where(
                    'setting_key',
                    'general.platform_name'
                )
                ->value(
                    'value'
                )
        );
    }

    public function test_payments_settings_page_never_renders_provider_or_destination_secrets(): void
    {
        $admin =
            $this->adminWithPermissions([
                'settings.view',
            ]);

        config()->set(
            'tradex_payments.paypal.client_id',
            'PAYPAL-CLIENT-CANARY'
        );

        config()->set(
            'tradex_payments.paypal.client_secret',
            'PAYPAL-SECRET-CANARY'
        );

        config()->set(
            'tradex_payments.paypal.webhook_id',
            'PAYPAL-WEBHOOK-CANARY'
        );

        config()->set(
            'tradex_payments.local_bank.account_number',
            'BANK-ACCOUNT-CANARY'
        );

        config()->set(
            'tradex_payments.usdt_trc20.wallet_address',
            'USDT-WALLET-CANARY'
        );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.settings.payments'
                )
            )
            ->assertOk()
            ->assertSee(
                'Provider Status'
            )
            ->assertDontSee(
                'PAYPAL-CLIENT-CANARY'
            )
            ->assertDontSee(
                'PAYPAL-SECRET-CANARY'
            )
            ->assertDontSee(
                'PAYPAL-WEBHOOK-CANARY'
            )
            ->assertDontSee(
                'BANK-ACCOUNT-CANARY'
            )
            ->assertDontSee(
                'USDT-WALLET-CANARY'
            );
    }

    public function test_video_settings_page_never_renders_binary_or_private_work_paths(): void
    {
        $admin =
            $this->adminWithPermissions([
                'settings.view',
            ]);

        config()->set(
            'tradex.media.ffmpeg_binary',
            'FFMPEG-PRIVATE-CANARY'
        );

        config()->set(
            'tradex.media.ffprobe_binary',
            'FFPROBE-PRIVATE-CANARY'
        );

        config()->set(
            'tradex.media.working_directory',
            'PRIVATE-WORK-DIR-CANARY'
        );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.settings.video'
                )
            )
            ->assertOk()
            ->assertSee(
                'Configured HLS Variants'
            )
            ->assertDontSee(
                'FFMPEG-PRIVATE-CANARY'
            )
            ->assertDontSee(
                'FFPROBE-PRIVATE-CANARY'
            )
            ->assertDontSee(
                'PRIVATE-WORK-DIR-CANARY'
            );
    }

    public function test_security_settings_page_does_not_render_application_key(): void
    {
        $admin =
            $this->adminWithPermissions([
                'settings.view',
            ]);

        $appKeyCanary =
            'base64:'
            .base64_encode(
                str_repeat(
                    'K',
                    32
                )
            );

        config()->set(
            'app.key',
            $appKeyCanary
        );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.settings.security'
                )
            )
            ->assertOk()
            ->assertSee(
                'READ-ONLY SECURITY POSTURE'
            )
            ->assertDontSee(
                $appKeyCanary
            );
    }

    public function test_affiliate_settings_page_is_read_only_ownership_view(): void
    {
        $admin =
            $this->adminWithPermissions([
                'settings.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.settings.affiliate'
                )
            )
            ->assertOk()
            ->assertSee(
                'ADMIN-06 Affiliate Management'
            )
            ->assertSee(
                'Read-only here'
            );
    }

    public function test_settings_pages_send_no_store_headers(): void
    {
        $admin =
            $this->adminWithPermissions([
                'settings.view',
            ]);

        $response =
            $this->actingAs(
                $admin,
                'admin'
            )
                ->get(
                    route(
                        'admin.settings.general'
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

    public function test_admin_14_has_only_one_settings_mutation_route(): void
    {
        $routes = collect(
            app('router')
                ->getRoutes()
                ->getRoutes()
        )
            ->filter(
                fn ($route): bool =>
                    str_starts_with(
                        (string) $route
                            ->uri(),
                        'admin/settings'
                    )
            );

        $this->assertCount(
            7,
            $routes
        );

        $mutationRoutes =
            $routes->filter(
                fn ($route): bool =>
                    collect(
                        $route->methods()
                    )->contains(
                        fn (string $method): bool =>
                            in_array(
                                $method,
                                [
                                    'POST',
                                    'PUT',
                                    'PATCH',
                                    'DELETE',
                                ],
                                true
                            )
                    )
            );

        $this->assertCount(
            1,
            $mutationRoutes
        );

        $mutation =
            $mutationRoutes->first();

        $this->assertContains(
            'PATCH',
            $mutation->methods()
        );

        $this->assertSame(
            'admin/settings/general',
            $mutation->uri()
        );
    }

    public function test_system_setting_model_uses_only_expected_safe_registry_columns(): void
    {
        $setting =
            new SystemSetting();

        $this->assertSame(
            'system_settings',
            $setting->getTable()
        );

        $this->assertEqualsCanonicalizing(
            [
                'setting_key',
                'group_name',
                'value',
                'value_type',
                'version',
                'updated_by_admin_id',
            ],
            $setting->getFillable()
        );
    }

    private function generalPayload(
        array $overrides = []
    ): array {
        return array_merge(
            [
                'platform_name' =>
                    'TradeX OS',

                'support_email' =>
                    null,

                'support_whatsapp' =>
                    null,

                'maintenance_notice_enabled' =>
                    '0',

                'maintenance_notice' =>
                    null,

                'versions' => [
                    'platform_name' =>
                        0,

                    'support_email' =>
                        0,

                    'support_whatsapp' =>
                        0,

                    'maintenance_notice_enabled' =>
                        0,

                    'maintenance_notice' =>
                        0,
                ],
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
                    'uuid' =>
                        Str::uuid()
                            ->toString(),

                    'name' =>
                        'ADMIN-14 Test Actor',

                    'email' =>
                        'admin14-'
                        .Str::lower(
                            Str::random(10)
                        )
                        .'@example.test',

                    'email_verified_at' =>
                        now(),

                    'password' =>
                        'Testing-Admin-Pass-123!',

                    'is_active' =>
                        true,

                    'failed_login_attempts' =>
                        0,
                ]);

        $role =
            AdminRole::query()
                ->create([
                    'code' =>
                        'ADMIN14_TEST_'
                        .Str::upper(
                            Str::random(8)
                        ),

                    'name' =>
                        'ADMIN-14 Test Role',

                    'description' =>
                        null,

                    'is_system' =>
                        false,

                    'is_active' =>
                        true,
                ]);

        $permissionIds = [];

        foreach ($codes as $code) {
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
