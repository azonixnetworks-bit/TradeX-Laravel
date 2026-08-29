<?php

namespace Tests\Feature\Api\Auth;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\CustomerOnboardingInvitation;
use App\Models\UserRegistrationSession;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerInvitationResendInvalidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AdminRoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
        Mail::fake();
    }

    public function test_admin_resend_supersedes_an_older_pending_invite_registration_session(): void
    {
        $admin = $this->adminWithInvitePermission();

        $invitation = CustomerOnboardingInvitation::query()
            ->create([
                'uuid' => (string) Str::uuid(),
                'name' => 'ADMIN-20B Resend Customer',
                'email' => 'resend.admin20b@example.test',
                'whatsapp_number' => '+94771112255',
                'country_code' => 'LK',
                'language_code' => 'en',
                'status' =>
                    CustomerOnboardingInvitation::STATUS_PENDING,
                'onboarding_stage' =>
                    CustomerOnboardingInvitation::STAGE_EMAIL_OTP_SENT,
                'activation_token_hash' => null,
                'activation_consumed_at' => now(),
                'invite_send_count' => 1,
                'active_email_hash' => hash(
                    'sha256',
                    'resend.admin20b@example.test'
                ),
                'active_whatsapp_hash' => hash(
                    'sha256',
                    '+94771112255'
                ),
                'created_by_admin_id' => $admin->getKey(),
                'expires_at' => now()->addDays(7),
            ]);

        $session = UserRegistrationSession::query()
            ->create([
                'uuid' => (string) Str::uuid(),
                'registration_token_hash' => hash(
                    'sha256',
                    str_repeat('d', 64)
                ),
                'name' => $invitation->name,
                'email' => $invitation->email,
                'whatsapp_number' =>
                    $invitation->whatsapp_number,
                'password_hash' => bcrypt(
                    'temporary-secret'
                ),
                'country_code' => 'LK',
                'language_code' => 'en',
                'source' =>
                    UserRegistrationSession::SOURCE_ADMIN_INVITE,
                'customer_onboarding_invitation_id' =>
                    $invitation->getKey(),
                'status' =>
                    UserRegistrationSession::STATUS_PENDING,
                'expires_at' => now()->addHour(),
            ]);

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

        $session->refresh();
        $invitation->refresh();

        $this->assertSame(
            UserRegistrationSession::STATUS_SUPERSEDED,
            $session->status
        );
        $this->assertSame(
            CustomerOnboardingInvitation::STAGE_INVITE_SENT,
            $invitation->onboarding_stage
        );
        $this->assertNull(
            $invitation->activation_consumed_at
        );
        $this->assertNotNull(
            $invitation->activation_token_hash
        );
        $this->assertSame(2, $invitation->invite_send_count);
    }

    private function adminWithInvitePermission(): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => (string) Str::uuid(),
            'name' => 'ADMIN-20B Resend Admin',
            'email' => 'admin20b-resend-'
                .Str::lower(Str::random(8))
                .'@example.test',
            'email_verified_at' => now(),
            'password' => 'Testing-Admin-Pass-123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'ADMIN20B_RESEND_'
                .Str::upper(Str::random(8)),
            'name' => 'ADMIN-20B Resend Role',
            'description' => null,
            'is_system' => false,
            'is_active' => true,
        ]);

        $permission = AdminPermission::query()
            ->where('code', 'users.invite')
            ->firstOrFail();

        $role->permissions()->sync([
            $permission->id,
        ]);
        $admin->roles()->sync([
            $role->id,
        ]);

        return $admin->fresh('roles');
    }
}
