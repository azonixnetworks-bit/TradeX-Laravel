<?php

namespace Tests\Feature\Admin\Administration;

use App\Mail\AdminSecurityMail;
use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Services\Admin\Administration\AdminEmailDeliveryService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminEmailDeliveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminRoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);

        config([
            'tradex_admin_email.enabled' => true,
            'tradex_admin_email.mailer' => 'array',
            'mail.default' => 'array',
        ]);
    }

    public function test_creating_admin_sends_invitation_and_records_dispatch_status(): void
    {
        Mail::fake();

        $owner = $this->superAdmin();
        $role = AdminRole::query()
            ->where('code', 'SUPPORT_ADMIN')
            ->firstOrFail();

        $this->actingAs($owner, 'admin')
            ->post(
                route('admin.administration.admins.store'),
                [
                    'name' => 'Email Audit Admin',
                    'email' => 'email.audit@example.test',
                    'password' => 'Strong-Admin-Pass-123!',
                    'password_confirmation' => 'Strong-Admin-Pass-123!',
                    'role_ids' => [$role->id],
                ]
            )
            ->assertRedirect();

        $created = AdminUser::query()
            ->where('email', 'email.audit@example.test')
            ->firstOrFail();

        $this->assertDatabaseHas('admin_email_deliveries', [
            'admin_user_id' => $created->id,
            'event_type' => 'ADMIN_INVITATION',
            'recipient_email' => 'email.audit@example.test',
            'dispatch_status' => 'SENT',
            'delivery_status' => 'UNCONFIRMED',
            'mailer' => 'array',
        ]);

        Mail::assertSent(
            AdminSecurityMail::class,
            fn (AdminSecurityMail $mail): bool =>
                $mail->hasTo('email.audit@example.test')
                && $mail->eventType === 'ADMIN_INVITATION'
        );
    }

    public function test_disabling_admin_sends_security_email_and_records_status(): void
    {
        Mail::fake();

        $owner = $this->superAdmin();
        $target = $this->delegatedAdmin(true);

        $this->actingAs($owner, 'admin')
            ->patch(
                route('admin.administration.admins.status', $target),
                [
                    'status' => 'DISABLED',
                    'reason' => 'Access is no longer required',
                ]
            )
            ->assertRedirect();

        $this->assertFalse((bool) $target->fresh()->is_active);

        $this->assertDatabaseHas('admin_email_deliveries', [
            'admin_user_id' => $target->id,
            'event_type' => 'ACCOUNT_DISABLED',
            'dispatch_status' => 'SENT',
        ]);
    }

    public function test_owner_can_soft_delete_only_disabled_delegated_admin(): void
    {
        Mail::fake();

        $owner = $this->superAdmin();
        $target = $this->delegatedAdmin(false);

        $this->actingAs($owner, 'admin')
            ->patch(
                route('admin.administration.admins.status', $target),
                [
                    'status' => 'DELETE',
                    'reason' => 'Administrator left the company',
                ]
            )
            ->assertRedirect(
                route('admin.administration.admins.index')
            );

        $this->assertSoftDeleted('admin_users', [
            'id' => $target->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'ADMIN_ACCOUNT_DELETED',
            'target_id' => $target->id,
        ]);

        $this->assertDatabaseHas('admin_email_deliveries', [
            'admin_user_id' => $target->id,
            'event_type' => 'ACCOUNT_DELETED',
            'dispatch_status' => 'SENT',
        ]);
    }

    public function test_owner_cannot_delete_active_admin_before_disabling(): void
    {
        Mail::fake();

        $owner = $this->superAdmin();
        $target = $this->delegatedAdmin(true);

        $this->actingAs($owner, 'admin')
            ->patch(
                route('admin.administration.admins.status', $target),
                [
                    'status' => 'DELETE',
                    'reason' => 'Attempted active deletion',
                ]
            )
            ->assertStatus(422);

        $this->assertDatabaseHas('admin_users', [
            'id' => $target->id,
            'deleted_at' => null,
        ]);
    }

    public function test_delegated_admin_cannot_use_owner_delete_action(): void
    {
        Mail::fake();

        $actor = $this->adminWithPermissions([
            'admins.disable',
        ]);
        $target = $this->delegatedAdmin(false);

        $this->actingAs($actor, 'admin')
            ->patch(
                route('admin.administration.admins.status', $target),
                [
                    'status' => 'DELETE',
                    'reason' => 'Unauthorized delete attempt',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseHas('admin_users', [
            'id' => $target->id,
            'deleted_at' => null,
        ]);
    }

    public function test_admin_list_and_edit_show_email_delivery_audit(): void
    {
        $owner = $this->superAdmin();
        $target = $this->delegatedAdmin(true);

        app(AdminEmailDeliveryService::class)
            ->send($target, 'ADMIN_INVITATION', [
                'roles' => ['SUPPORT_ADMIN'],
            ]);

        $this->actingAs($owner, 'admin')
            ->get(route('admin.administration.admins.index'))
            ->assertOk()
            ->assertSee('Email status meaning')
            ->assertSee('ADMIN_INVITATION')
            ->assertSee('UNCONFIRMED');

        $this->actingAs($owner, 'admin')
            ->get(route('admin.administration.admins.edit', $target))
            ->assertOk()
            ->assertSee('EMAIL DELIVERY AUDIT')
            ->assertSee('ADMIN_INVITATION')
            ->assertSee('UNCONFIRMED');
    }

    private function superAdmin(): AdminUser
    {
        $admin = $this->newAdmin('owner');
        $role = AdminRole::query()
            ->where('code', 'SUPER_ADMIN')
            ->firstOrFail();

        $admin->roles()->sync([$role->id]);

        return $admin->fresh('roles');
    }

    private function delegatedAdmin(bool $active): AdminUser
    {
        $admin = $this->newAdmin('delegated', $active);
        $role = AdminRole::query()
            ->where('code', 'SUPPORT_ADMIN')
            ->firstOrFail();

        $admin->roles()->sync([$role->id]);

        return $admin->fresh('roles');
    }

    private function adminWithPermissions(array $codes): AdminUser
    {
        $admin = $this->newAdmin('actor');

        $role = AdminRole::query()->create([
            'code' => 'ADMIN_EMAIL_TEST_'.Str::upper(Str::random(8)),
            'name' => 'Admin Email Test Role',
            'description' => null,
            'is_system' => false,
            'is_active' => true,
        ]);

        $permissionIds = AdminPermission::query()
            ->whereIn('code', $codes)
            ->pluck('id')
            ->all();

        $role->permissions()->sync($permissionIds);
        $admin->roles()->sync([$role->id]);

        return $admin->fresh('roles');
    }

    private function newAdmin(
        string $prefix,
        bool $active = true
    ): AdminUser {
        return AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => ucfirst($prefix).' Admin',
            'email' => $prefix.'-'.Str::lower(Str::random(10)).'@example.test',
            'email_verified_at' => now(),
            'password' => 'Testing-Admin-Pass-123!',
            'is_active' => $active,
            'failed_login_attempts' => 0,
        ]);
    }
}
