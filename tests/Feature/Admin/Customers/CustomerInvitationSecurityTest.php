<?php

namespace Tests\Feature\Admin\Customers;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\CustomerOnboardingInvitation;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerInvitationSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminRoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);

        config()->set(
            'tradex_registration.allowed_languages',
            ['en', 'ta', 'si', 'hi', 'ml', 'te', 'ur']
        );

        Mail::fake();
    }

    public function test_admin_created_invitation_stores_only_a_hashed_activation_token(): void
    {
        $admin = $this->adminWithPermissions([
            'users.invite',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(
                route('admin.customers.store'),
                $this->invitationPayload()
            )
            ->assertRedirect(
                route('admin.customers.onboarding')
            );

        $this->assertDatabaseCount('users', 0);

        $invitation = CustomerOnboardingInvitation::query()
            ->firstOrFail();

        $this->assertSame(
            CustomerOnboardingInvitation::STATUS_PENDING,
            $invitation->status
        );

        $this->assertSame(
            CustomerOnboardingInvitation::STAGE_INVITE_SENT,
            $invitation->onboarding_stage
        );

        $this->assertIsString($invitation->activation_token_hash);
        $this->assertSame(64, strlen($invitation->activation_token_hash));
        $this->assertSame(1, $invitation->invite_send_count);
        $this->assertNotNull($invitation->invited_at);
        $this->assertNotNull($invitation->last_invited_at);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'CUSTOMER_ONBOARDING_INVITATION_SENT',
            'target_type' => 'CUSTOMER_ONBOARDING_INVITATION',
            'target_id' => $invitation->id,
        ]);
    }

    public function test_resend_rotates_activation_token_and_extends_expiry(): void
    {
        $admin = $this->adminWithPermissions([
            'users.invite',
        ]);

        $this->actingAs($admin, 'admin')
            ->post(
                route('admin.customers.store'),
                $this->invitationPayload()
            )
            ->assertRedirect();

        $invitation = CustomerOnboardingInvitation::query()
            ->firstOrFail();

        $oldHash = $invitation->activation_token_hash;

        $shortExpiry = now()->addHour();

        $invitation->forceFill([
            'expires_at' => $shortExpiry,
        ])->save();

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.customers.onboarding.resend',
                    $invitation->uuid
                )
            )
            ->assertRedirect(
                route('admin.customers.onboarding')
            );

        $invitation->refresh();

        $this->assertNotSame(
            $oldHash,
            $invitation->activation_token_hash
        );

        $this->assertSame(
            CustomerOnboardingInvitation::STAGE_INVITE_SENT,
            $invitation->onboarding_stage
        );

        $this->assertSame(2, $invitation->invite_send_count);
        $this->assertTrue(
            $invitation->expires_at->greaterThan($shortExpiry)
        );
    }

    public function test_revoke_invalidates_token_and_allows_a_fresh_invitation_for_identity(): void
    {
        $admin = $this->adminWithPermissions([
            'users.invite',
        ]);

        $payload = $this->invitationPayload();

        $this->actingAs($admin, 'admin')
            ->post(
                route('admin.customers.store'),
                $payload
            )
            ->assertRedirect();

        $invitation = CustomerOnboardingInvitation::query()
            ->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.customers.onboarding.revoke',
                    $invitation->uuid
                )
            )
            ->assertRedirect(
                route('admin.customers.onboarding')
            );

        $invitation->refresh();

        $this->assertSame(
            CustomerOnboardingInvitation::STATUS_REVOKED,
            $invitation->status
        );

        $this->assertNull($invitation->activation_token_hash);
        $this->assertNull($invitation->active_email_hash);
        $this->assertNull($invitation->active_whatsapp_hash);
        $this->assertNotNull($invitation->revoked_at);
        $this->assertSame(
            $admin->id,
            $invitation->revoked_by_admin_id
        );

        $this->actingAs($admin, 'admin')
            ->post(
                route('admin.customers.store'),
                $payload
            )
            ->assertRedirect(
                route('admin.customers.onboarding')
            );

        $this->assertDatabaseCount(
            'customer_onboarding_invitations',
            2
        );
    }

    private function invitationPayload(): array
    {
        return [
            'name' => 'ADMIN-20A Customer',
            'email' => 'customer.admin20a@example.test',
            'whatsapp_number' => '+94771112233',
            'country_code' => 'LK',
            'language_code' => 'en',
            'referral_code' => null,
            'internal_note' => null,
        ];
    }

    private function adminWithPermissions(array $codes): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'ADMIN-20A Test Actor',
            'email' => 'admin20a-'
                .Str::lower(Str::random(10))
                .'@example.test',
            'email_verified_at' => now(),
            'password' => 'Testing-Admin-Pass-123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'ADMIN20A_TEST_'
                .Str::upper(Str::random(8)),
            'name' => 'ADMIN-20A Test Role',
            'description' => null,
            'is_system' => false,
            'is_active' => true,
        ]);

        $permissionIds = [];

        foreach ($codes as $code) {
            $permissionIds[] = AdminPermission::query()
                ->where('code', $code)
                ->firstOrFail()
                ->id;
        }

        $role->permissions()->sync($permissionIds);
        $admin->roles()->sync([$role->id]);

        return $admin->fresh('roles');
    }
}
