<?php

namespace Tests\Feature\Admin\Audit;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditLogManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_audit_index(): void
    {
        $this->get(route('admin.audit.index'))->assertRedirect(route('admin.login'));
    }

    public function test_audit_view_permission_is_required(): void
    {
        $admin = $this->adminWithPermissions([]);
        $this->actingAs($admin, 'admin')->get(route('admin.audit.index'))->assertForbidden();
    }

    public function test_audit_index_shows_metadata_but_not_raw_payload_values(): void
    {
        $admin = $this->adminWithPermissions(['audit.view']);
        AuditLog::record(
            actorType: 'ADMIN', actorId: $admin->getKey(), action: 'PAYMENT_REVIEWED',
            module: 'PAYMENTS', targetType: 'PAYMENT', targetId: 88,
            oldValues: ['provider_reference' => 'RAW-OLD-SECRET'],
            newValues: ['status' => 'CLEARED', 'internal_note' => 'RAW-NEW-PAYLOAD'],
            ipAddress: '203.0.113.11', userAgent: 'TradeX Audit Test Agent'
        );

        $this->actingAs($admin, 'admin')->get(route('admin.audit.index'))
            ->assertOk()->assertSee('PAYMENT_REVIEWED')->assertSee('PAYMENTS')
            ->assertSee('203.0.113.11')->assertDontSee('RAW-OLD-SECRET')
            ->assertDontSee('RAW-NEW-PAYLOAD');
    }

    public function test_audit_filters_apply_to_metadata(): void
    {
        $admin = $this->adminWithPermissions(['audit.view']);
        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->getKey(),
            action: 'DEVICE_REVOKED',
            module: 'DEVICE_SECURITY',
            targetType: 'USER_DEVICE',
            targetId: 12,
            ipAddress: '203.0.113.21',
            userAgent: 'INCLUDED-FILTER-AGENT'
        );

        AuditLog::record(
            actorType: 'USER',
            actorId: 777,
            action: 'PROFILE_UPDATED',
            module: 'PROFILE',
            targetType: 'USER',
            targetId: 777,
            ipAddress: '203.0.113.22',
            userAgent: 'EXCLUDED-FILTER-AGENT'
        );

        $this->actingAs($admin, 'admin')
            ->get(route('admin.audit.index', [
                'actor_type' => 'ADMIN',
                'module' => 'DEVICE_SECURITY',
                'target_type' => 'USER_DEVICE',
            ]))
            ->assertOk()
            ->assertSee('DEVICE_REVOKED')
            ->assertSee('INCLUDED-FILTER-AGENT')
            ->assertDontSee('EXCLUDED-FILTER-AGENT');
    }

    public function test_audit_detail_requires_permission(): void
    {
        $admin = $this->adminWithPermissions([]);
        $log = AuditLog::record('SYSTEM', null, 'SECURITY_CHECK', 'SECURITY');
        $this->actingAs($admin, 'admin')->get(route('admin.audit.show', $log->getKey()))->assertForbidden();
    }

    public function test_audit_detail_redacts_sensitive_nested_payload_keys(): void
    {
        $admin = $this->adminWithPermissions(['audit.view']);
        $log = AuditLog::record(
            actorType: 'ADMIN', actorId: $admin->getKey(), action: 'SENSITIVE_TEST', module: 'SECURITY',
            targetType: 'USER', targetId: 5,
            oldValues: ['status' => 'ACTIVE', 'nested' => [
                'token_hash' => 'TOKEN-HASH-SHOULD-NOT-RENDER',
                'identity_number' => '199512345678', 'safe_counter' => 9,
            ]],
            newValues: ['status' => 'SUSPENDED', 'reason' => 'Security review completed.']
        );

        $this->actingAs($admin, 'admin')->get(route('admin.audit.show', $log->getKey()))
            ->assertOk()->assertSee('[REDACTED]')->assertSee('safe_counter')
            ->assertSee('Security review completed.')
            ->assertDontSee('TOKEN-HASH-SHOULD-NOT-RENDER')->assertDontSee('199512345678');
    }

    public function test_audit_detail_resolves_admin_actor(): void
    {
        $admin = $this->adminWithPermissions(['audit.view']);
        $log = AuditLog::record('ADMIN', $admin->getKey(), 'ADMIN_LOGIN', 'AUTH');
        $this->actingAs($admin, 'admin')->get(route('admin.audit.show', $log->getKey()))
            ->assertOk()->assertSee($admin->name)->assertSee($admin->email);
    }

    public function test_audit_detail_is_no_store_and_security_hardened(): void
    {
        $admin = $this->adminWithPermissions(['audit.view']);
        $log = AuditLog::record('SYSTEM', null, 'SECURITY_CHECK', 'SECURITY');
        $response = $this->actingAs($admin, 'admin')->get(route('admin.audit.show', $log->getKey()))
            ->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_audit_detail_access_is_itself_audited_without_copying_payload(): void
    {
        $admin = $this->adminWithPermissions(['audit.view']);
        $log = AuditLog::record(
            actorType: 'USER', actorId: 100, action: 'PROFILE_CONTACT_CHANGE_REQUESTED', module: 'PROFILE',
            targetType: 'USER_PROFILE_CHANGE_CHALLENGE', targetId: 200,
            newValues: ['challenge_token' => 'DO-NOT-COPY-THIS-TOKEN', 'purpose' => 'WHATSAPP']
        );

        $this->actingAs($admin, 'admin')->get(route('admin.audit.show', $log->getKey()))->assertOk();
        $access = AuditLog::query()->where('action', 'AUDIT_LOG_DETAIL_ACCESSED')
            ->where('target_type', 'AUDIT_LOG')->where('target_id', $log->getKey())->latest('id')->firstOrFail();
        $payload = json_encode($access->new_values, JSON_THROW_ON_ERROR);
        $this->assertStringContainsString('PROFILE_CONTACT_CHANGE_REQUESTED', $payload);
        $this->assertStringNotContainsString('DO-NOT-COPY-THIS-TOKEN', $payload);
    }

    public function test_audit_index_is_also_no_store(): void
    {
        $admin = $this->adminWithPermissions(['audit.view']);
        $response = $this->actingAs($admin, 'admin')->get(route('admin.audit.index'))->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_admin_12_introduces_get_routes_only(): void
    {
        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn ($route): bool => str_starts_with((string) $route->uri(), 'admin/audit'));
        $this->assertCount(2, $routes);
        foreach ($routes as $route) {
            $methods = $route->methods();
            $this->assertContains('GET', $methods);
            $this->assertNotContains('POST', $methods);
            $this->assertNotContains('PUT', $methods);
            $this->assertNotContains('PATCH', $methods);
            $this->assertNotContains('DELETE', $methods);
        }
    }

    private function adminWithPermissions(array $codes): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(), 'name' => 'ADMIN Audit Test',
            'email' => 'admin-audit-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(), 'password' => 'TestingPassword123!',
            'is_active' => true, 'failed_login_attempts' => 0,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'AUDIT_TEST_'.Str::upper(Str::random(8)), 'name' => 'Audit Test Role',
            'description' => 'ADMIN-12 test role.', 'is_system' => false, 'is_active' => true,
        ]);

        $permissionIds = [];
        foreach ($codes as $code) {
            $permission = AdminPermission::query()->updateOrCreate(
                ['code' => $code],
                ['name' => $code, 'module' => 'AUDIT', 'description' => null]
            );
            $permissionIds[] = $permission->getKey();
        }

        $role->permissions()->sync($permissionIds);
        $admin->roles()->sync([$role->getKey()]);
        return $admin->fresh('roles');
    }
}
