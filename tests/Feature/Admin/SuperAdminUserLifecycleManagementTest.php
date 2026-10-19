<?php

namespace Tests\Feature\Admin;

use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\User;
use App\Models\UserEntitlement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SuperAdminUserLifecycleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_grant_and_revoke_real_premium_access(): void
    {
        $admin = $this->createSuperAdmin();
        $user = $this->createActiveUser();

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.users.account-type.update', $user), [
                'account_type' => 'PREMIUM',
                'reason' => 'Approved complimentary premium access.',
            ])
            ->assertRedirect(route('admin.users.show', $user));

        $this->assertSame('PREMIUM', $user->fresh()->account_type);
        $this->assertTrue($user->fresh()->canAccessPremium());
        $this->assertDatabaseHas('user_entitlements', [
            'user_id' => $user->id,
            'product_code' => 'EDUCATION_PREMIUM',
            'status' => UserEntitlement::STATUS_ACTIVE,
            'payment_id' => null,
        ]);

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.users.account-type.update', $user), [
                'account_type' => 'FREE',
                'reason' => 'Complimentary premium access has ended.',
            ])
            ->assertRedirect(route('admin.users.show', $user));

        $this->assertSame('FREE', $user->fresh()->account_type);
        $this->assertFalse($user->fresh()->canAccessPremium());
        $this->assertDatabaseHas('user_entitlements', [
            'user_id' => $user->id,
            'product_code' => 'EDUCATION_PREMIUM',
            'status' => UserEntitlement::STATUS_REVOKED,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'USER_ACCOUNT_TYPE_CHANGED',
            'module' => 'USERS',
            'target_id' => $user->id,
        ]);
    }

    public function test_only_super_admin_can_change_account_type_or_delete_a_user(): void
    {
        $admin = $this->createRegularAdmin();
        $user = $this->createActiveUser();

        $this->actingAs($admin, 'admin')
            ->patch(route('admin.users.account-type.update', $user), [
                'account_type' => 'PREMIUM',
                'reason' => 'This attempt must be denied securely.',
            ])
            ->assertForbidden();

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.users.destroy', $user), [
                'reason' => 'This attempt must be denied securely.',
                'confirmation' => 'DELETE USER',
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
            'account_type' => 'FREE',
        ]);
    }

    public function test_super_admin_can_soft_delete_user_with_explicit_confirmation(): void
    {
        $admin = $this->createSuperAdmin();
        $user = $this->createActiveUser();

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.users.destroy', $user), [
                'reason' => 'Customer requested permanent account closure.',
                'confirmation' => 'DELETE USER',
            ])
            ->assertRedirect(route('admin.users.index'));

        $this->assertSoftDeleted('users', [
            'id' => $user->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'USER_ACCOUNT_DELETED',
            'module' => 'USERS',
            'target_id' => $user->id,
        ]);
    }

    public function test_deletion_requires_exact_confirmation_phrase(): void
    {
        $admin = $this->createSuperAdmin();
        $user = $this->createActiveUser();

        $this->actingAs($admin, 'admin')
            ->delete(route('admin.users.destroy', $user), [
                'reason' => 'Customer requested permanent account closure.',
                'confirmation' => 'delete user',
            ])
            ->assertSessionHasErrors('confirmation');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
    }

    private function createSuperAdmin(): AdminUser
    {
        $admin = $this->createRegularAdmin();

        $role = AdminRole::query()->create([
            'code' => 'SUPER_ADMIN',
            'name' => 'Super Admin',
            'description' => 'Test super administrator.',
            'is_system' => true,
            'is_active' => true,
        ]);

        $admin->roles()->attach($role->id);

        return $admin->fresh();
    }

    private function createRegularAdmin(): AdminUser
    {
        return AdminUser::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Lifecycle Test Admin',
            'email' => Str::lower(Str::random(12)).'@example.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);
    }

    private function createActiveUser(): User
    {
        return User::factory()->create([
            'uuid' => (string) Str::uuid(),
            'whatsapp_number' => '+9477'.random_int(1000000, 9999999),
            'whatsapp_verified_at' => now(),
            'referral_code' => 'LIFE'.strtoupper(Str::random(8)),
            'country_code' => 'LK',
            'language_code' => 'EN',
            'account_type' => 'FREE',
            'status' => 'ACTIVE',
        ]);
    }
}
