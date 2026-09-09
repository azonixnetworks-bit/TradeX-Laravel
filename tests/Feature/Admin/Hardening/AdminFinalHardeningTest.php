<?php

namespace Tests\Feature\Admin\Hardening;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Services\Admin\Security\AdminReleaseReadinessService;
use App\Services\Admin\Security\AdminTotpService;
use App\Services\Auth\TotpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AdminFinalHardeningTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * Deterministic valid AES-256 application key for encrypted-cast tests.
         * This is a test-only key and is never a production credential.
         */
        config()->set(
            'app.key',
            'base64:'
                .base64_encode(
                    str_repeat(
                        'H',
                        32
                    )
                )
        );
    }

    public function test_admin_login_page_receives_final_security_headers(): void
    {
        $response =
            $this->get(
                route('admin.login')
            )
                ->assertOk()
                ->assertHeader(
                    'X-Content-Type-Options',
                    'nosniff'
                )
                ->assertHeader(
                    'Referrer-Policy',
                    'no-referrer'
                )
                ->assertHeader(
                    'X-Frame-Options',
                    'DENY'
                )
                ->assertHeader(
                    'Cross-Origin-Opener-Policy',
                    'same-origin'
                )
                ->assertHeader(
                    'Cross-Origin-Resource-Policy',
                    'same-origin'
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

    public function test_admin_csp_is_enabled_by_default(): void
    {
        $response =
            $this->get(
                route('admin.login')
            )
                ->assertOk();

        $csp =
            (string) $response
                ->headers
                ->get(
                    'Content-Security-Policy'
                );

        $this->assertStringContainsString(
            "default-src 'self'",
            $csp
        );

        $this->assertStringContainsString(
            "frame-ancestors 'none'",
            $csp
        );

        $this->assertStringContainsString(
            "object-src 'none'",
            $csp
        );

        $this->assertStringContainsString(
            "https://i.ytimg.com",
            $csp
        );

        $this->assertStringContainsString(
            "script-src 'self' 'unsafe-inline'",
            $csp
        );
    }

    public function test_non_admin_web_surface_does_not_receive_admin_csp(): void
    {
        $response =
            $this->get('/');

        $this->assertNull(
            $response
                ->headers
                ->get(
                    'Content-Security-Policy'
                )
        );
    }

    public function test_admin_https_enforcement_can_block_plain_http(): void
    {
        config()->set(
            'tradex_admin_hardening.transport.require_https',
            true
        );

        $this->get(
            route('admin.login')
        )
            ->assertForbidden()
            ->assertSee(
                'Admin HTTPS is required.'
            );
    }

    public function test_admin_host_allowlist_rejects_unapproved_host(): void
    {
        config()->set(
            'tradex_admin_hardening.transport.allowed_hosts',
            [
                'admin.tradex.example',
            ]
        );

        $this->withServerVariables([
            'HTTP_HOST' => 'evil.example',
        ])
            ->get(
                '/admin/login'
            )
            ->assertStatus(400)
            ->assertSee(
                'Invalid admin host.'
            )
            ->assertDontSee(
                'evil.example'
            );
    }

    public function test_hsts_is_only_added_for_secure_admin_requests(): void
    {
        config()->set(
            'tradex_admin_hardening.transport.hsts_enabled',
            true
        );

        $this->get(
            route('admin.login')
        )
            ->assertOk()
            ->assertHeaderMissing(
                'Strict-Transport-Security'
            );

        $this->get(
            'https://localhost/admin/login'
        )
            ->assertOk()
            ->assertHeader(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
    }

    public function test_admin_totp_schema_exists(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'admin_users',
                [
                    'totp_secret',
                    'totp_setup_at',
                    'totp_confirmed_at',
                    'totp_last_used_step',
                ]
            )
        );
    }

    public function test_admin_totp_secret_is_encrypted_at_rest_and_hidden(): void
    {
        $admin =
            $this->admin();

        $secret =
            'JBSWY3DPEHPK3PXP';

        $admin->forceFill([
            'totp_secret' =>
                $secret,
        ])->save();

        $raw =
            DB::table(
                'admin_users'
            )
                ->where(
                    'id',
                    $admin->id
                )
                ->value(
                    'totp_secret'
                );

        $this->assertNotSame(
            $secret,
            $raw
        );

        $this->assertSame(
            $secret,
            $admin
                ->fresh()
                ->totp_secret
        );

        $this->assertArrayNotHasKey(
            'totp_secret',
            $admin
                ->fresh()
                ->toArray()
        );
    }

    public function test_totp_settings_require_admin_auth(): void
    {
        $this->get(
            route(
                'admin.account.totp.settings'
            )
        )->assertRedirect(
            route('admin.login')
        );
    }

    public function test_totp_setup_requires_current_admin_password(): void
    {
        $admin =
            $this->admin();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.account.totp.setup'
                ),
                [
                    'current_password' =>
                        'Wrong-Password-123!',
                ]
            )
            ->assertSessionHasErrors(
                'current_password'
            );

        $this->assertNull(
            $admin
                ->fresh()
                ->totp_secret
        );
    }

    public function test_totp_setup_generates_random_secret_and_safe_audit(): void
    {
        $admin =
            $this->admin();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.account.totp.setup'
                ),
                [
                    'current_password' =>
                        'Valid-Admin-Password-123!',
                ]
            )
            ->assertRedirect(
                route(
                    'admin.account.totp.settings'
                )
            );

        $admin->refresh();

        $this->assertNotEmpty(
            $admin->totp_secret
        );

        $this->assertNotNull(
            $admin->totp_setup_at
        );

        $audit =
            AuditLog::query()
                ->where(
                    'action',
                    'ADMIN_TOTP_SETUP_STARTED'
                )
                ->latest('id')
                ->firstOrFail();

        $json =
            json_encode(
                $audit->new_values,
                JSON_THROW_ON_ERROR
            );

        $this->assertStringNotContainsString(
            (string) $admin
                ->totp_secret,
            $json
        );
    }

    public function test_unconfirmed_totp_secret_is_visible_only_on_protected_setup_page(): void
    {
        $admin =
            $this->admin();

        $secret =
            'JBSWY3DPEHPK3PXP';

        $admin->forceFill([
            'totp_secret' =>
                $secret,

            'totp_setup_at' =>
                now(),
        ])->save();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.account.totp.settings'
                )
            )
            ->assertOk()
            ->assertSee(
                $secret
            )
            ->assertSee(
                'otpauth://totp/',
                false
            );
    }

    public function test_totp_setup_can_be_confirmed_with_current_code(): void
    {
        $admin =
            $this->admin();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.account.totp.setup'
                ),
                [
                    'current_password' =>
                        'Valid-Admin-Password-123!',
                ]
            )
            ->assertRedirect();

        $admin->refresh();

        $code =
            app(
                TotpService::class
            )->codeAt(
                (string) $admin
                    ->totp_secret,
                time()
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.account.totp.confirm'
                ),
                [
                    'current_password' =>
                        'Valid-Admin-Password-123!',

                    'code' =>
                        $code,
                ]
            )
            ->assertRedirect(
                route(
                    'admin.account.totp.settings'
                )
            );

        $admin->refresh();

        $this->assertNotNull(
            $admin->totp_confirmed_at
        );

        $this->assertTrue(
            $admin->hasConfirmedTotp()
        );
    }

    public function test_confirmed_totp_secret_is_not_rendered_on_settings_page(): void
    {
        $admin =
            $this->enrolledAdmin();

        $secret =
            (string) $admin
                ->totp_secret;

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.account.totp.settings'
                )
            )
            ->assertOk()
            ->assertDontSee(
                $secret
            )
            ->assertSee(
                'TOTP Enabled'
            );
    }

    public function test_totp_setup_expiry_requires_restart(): void
    {
        $admin =
            $this->admin();

        $secret =
            'JBSWY3DPEHPK3PXP';

        $admin->forceFill([
            'totp_secret' =>
                $secret,

            'totp_setup_at' =>
                now()->subMinutes(
                    20
                ),
        ])->save();

        config()->set(
            'tradex_admin_hardening.totp.setup_window_minutes',
            15
        );

        $code =
            app(
                TotpService::class
            )->codeAt(
                $secret,
                time()
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.account.totp.confirm'
                ),
                [
                    'current_password' =>
                        'Valid-Admin-Password-123!',

                    'code' =>
                        $code,
                ]
            )
            ->assertSessionHasErrors(
                'code'
            );

        $this->assertNull(
            $admin
                ->fresh()
                ->totp_confirmed_at
        );
    }

    public function test_expired_unconfirmed_secret_is_not_rendered_and_setup_can_restart(): void
    {
        $admin =
            $this->admin();

        $secret =
            'JBSWY3DPEHPK3PXP';

        $admin->forceFill([
            'totp_secret' =>
                $secret,

            'totp_setup_at' =>
                now()->subMinutes(
                    20
                ),
        ])->save();

        config()->set(
            'tradex_admin_hardening.totp.setup_window_minutes',
            15
        );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.account.totp.settings'
                )
            )
            ->assertOk()
            ->assertDontSee(
                $secret
            )
            ->assertSee(
                'Generate TOTP Secret'
            );
    }

    public function test_default_rollout_mode_preserves_existing_password_login(): void
    {
        config()->set(
            'tradex_admin_hardening.totp.required',
            false
        );

        $admin =
            $this->admin();

        $this->post(
            route(
                'admin.login.submit'
            ),
            [
                'email' =>
                    $admin->email,

                'password' =>
                    'Valid-Admin-Password-123!',
            ]
        )
            ->assertRedirect(
                route(
                    'admin.dashboard'
                )
            );

        $this->assertAuthenticatedAs(
            $admin,
            'admin'
        );
    }

    public function test_five_invalid_password_attempts_lock_the_admin_account(): void
    {
        config()->set(
            'tradex_admin_hardening.totp.required',
            false
        );

        $admin =
            $this->admin();

        for (
            $attempt = 1;
            $attempt <= 5;
            $attempt++
        ) {
            $this->post(
                route(
                    'admin.login.submit'
                ),
                [
                    'email' =>
                        $admin->email,

                    'password' =>
                        'Definitely-Wrong-Password!',
                ]
            )
                ->assertSessionHasErrors(
                    'email'
                );
        }

        $admin->refresh();

        $this->assertSame(
            5,
            $admin->failed_login_attempts
        );

        $this->assertTrue(
            $admin->isLocked()
        );
    }

    public function test_required_totp_unenrolled_password_login_enters_restricted_enrollment_session(): void
    {
        config()->set(
            'tradex_admin_hardening.totp.required',
            true
        );

        $admin =
            $this->admin();

        $this->post(
            route(
                'admin.login.submit'
            ),
            [
                'email' =>
                    $admin->email,

                'password' =>
                    'Valid-Admin-Password-123!',
            ]
        )
            ->assertRedirect(
                route(
                    'admin.account.totp.settings'
                )
            )
            ->assertSessionHas(
                'tradex_admin_totp_enrollment_only',
                true
            )
            ->assertSessionMissing(
                'tradex_admin_totp_authenticated_at'
            );

        $this->assertAuthenticatedAs(
            $admin,
            'admin'
        );

        $this->get(
            route(
                'admin.dashboard'
            )
        )
            ->assertRedirect(
                route(
                    'admin.account.totp.settings'
                )
            );
    }

    public function test_required_totp_enrollment_confirmation_promotes_restricted_session(): void
    {
        config()->set(
            'tradex_admin_hardening.totp.required',
            true
        );

        $admin =
            $this->admin();

        $this->post(
            route(
                'admin.login.submit'
            ),
            [
                'email' =>
                    $admin->email,

                'password' =>
                    'Valid-Admin-Password-123!',
            ]
        )
            ->assertRedirect(
                route(
                    'admin.account.totp.settings'
                )
            );

        $this->post(
            route(
                'admin.account.totp.setup'
            ),
            [
                'current_password' =>
                    'Valid-Admin-Password-123!',
            ]
        )
            ->assertRedirect(
                route(
                    'admin.account.totp.settings'
                )
            );

        $admin->refresh();

        $code =
            app(
                TotpService::class
            )->codeAt(
                (string) $admin
                    ->totp_secret,
                time()
            );

        $this->post(
            route(
                'admin.account.totp.confirm'
            ),
            [
                'current_password' =>
                    'Valid-Admin-Password-123!',

                'code' =>
                    $code,
            ]
        )
            ->assertRedirect(
                route(
                    'admin.dashboard'
                )
            )
            ->assertSessionMissing(
                'tradex_admin_totp_enrollment_only'
            )
            ->assertSessionHas(
                'tradex_admin_totp_authenticated_at'
            );

        $this->assertAuthenticatedAs(
            $admin,
            'admin'
        );

        /*
         * MFA promotion authenticates the session, but ADMIN-RBAC-02 must not
         * grant Dashboard access to this role-less hardening fixture.
         */
        $this->get(
            route(
                'admin.dashboard'
            )
        )->assertForbidden();
    }

    public function test_required_totp_password_stage_creates_pending_challenge_not_admin_session(): void
    {
        config()->set(
            'tradex_admin_hardening.totp.required',
            true
        );

        $admin =
            $this->enrolledAdmin();

        $this->post(
            route(
                'admin.login.submit'
            ),
            [
                'email' =>
                    $admin->email,

                'password' =>
                    'Valid-Admin-Password-123!',
            ]
        )
            ->assertRedirect(
                route(
                    'admin.totp.challenge'
                )
            )
            ->assertSessionHas(
                'tradex_admin_pending_login_id',
                $admin->id
            );

        $this->assertGuest(
            'admin'
        );
    }

    public function test_valid_totp_challenge_creates_authenticated_admin_session(): void
    {
        config()->set(
            'tradex_admin_hardening.totp.required',
            true
        );

        $admin =
            $this->enrolledAdmin();

        $this->post(
            route(
                'admin.login.submit'
            ),
            [
                'email' =>
                    $admin->email,

                'password' =>
                    'Valid-Admin-Password-123!',
            ]
        )
            ->assertRedirect(
                route(
                    'admin.totp.challenge'
                )
            );

        $admin->refresh();

        $code =
            app(
                TotpService::class
            )->codeAt(
                (string) $admin
                    ->totp_secret,
                time()
            );

        $this->post(
            route(
                'admin.totp.challenge.submit'
            ),
            [
                'code' =>
                    $code,
            ]
        )
            ->assertRedirect(
                route(
                    'admin.dashboard'
                )
            )
            ->assertSessionHas(
                'tradex_admin_totp_authenticated_at'
            );

        $this->assertAuthenticatedAs(
            $admin,
            'admin'
        );
    }

    public function test_totp_code_replay_is_rejected_transactionally(): void
    {
        $admin =
            $this->enrolledAdmin();

        $code =
            app(
                TotpService::class
            )->codeAt(
                (string) $admin
                    ->totp_secret,
                time()
            );

        $service =
            app(
                AdminTotpService::class
            );

        $service->consumeLoginCode(
            $admin,
            $code
        );

        $this->expectException(
            ValidationException::class
        );

        $service->consumeLoginCode(
            $admin->fresh(),
            $code
        );
    }

    public function test_expired_pending_totp_challenge_returns_to_login(): void
    {
        $admin =
            $this->enrolledAdmin();

        $this->withSession([
            'tradex_admin_pending_login_id' =>
                $admin->id,

            'tradex_admin_pending_started_at' =>
                now()
                    ->subMinutes(10)
                    ->timestamp,
        ])
            ->get(
                route(
                    'admin.totp.challenge'
                )
            )
            ->assertRedirect(
                route(
                    'admin.login'
                )
            );
    }

    public function test_required_totp_forces_existing_unverified_session_to_reauthenticate(): void
    {
        config()->set(
            'tradex_admin_hardening.totp.required',
            true
        );

        $admin =
            $this->enrolledAdmin();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get('/admin')
            ->assertRedirect(
                route(
                    'admin.login'
                )
            );

        $this->assertGuest(
            'admin'
        );
    }

    public function test_required_totp_allows_session_with_second_factor_marker(): void
    {
        config()->set(
            'tradex_admin_hardening.totp.required',
            true
        );

        $admin =
            $this->enrolledAdmin();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->withSession([
                'tradex_admin_totp_authenticated_at' =>
                    now()->timestamp,
            ])
            ->get('/admin')
            ->assertRedirect(
                route(
                    'admin.dashboard'
                )
            );
    }

    public function test_required_totp_redirects_unenrolled_authenticated_admin_to_setup(): void
    {
        config()->set(
            'tradex_admin_hardening.totp.required',
            true
        );

        $admin =
            $this->admin();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get('/admin')
            ->assertRedirect(
                route(
                    'admin.account.totp.settings'
                )
            );
    }

    public function test_idle_admin_session_expires_and_is_audited(): void
    {
        config()->set(
            'tradex_admin_hardening.session.idle_timeout_minutes',
            30
        );

        $admin =
            $this->admin();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->withSession([
                'tradex_admin_session_started_at' =>
                    now()
                        ->subMinutes(40)
                        ->timestamp,

                'tradex_admin_last_activity_at' =>
                    now()
                        ->subMinutes(31)
                        ->timestamp,
            ])
            ->get(
                route(
                    'admin.account.totp.settings'
                )
            )
            ->assertRedirect(
                route(
                    'admin.login'
                )
            );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'actor_type' =>
                    'ADMIN',

                'actor_id' =>
                    $admin->id,

                'action' =>
                    'ADMIN_SESSION_EXPIRED',
            ]
        );
    }

    public function test_absolute_admin_session_timeout_is_enforced(): void
    {
        config()->set(
            'tradex_admin_hardening.session.idle_timeout_minutes',
            30
        );

        config()->set(
            'tradex_admin_hardening.session.absolute_timeout_minutes',
            480
        );

        $admin =
            $this->admin();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->withSession([
                'tradex_admin_session_started_at' =>
                    now()
                        ->subMinutes(481)
                        ->timestamp,

                'tradex_admin_last_activity_at' =>
                    now()->timestamp,
            ])
            ->get(
                route(
                    'admin.account.totp.settings'
                )
            )
            ->assertRedirect(
                route(
                    'admin.login'
                )
            );

        $this->assertGuest(
            'admin'
        );
    }

    public function test_baseline_release_readiness_can_pass_with_safe_test_configuration(): void
    {
        $this->safeBaselineConfig();

        $result =
            app(
                AdminReleaseReadinessService::class
            )->evaluate(
                false
            );

        $this->assertTrue(
            $result['ready']
        );

        $this->assertSame(
            0,
            $result[
                'failed_critical'
            ]
        );
    }

    public function test_production_release_readiness_fails_when_production_gates_are_not_enabled(): void
    {
        $this->safeBaselineConfig();

        config()->set(
            'tradex_admin_hardening.totp.required',
            false
        );

        $result =
            app(
                AdminReleaseReadinessService::class
            )->evaluate(
                true
            );

        $this->assertFalse(
            $result['ready']
        );

        $this->assertGreaterThan(
            0,
            $result[
                'failed_critical'
            ]
        );
    }

    public function test_production_release_readiness_passes_after_all_critical_gates_and_enrollment(): void
    {
        $this->safeBaselineConfig();

        $this->app[
            'env'
        ] = 'production';

        config()->set(
            'app.debug',
            false
        );

        config()->set(
            'app.url',
            'https://admin.tradex.example'
        );

        config()->set(
            'session.secure',
            true
        );

        config()->set(
            'session.encrypt',
            true
        );

        config()->set(
            'tradex_admin_hardening.transport.allowed_hosts',
            [
                'admin.tradex.example',
            ]
        );

        config()->set(
            'tradex_admin_hardening.transport.require_https',
            true
        );

        config()->set(
            'tradex_admin_hardening.transport.hsts_enabled',
            true
        );

        config()->set(
            'tradex_admin_hardening.totp.required',
            true
        );

        $this->enrolledAdmin();

        $result =
            app(
                AdminReleaseReadinessService::class
            )->evaluate(
                true
            );

        $this->assertTrue(
            $result['ready']
        );

        $this->assertSame(
            0,
            $result[
                'failed_critical'
            ]
        );
    }

    public function test_production_release_readiness_detects_active_unenrolled_admin(): void
    {
        $this->safeBaselineConfig();

        $this->app[
            'env'
        ] = 'production';

        config()->set(
            'app.debug',
            false
        );

        config()->set(
            'app.url',
            'https://admin.tradex.example'
        );

        config()->set(
            'session.secure',
            true
        );

        config()->set(
            'session.encrypt',
            true
        );

        config()->set(
            'tradex_admin_hardening.transport.allowed_hosts',
            [
                'admin.tradex.example',
            ]
        );

        config()->set(
            'tradex_admin_hardening.transport.require_https',
            true
        );

        config()->set(
            'tradex_admin_hardening.transport.hsts_enabled',
            true
        );

        config()->set(
            'tradex_admin_hardening.totp.required',
            true
        );

        $this->admin();

        $result =
            app(
                AdminReleaseReadinessService::class
            )->evaluate(
                true
            );

        $enrollmentCheck =
            collect(
                $result['checks']
            )
                ->firstWhere(
                    'name',
                    'Active admin TOTP enrollment'
                );

        $this->assertFalse(
            $enrollmentCheck[
                'passed'
            ]
        );
    }

    public function test_baseline_release_check_command_is_registered_and_can_pass(): void
    {
        $this->safeBaselineConfig();

        $this->artisan(
            'tradex:admin-release-check'
        )->assertExitCode(0);
    }

    public function test_production_release_check_command_fails_closed_when_gates_fail(): void
    {
        $this->safeBaselineConfig();

        config()->set(
            'tradex_admin_hardening.totp.required',
            false
        );

        $this->artisan(
            'tradex:admin-release-check --production'
        )->assertExitCode(1);
    }

    public function test_admin_19_registers_exactly_five_totp_routes_and_no_delete_route(): void
    {
        $routes =
            collect(
                Route::getRoutes()
                    ->getRoutes()
            )
                ->filter(
                    function (
                        $route
                    ): bool {
                        $name =
                            (string) $route
                                ->getName();

                        return str_starts_with(
                            $name,
                            'admin.totp.'
                        )
                        || str_starts_with(
                            $name,
                            'admin.account.totp.'
                        );
                    }
                );

        $this->assertCount(
            5,
            $routes
        );

        foreach (
            $routes
            as $route
        ) {
            $this->assertNotContains(
                'DELETE',
                $route->methods()
            );
        }
    }

    public function test_admin_19_has_no_self_service_totp_disable_route(): void
    {
        $names =
            collect(
                Route::getRoutes()
                    ->getRoutes()
            )
                ->map(
                    fn ($route): ?string =>
                        $route->getName()
                )
                ->filter();

        $this->assertFalse(
            $names->contains(
                fn (string $name): bool =>
                    str_contains(
                        $name,
                        'totp.disable'
                    )
                    || str_contains(
                        $name,
                        'totp.reset'
                    )
            )
        );
    }

    private function admin(): AdminUser
    {
        return AdminUser::query()
            ->create([
                'uuid' =>
                    Str::uuid()
                        ->toString(),

                'name' =>
                    'ADMIN-19 Test Admin',

                'email' =>
                    'admin19-'
                    .Str::lower(
                        Str::random(10)
                    )
                    .'@example.test',

                'email_verified_at' =>
                    now(),

                'password' =>
                    'Valid-Admin-Password-123!',

                'is_active' =>
                    true,

                'failed_login_attempts' =>
                    0,
            ]);
    }

    private function enrolledAdmin(): AdminUser
    {
        $admin =
            $this->admin();

        $admin->forceFill([
            'totp_secret' =>
                'JBSWY3DPEHPK3PXP',

            'totp_setup_at' =>
                null,

            'totp_confirmed_at' =>
                now(),

            'totp_last_used_step' =>
                null,
        ])->save();

        return $admin->fresh();
    }

    private function safeBaselineConfig(): void
    {
        config()->set(
            'session.driver',
            'database'
        );

        config()->set(
            'session.http_only',
            true
        );

        config()->set(
            'session.same_site',
            'lax'
        );

        config()->set(
            'session.serialization',
            'json'
        );

        config()->set(
            'tradex_admin_hardening.session.idle_timeout_minutes',
            30
        );

        config()->set(
            'tradex_admin_hardening.session.absolute_timeout_minutes',
            480
        );

        config()->set(
            'tradex_admin_hardening.headers.csp_enabled',
            true
        );

        config()->set(
            'tradex_admin_hardening.headers.content_security_policy',
            "default-src 'self'; "
            ."form-action 'self'; "
            ."frame-ancestors 'none'; "
            ."object-src 'none'; "
            ."script-src 'self';"
        );
    }
}
