<?php

namespace Tests\Feature\Admin\Security;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\UserDevice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class CustomerDeviceAdminUiTest extends TestCase
{
    use CreatesTradeXMobileSession;
    use RefreshDatabase;

    public function test_security_admin_can_review_customer_device_risk_without_secrets(): void
    {
        $admin = $this->adminWithPermissions(['security.view']);
        $user = $this->createApiUser();
        $device = $this->device($user->getKey());

        AuditLog::record(
            actorType: 'USER',
            actorId: $user->getKey(),
            action: 'USER_SUSPICIOUS_LOGIN_DETECTED',
            module: 'SECURITY',
            targetType: 'USER_DEVICE',
            targetId: $device->getKey(),
            newValues: [
                'risk_level' => 'SUSPICIOUS',
                'risk_score' => 75,
                'signals' => ['NEW_DEVICE', 'NEW_IP'],
            ],
            ipAddress: '203.0.113.25'
        );

        $this->actingAs($admin, 'admin')
            ->get(route('admin.security.customer-devices.show', $device))
            ->assertOk()
            ->assertSee('SUSPICIOUS')
            ->assertSee('NEW DEVICE')
            ->assertSee('203.0.113.25')
            ->assertDontSee($device->installation_id_hash)
            ->assertDontSee('TEST-DEVICE-PUBLIC-KEY');
    }

    public function test_security_manager_can_force_revoke_customer_device(): void
    {
        $admin = $this->adminWithPermissions([
            'security.view',
            'security.manage',
        ]);
        $user = $this->createApiUser();
        $device = $this->device($user->getKey());

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.security.customer-devices.revoke', $device))
            ->assertRedirect(route('admin.security.customer-devices.show', $device));

        $device->refresh();

        $this->assertFalse((bool) $device->is_active);
        $this->assertFalse((bool) $device->is_trusted);
        $this->assertNotNull($device->revoked_at);
        $this->assertSame('ADMIN_FORCE_REVOKE', $device->revoke_reason);

        $this->assertDatabaseHas('audit_logs', [
            'actor_type' => 'ADMIN',
            'actor_id' => $admin->getKey(),
            'action' => 'CUSTOMER_DEVICE_FORCE_REVOKED',
            'module' => 'SECURITY',
            'target_type' => 'USER_DEVICE',
            'target_id' => $device->getKey(),
        ]);
    }

    private function device(int $userId): UserDevice
    {
        return UserDevice::query()->create([
            'uuid' => (string) Str::uuid(),
            'user_id' => $userId,
            'device_slot' => UserDevice::SLOT_MOBILE,
            'platform' => UserDevice::PLATFORM_ANDROID,
            'device_name' => 'Customer Security Phone',
            'device_model' => 'Test Model',
            'os_version' => '15',
            'app_version' => '2.0.0',
            'installation_id_hash' => hash('sha256', (string) Str::uuid()),
            'device_public_key' => 'TEST-DEVICE-PUBLIC-KEY',
            'is_trusted' => true,
            'is_active' => true,
            'registered_at' => now(),
            'trusted_at' => now(),
            'last_seen_at' => now(),
            'last_login_at' => now(),
            'last_login_ip' => '198.51.100.50',
        ]);
    }

    private function adminWithPermissions(array $codes): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Customer Security Admin',
            'email' => 'customer-security-'.Str::lower(Str::random(10)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'CUSTOMER_SECURITY_'.Str::upper(Str::random(8)),
            'name' => 'Customer Security Role',
            'description' => 'Customer security test role.',
            'is_system' => false,
            'is_active' => true,
        ]);

        $permissionIds = [];

        foreach ($codes as $code) {
            $permission = AdminPermission::query()->updateOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'module' => 'SECURITY',
                    'description' => null,
                ]
            );

            $permissionIds[] = $permission->getKey();
        }

        $role->permissions()->sync($permissionIds);
        $admin->roles()->sync([$role->getKey()]);

        return $admin->fresh('roles');
    }
}
