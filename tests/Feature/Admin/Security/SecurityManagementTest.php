<?php

namespace Tests\Feature\Admin\Security;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\UserDevice;
use App\Models\UserRefreshToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class SecurityManagementTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_guest_is_redirected_from_security_home(): void
    {
        $this->get(
            route('admin.security.home')
        )->assertRedirect(
            route('admin.login')
        );
    }

    public function test_security_home_requires_security_permission(): void
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
                    'admin.security.home'
                )
            )
            ->assertForbidden();
    }

    public function test_security_home_prefers_events_when_event_permission_exists(): void
    {
        $admin =
            $this->adminWithPermissions([
                'security.events',
                'security.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.security.home'
                )
            )
            ->assertRedirect(
                route(
                    'admin.security.events'
                )
            );
    }

    public function test_security_home_falls_back_to_sessions_for_view_only_admin(): void
    {
        $admin =
            $this->adminWithPermissions([
                'security.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.security.home'
                )
            )
            ->assertRedirect(
                route(
                    'admin.security.sessions'
                )
            );
    }

    public function test_security_events_permission_is_required(): void
    {
        $admin =
            $this->adminWithPermissions([
                'security.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.security.events'
                )
            )
            ->assertForbidden();
    }

    public function test_security_events_show_curated_metadata_but_not_audit_payload_values(): void
    {
        $admin =
            $this->adminWithPermissions([
                'security.events',
            ]);

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: 'DEVICE_REVOKED',
            module: 'DEVICE_SECURITY',
            targetType: 'USER_DEVICE',
            targetId: 123,
            oldValues: [
                'secret' =>
                    'OLD-SENSITIVE-PAYLOAD',
            ],
            newValues: [
                'reason' =>
                    'NEW-SENSITIVE-PAYLOAD',
            ],
            ipAddress:
                '203.0.113.10',
            userAgent:
                'TradeX Security Test Agent'
        );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.security.events'
                )
            )
            ->assertOk()
            ->assertSee(
                'DEVICE_REVOKED'
            )
            ->assertSee(
                'DEVICE_SECURITY'
            )
            ->assertSee(
                '203.0.113.10'
            )
            ->assertDontSee(
                'OLD-SENSITIVE-PAYLOAD'
            )
            ->assertDontSee(
                'NEW-SENSITIVE-PAYLOAD'
            );
    }

    public function test_generic_non_security_audit_event_is_not_shown(): void
    {
        $admin =
            $this->adminWithPermissions([
                'security.events',
            ]);

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: 'COURSE_TITLE_UPDATED',
            module: 'EDUCATION',
            targetType: 'COURSE',
            targetId: 9
        );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.security.events'
                )
            )
            ->assertOk()
            ->assertDontSee(
                'COURSE_TITLE_UPDATED'
            );
    }

    public function test_sessions_require_security_view_permission(): void
    {
        $admin =
            $this->adminWithPermissions([
                'security.events',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.security.sessions'
                )
            )
            ->assertForbidden();
    }

    public function test_session_inventory_shows_safe_metadata_and_never_token_secrets(): void
    {
        $admin =
            $this->adminWithPermissions([
                'security.view',
            ]);

        [
            $user,
            $device,
            $token,
        ] = $this->activeSession();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.security.sessions'
                )
            )
            ->assertOk()
            ->assertSee(
                $user->email
            )
            ->assertSee(
                'Security Test Phone'
            )
            ->assertSee(
                'ACTIVE'
            )
            ->assertDontSee(
                $token->token_hash
            )
            ->assertDontSee(
                $token->family_uuid
            )
            ->assertDontSee(
                $device
                    ->installation_id_hash
            )
            ->assertDontSee(
                'TEST-DEVICE-PUBLIC-KEY'
            );
    }

    public function test_session_status_filters_distinguish_active_expired_and_revoked(): void
    {
        $admin =
            $this->adminWithPermissions([
                'security.view',
            ]);

        $active =
            $this->activeSession(
                'active-session@example.test'
            );

        $expired =
            $this->activeSession(
                'expired-session@example.test',
                now()->subHour()
            );

        $revoked =
            $this->activeSession(
                'revoked-session@example.test',
                now()->addHour(),
                now()->subMinute()
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.security.sessions',
                    [
                        'status' =>
                            'ACTIVE',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $active[0]->email
            )
            ->assertDontSee(
                $expired[0]->email
            )
            ->assertDontSee(
                $revoked[0]->email
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.security.sessions',
                    [
                        'status' =>
                            'EXPIRED',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $expired[0]->email
            )
            ->assertDontSee(
                $active[0]->email
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.security.sessions',
                    [
                        'status' =>
                            'REVOKED',
                    ]
                )
            )
            ->assertOk()
            ->assertSee(
                $revoked[0]->email
            )
            ->assertDontSee(
                $active[0]->email
            );
    }

    public function test_user_security_link_only_appears_with_user_detail_permission(): void
    {
        $withoutUserAccess =
            $this->adminWithPermissions([
                'security.view',
            ]);

        $withUserAccess =
            $this->adminWithPermissions([
                'security.view',
                'users.view_details',
            ]);

        [
            $user,
        ] = $this->activeSession();

        $this->actingAs(
            $withoutUserAccess,
            'admin'
        )
            ->get(
                route(
                    'admin.security.sessions'
                )
            )
            ->assertOk()
            ->assertDontSee(
                'User Security'
            );

        $this->actingAs(
            $withUserAccess,
            'admin'
        )
            ->get(
                route(
                    'admin.security.sessions'
                )
            )
            ->assertOk()
            ->assertSee(
                route(
                    'admin.users.show',
                    $user->getKey()
                ),
                false
            )
            ->assertSee(
                'User Security'
            );
    }

    public function test_content_protection_requires_security_view_permission(): void
    {
        $admin =
            $this->adminWithPermissions([
                'security.events',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.security.content-protection'
                )
            )
            ->assertForbidden();
    }

    public function test_content_protection_reports_effective_controls_without_secrets(): void
    {
        $admin =
            $this->adminWithPermissions([
                'security.view',
            ]);

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
            'session.secure',
            true
        );

        config()->set(
            'tradex_kyc.identity_hash_key',
            'DO-NOT-RENDER-THIS-SECRET'
        );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.security.content-protection'
                )
            )
            ->assertOk()
            ->assertSee(
                'Protected private filesystem'
            )
            ->assertSee(
                'KYC private storage'
            )
            ->assertSee(
                'Admin session HttpOnly cookie'
            )
            ->assertSee(
                'HTTPS-only session cookie'
            )
            ->assertDontSee(
                'DO-NOT-RENDER-THIS-SECRET'
            );
    }

    public function test_admin_11_introduces_no_security_mutation_routes(): void
    {
        $routes = collect(
            app('router')
                ->getRoutes()
                ->getRoutes()
        )
            ->filter(
                fn ($route): bool =>
                    str_starts_with(
                        (string) $route->uri(),
                        'admin/security'
                    )
            );

        $this->assertNotEmpty(
            $routes
        );

        foreach ($routes as $route) {
            $methods =
                $route->methods();

            $this->assertContains(
                'GET',
                $methods
            );

            $this->assertNotContains(
                'POST',
                $methods
            );

            $this->assertNotContains(
                'PUT',
                $methods
            );

            $this->assertNotContains(
                'PATCH',
                $methods
            );

            $this->assertNotContains(
                'DELETE',
                $methods
            );
        }
    }

    private function activeSession(
        ?string $email = null,
        $expiresAt = null,
        $revokedAt = null
    ): array {
        $user =
            $this->createApiUser([
                'email' =>
                    $email
                    ?: 'security-session-'
                        .Str::lower(
                            Str::random(8)
                        )
                        .'@example.test',
            ]);

        $device =
            UserDevice::query()
                ->create([
                    'uuid' =>
                        Str::uuid()
                            ->toString(),

                    'user_id' =>
                        $user->getKey(),

                    'device_slot' =>
                        UserDevice::SLOT_MOBILE,

                    'platform' =>
                        UserDevice::PLATFORM_ANDROID,

                    'device_name' =>
                        'Security Test Phone',

                    'device_model' =>
                        'Test Model',

                    'os_version' =>
                        '15',

                    'app_version' =>
                        '1.0.0',

                    'installation_id_hash' =>
                        hash(
                            'sha256',
                            Str::uuid()
                                ->toString()
                        ),

                    'device_public_key' =>
                        'TEST-DEVICE-PUBLIC-KEY',

                    'is_trusted' =>
                        true,

                    'is_active' =>
                        true,

                    'registered_at' =>
                        now(),

                    'trusted_at' =>
                        now(),

                    'last_seen_at' =>
                        now(),

                    'last_login_at' =>
                        now(),

                    'last_login_ip' =>
                        '198.51.100.20',
                ]);

        $token =
            UserRefreshToken::query()
                ->create([
                    'uuid' =>
                        Str::uuid()
                            ->toString(),

                    'user_id' =>
                        $user->getKey(),

                    'user_device_id' =>
                        $device->getKey(),

                    'token_hash' =>
                        hash(
                            'sha256',
                            'refresh-'
                            .Str::uuid()
                                ->toString()
                        ),

                    'family_uuid' =>
                        Str::uuid()
                            ->toString(),

                    'expires_at' =>
                        $expiresAt
                        ?: now()
                            ->addHour(),

                    'last_used_at' =>
                        now()
                            ->subMinute(),

                    'revoked_at' =>
                        $revokedAt,

                    'revoke_reason' =>
                        $revokedAt
                            ? 'SENSITIVE-REVOKE-REASON'
                            : null,
                ]);

        return [
            $user,
            $device,
            $token,
        ];
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
                        'ADMIN Security Test',

                    'email' =>
                        'admin-security-'
                        .Str::lower(
                            Str::random(12)
                        )
                        .'@tradex.test',

                    'email_verified_at' =>
                        now(),

                    'password' =>
                        'TestingPassword123!',

                    'is_active' =>
                        true,

                    'failed_login_attempts' =>
                        0,
                ]);

        $role =
            AdminRole::query()
                ->create([
                    'code' =>
                        'SECURITY_TEST_'
                        .Str::upper(
                            Str::random(8)
                        ),

                    'name' =>
                        'Security Test Role',

                    'description' =>
                        'ADMIN-11 test role.',

                    'is_system' =>
                        false,

                    'is_active' =>
                        true,
                ]);

        $permissionIds = [];

        foreach ($codes as $code) {
            $permission =
                AdminPermission::query()
                    ->updateOrCreate(
                        [
                            'code' =>
                                $code,
                        ],
                        [
                            'name' =>
                                $code,

                            'module' =>
                                'SECURITY',

                            'description' =>
                                null,
                        ]
                    );

            $permissionIds[] =
                $permission
                    ->getKey();
        }

        $role->permissions()
            ->sync(
                $permissionIds
            );

        $admin->roles()
            ->sync([
                $role->getKey(),
            ]);

        return $admin->fresh(
            'roles'
        );
    }
}
