<?php

namespace Tests\Feature\Admin;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\CustomerOnboardingInvitation;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class GracefulAdminErrorHandlingTest extends TestCase
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

        config()->set(
            'tradex_registration.allowed_languages',
            ['en']
        );
    }

    public function test_duplicate_customer_invitation_returns_to_form_with_warning_notice(): void
    {
        $admin = $this->adminWithPermissions([
            'users.invite',
        ]);

        $this->pendingInvitation(
            $admin,
            'duplicate@example.test',
            '+94771110001'
        );

        $response = $this
            ->actingAs($admin, 'admin')
            ->from(
                route('admin.customers.create')
            )
            ->post(
                route('admin.customers.store'),
                [
                    'name' => 'Duplicate Customer',
                    'email' => 'duplicate@example.test',
                    'whatsapp_number' => '+94771110002',
                    'country_code' => 'LK',
                    'language_code' => 'en',
                    'referral_code' => null,
                    'internal_note' => 'Safe old input canary',
                ]
            );

        $response
            ->assertRedirect(
                route('admin.customers.create')
            )
            ->assertSessionHas(
                'admin_notice',
                fn (array $notice): bool =>
                    ($notice['type'] ?? null) === 'warning'
                    && ($notice['status'] ?? null) === 409
                    && str_contains(
                        (string) ($notice['message'] ?? ''),
                        'pending onboarding invitation already exists'
                    )
            )
            ->assertSessionHas(
                '_old_input.email',
                'duplicate@example.test'
            );

        $this->assertDatabaseCount(
            'customer_onboarding_invitations',
            1
        );
    }

    public function test_duplicate_without_browser_referrer_keeps_http_status_but_uses_friendly_page(): void
    {
        $admin = $this->adminWithPermissions([
            'users.invite',
        ]);

        $this->pendingInvitation(
            $admin,
            'direct@example.test',
            '+94771110011'
        );

        $this
            ->actingAs($admin, 'admin')
            ->post(
                route('admin.customers.store'),
                [
                    'name' => 'Direct Duplicate',
                    'email' => 'direct@example.test',
                    'whatsapp_number' => '+94771110012',
                    'country_code' => 'LK',
                    'language_code' => 'en',
                    'referral_code' => null,
                    'internal_note' => null,
                ]
            )
            ->assertStatus(409)
            ->assertSee(
                'data-tradex-admin-friendly-error="v1"',
                false
            )
            ->assertSee('Conflict detected')
            ->assertDontSee('HttpException')
            ->assertDontSee('Exception trace');
    }

    public function test_existing_warning_session_is_rendered_as_toast(): void
    {
        $admin = $this->adminWithPermissions([
            'users.invite',
        ]);

        $this
            ->actingAs($admin, 'admin')
            ->withSession([
                'warning' =>
                    'ADMIN-20D warning toast canary',
            ])
            ->get(
                route('admin.customers.create')
            )
            ->assertOk()
            ->assertSee(
                'data-admin-toast',
                false
            )
            ->assertSee(
                'admin-toast-warning',
                false
            )
            ->assertSee(
                'ADMIN-20D warning toast canary'
            );
    }

    public function test_unexpected_admin_mutation_redirects_with_reference_and_never_flashes_secrets(): void
    {
        Route::middleware('web')
            ->post(
                '/admin/__test/error-boundary',
                static function (): void {
                    throw new RuntimeException(
                        'ADMIN-20D-UNEXPECTED-SECRET-CANARY'
                    );
                }
            );

        $admin = $this->adminWithPermissions([]);

        $response = $this
            ->actingAs($admin, 'admin')
            ->from('/admin/customers/create')
            ->post(
                '/admin/__test/error-boundary',
                [
                    'name' => 'Safe Input Canary',
                    'password' => 'DO-NOT-FLASH-PASSWORD',
                    'totp_code' => '123456',
                    'api_key' => 'DO-NOT-FLASH-API-KEY',
                    'nested' => [
                        'note' => 'Nested safe value',
                        'recovery_code' => 'DO-NOT-FLASH-RECOVERY',
                    ],
                ]
            );

        $response
            ->assertRedirect('/admin/customers/create')
            ->assertSessionHas(
                'admin_notice',
                function (array $notice): bool {
                    $reference =
                        (string) ($notice['reference'] ?? '');

                    return ($notice['type'] ?? null) === 'error'
                        && ($notice['status'] ?? null) === 500
                        && str_starts_with(
                            $reference,
                            'TX-ADM-'
                        )
                        && ! str_contains(
                            (string) ($notice['message'] ?? ''),
                            'ADMIN-20D-UNEXPECTED-SECRET-CANARY'
                        );
                }
            );

        $oldInput = Session::get(
            '_old_input',
            []
        );

        $this->assertSame(
            'Safe Input Canary',
            $oldInput['name'] ?? null
        );

        $this->assertSame(
            'Nested safe value',
            $oldInput['nested']['note'] ?? null
        );

        $this->assertArrayNotHasKey(
            'password',
            $oldInput
        );

        $this->assertArrayNotHasKey(
            'totp_code',
            $oldInput
        );

        $this->assertArrayNotHasKey(
            'api_key',
            $oldInput
        );

        $this->assertArrayNotHasKey(
            'recovery_code',
            $oldInput['nested'] ?? []
        );
    }

    public function test_unexpected_admin_get_returns_branded_error_without_leaking_exception(): void
    {
        Route::middleware('web')
            ->get(
                '/admin/__test/error-page',
                static function (): void {
                    throw new RuntimeException(
                        'ADMIN-20D-GET-LEAK-CANARY'
                    );
                }
            );

        $admin = $this->adminWithPermissions([]);

        $this
            ->actingAs($admin, 'admin')
            ->get('/admin/__test/error-page')
            ->assertStatus(500)
            ->assertSee(
                'data-tradex-admin-friendly-error="v1"',
                false
            )
            ->assertSee('Request could not be completed')
            ->assertSee('TX-ADM-')
            ->assertDontSee(
                'ADMIN-20D-GET-LEAK-CANARY'
            )
            ->assertDontSee('RuntimeException')
            ->assertDontSee('Exception trace');
    }

    private function pendingInvitation(
        AdminUser $admin,
        string $email,
        string $whatsapp
    ): CustomerOnboardingInvitation {
        return CustomerOnboardingInvitation::query()
            ->create([
                'uuid' => (string) Str::uuid(),
                'name' => 'ADMIN-20D Pending Customer',
                'email' => $email,
                'whatsapp_number' => $whatsapp,
                'country_code' => 'LK',
                'language_code' => 'en',
                'status' =>
                    CustomerOnboardingInvitation::STATUS_PENDING,
                'onboarding_stage' =>
                    CustomerOnboardingInvitation::STAGE_INVITE_CREATED,
                'active_email_hash' =>
                    hash('sha256', mb_strtolower($email)),
                'active_whatsapp_hash' =>
                    hash('sha256', $whatsapp),
                'created_by_admin_id' =>
                    $admin->getKey(),
                'expires_at' =>
                    now()->addDays(7),
            ]);
    }

    private function adminWithPermissions(
        array $codes
    ): AdminUser {
        $admin = AdminUser::query()
            ->create([
                'uuid' => (string) Str::uuid(),
                'name' => 'ADMIN-20D Error Boundary Actor',
                'email' =>
                    'admin20d-'
                    .Str::lower(Str::random(10))
                    .'@example.test',
                'email_verified_at' => now(),
                'password' => 'Testing-Admin-Pass-123!',
                'is_active' => true,
                'failed_login_attempts' => 0,
            ]);

        $role = AdminRole::query()
            ->create([
                'code' =>
                    'ADMIN20D_TEST_'
                    .Str::upper(Str::random(8)),
                'name' => 'ADMIN-20D Test Role',
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

        $role->permissions()->sync(
            $permissionIds
        );

        $admin->roles()->sync([
            $role->id,
        ]);

        return $admin->fresh('roles');
    }
}
