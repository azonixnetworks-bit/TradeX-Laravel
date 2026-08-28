<?php

namespace Tests\Feature\Admin\Customers;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\CustomerOnboardingInvitation;
use App\Models\User;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerManagementTest extends TestCase
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
            [
                'en',
                'ta',
                'si',
                'hi',
                'ml',
                'te',
                'ur',
            ]
        );
    }

    public function test_guest_is_redirected_from_customer_dashboard(): void
    {
        $this->get(
            route(
                'admin.customers.dashboard'
            )
        )->assertRedirect(
            route('admin.login')
        );
    }

    public function test_users_view_permission_is_required_for_customer_dashboard(): void
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
                    'admin.customers.dashboard'
                )
            )
            ->assertForbidden();
    }

    public function test_users_invite_permission_is_required_for_add_customer(): void
    {
        $admin =
            $this->adminWithPermissions([
                'users.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.customers.create'
                )
            )
            ->assertForbidden();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.customers.store'
                ),
                $this->invitationPayload()
            )
            ->assertForbidden();
    }

    public function test_authorized_admin_creates_pre_onboarding_invitation_not_user(): void
    {
        $admin =
            $this->adminWithPermissions([
                'users.invite',
            ]);

        $this->assertDatabaseCount(
            'users',
            0
        );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.customers.store'
                ),
                $this->invitationPayload([
                    'language_code' =>
                        'ta',
                ])
            )
            ->assertRedirect(
                route(
                    'admin.customers.onboarding'
                )
            );

        $this->assertDatabaseCount(
            'users',
            0
        );

        $invitation =
            CustomerOnboardingInvitation::query()
                ->firstOrFail();

        $this->assertSame(
            CustomerOnboardingInvitation::STATUS_PENDING,
            $invitation->status
        );

        $this->assertSame(
            'ta',
            $invitation->language_code
        );

        $this->assertNotNull(
            $invitation->active_email_hash
        );

        $this->assertNotNull(
            $invitation->active_whatsapp_hash
        );

        $this->assertNull(
            $invitation->matched_user_id
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'CUSTOMER_ONBOARDING_INVITATION_CREATED',
                'target_type' =>
                    'CUSTOMER_ONBOARDING_INVITATION',
                'target_id' =>
                    $invitation->id,
            ]
        );
    }

    public function test_internal_note_is_encrypted_and_never_copied_to_audit_payload(): void
    {
        $admin =
            $this->adminWithPermissions([
                'users.invite',
            ]);

        $canary =
            'ADMIN20-NOTE-CANARY-DO-NOT-RENDER-OR-AUDIT';

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.customers.store'
                ),
                $this->invitationPayload([
                    'internal_note' =>
                        $canary,
                ])
            )
            ->assertRedirect();

        $invitation =
            CustomerOnboardingInvitation::query()
                ->firstOrFail();

        $rawStoredNote =
            DB::table(
                'customer_onboarding_invitations'
            )
                ->where(
                    'id',
                    $invitation->id
                )
                ->value(
                    'internal_note'
                );

        $this->assertIsString(
            $rawStoredNote
        );

        $this->assertNotSame(
            $canary,
            $rawStoredNote
        );

        $this->assertSame(
            $canary,
            $invitation->internal_note
        );

        $audit =
            AuditLog::query()
                ->where(
                    'action',
                    'CUSTOMER_ONBOARDING_INVITATION_CREATED'
                )
                ->latest('id')
                ->firstOrFail();

        $auditJson = json_encode(
            $audit->new_values,
            JSON_THROW_ON_ERROR
        );

        $this->assertStringNotContainsString(
            $canary,
            $auditJson
        );

        $this->assertStringNotContainsString(
            'customer.admin20@example.test',
            $auditJson
        );

        $this->assertStringNotContainsString(
            '+94771112233',
            $auditJson
        );
    }

    public function test_registered_email_or_whatsapp_cannot_receive_new_invitation(): void
    {
        $admin =
            $this->adminWithPermissions([
                'users.invite',
            ]);

        $this->createCustomer([
            'email' =>
                'registered@example.test',
            'whatsapp_number' =>
                '+94770000011',
        ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.customers.store'
                ),
                $this->invitationPayload([
                    'email' =>
                        'registered@example.test',
                ])
            )
            ->assertStatus(409);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.customers.store'
                ),
                $this->invitationPayload([
                    'email' =>
                        'another@example.test',
                    'whatsapp_number' =>
                        '+94770000011',
                ])
            )
            ->assertStatus(409);

        $this->assertDatabaseCount(
            'customer_onboarding_invitations',
            0
        );
    }

    public function test_pending_invitation_blocks_duplicate_email_or_whatsapp(): void
    {
        $admin =
            $this->adminWithPermissions([
                'users.invite',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.customers.store'
                ),
                $this->invitationPayload()
            )
            ->assertRedirect();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.customers.store'
                ),
                $this->invitationPayload([
                    'whatsapp_number' =>
                        '+94771112244',
                ])
            )
            ->assertStatus(409);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.customers.store'
                ),
                $this->invitationPayload([
                    'email' =>
                        'different@example.test',
                ])
            )
            ->assertStatus(409);

        $this->assertDatabaseCount(
            'customer_onboarding_invitations',
            1
        );
    }

    public function test_invalid_referral_code_is_rejected(): void
    {
        $admin =
            $this->adminWithPermissions([
                'users.invite',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.customers.store'
                ),
                $this->invitationPayload([
                    'referral_code' =>
                        'NOT-A-VALID-REFERRAL',
                ])
            )
            ->assertStatus(422);

        $this->assertDatabaseCount(
            'customer_onboarding_invitations',
            0
        );
    }

    public function test_active_referral_code_is_snapshotted_by_relationship(): void
    {
        $admin =
            $this->adminWithPermissions([
                'users.invite',
            ]);

        $referrer =
            $this->createCustomer([
                'email' =>
                    'referrer@example.test',
                'whatsapp_number' =>
                    '+94770000021',
                'referral_code' =>
                    'TXADMIN20REF',
                'status' =>
                    'ACTIVE',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.customers.store'
                ),
                $this->invitationPayload([
                    'referral_code' =>
                        'txadmin20ref',
                ])
            )
            ->assertRedirect();

        $invitation =
            CustomerOnboardingInvitation::query()
                ->firstOrFail();

        $this->assertSame(
            $referrer->id,
            $invitation->referred_by_user_id
        );

        $this->assertSame(
            'TXADMIN20REF',
            $invitation->referral_code_input
        );
    }

    public function test_pending_invitation_reconciles_to_customer_after_normal_registration(): void
    {
        $admin =
            $this->adminWithPermissions([
                'users.view',
                'users.invite',
            ]);

        $payload =
            $this->invitationPayload();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.customers.store'
                ),
                $payload
            )
            ->assertRedirect();

        $invitation =
            CustomerOnboardingInvitation::query()
                ->firstOrFail();

        $customer =
            $this->createCustomer([
                'email' =>
                    $payload['email'],
                'whatsapp_number' =>
                    $payload['whatsapp_number'],
                'status' =>
                    'ACTIVE',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.customers.onboarding'
                )
            )
            ->assertOk()
            ->assertSee(
                'MATCHED'
            );

        $invitation->refresh();

        $this->assertSame(
            CustomerOnboardingInvitation::STATUS_MATCHED,
            $invitation->status
        );

        $this->assertSame(
            $customer->id,
            $invitation->matched_user_id
        );

        $this->assertNull(
            $invitation->active_email_hash
        );

        $this->assertNull(
            $invitation->active_whatsapp_hash
        );

        $this->assertNotNull(
            $invitation->matched_at
        );
    }

    public function test_customer_segments_only_show_matching_accounts(): void
    {
        $admin =
            $this->adminWithPermissions([
                'users.view',
            ]);

        $free =
            $this->createCustomer([
                'name' =>
                    'ADMIN20 Free Canary',
                'email' =>
                    'free@example.test',
                'whatsapp_number' =>
                    '+94770000101',
                'account_type' =>
                    'FREE',
                'status' =>
                    'ACTIVE',
            ]);

        $premium =
            $this->createCustomer([
                'name' =>
                    'ADMIN20 Premium Canary',
                'email' =>
                    'premium@example.test',
                'whatsapp_number' =>
                    '+94770000102',
                'account_type' =>
                    'PREMIUM',
                'status' =>
                    'ACTIVE',
            ]);

        $restricted =
            $this->createCustomer([
                'name' =>
                    'ADMIN20 Restricted Canary',
                'email' =>
                    'restricted@example.test',
                'whatsapp_number' =>
                    '+94770000103',
                'account_type' =>
                    'FREE',
                'status' =>
                    'BLOCKED',
            ]);

        $freeResponse =
            $this->actingAs(
                $admin,
                'admin'
            )
                ->get(
                    route(
                        'admin.users.index',
                        ['segment' => 'FREE']
                    )
                )
                ->assertOk();

        $freeResponse
            ->assertSee($free->name)
            ->assertSee($restricted->name)
            ->assertDontSee($premium->name);

        $premiumResponse =
            $this->actingAs(
                $admin,
                'admin'
            )
                ->get(
                    route(
                        'admin.users.index',
                        ['segment' => 'PREMIUM']
                    )
                )
                ->assertOk();

        $premiumResponse
            ->assertSee($premium->name)
            ->assertDontSee($free->name)
            ->assertDontSee($restricted->name);

        $restrictedResponse =
            $this->actingAs(
                $admin,
                'admin'
            )
                ->get(
                    route(
                        'admin.users.index',
                        ['segment' => 'RESTRICTED']
                    )
                )
                ->assertOk();

        $restrictedResponse
            ->assertSee($restricted->name)
            ->assertDontSee($free->name)
            ->assertDontSee($premium->name);
    }

    public function test_recent_segment_excludes_old_customer(): void
    {
        config()->set(
            'tradex_customers.recent_days',
            30
        );

        $admin =
            $this->adminWithPermissions([
                'users.view',
            ]);

        $recent =
            $this->createCustomer([
                'name' =>
                    'ADMIN20 Recent Canary',
                'email' =>
                    'recent@example.test',
                'whatsapp_number' =>
                    '+94770000111',
                'created_at' =>
                    now()->subDays(3),
                'updated_at' =>
                    now()->subDays(3),
            ]);

        $old =
            $this->createCustomer([
                'name' =>
                    'ADMIN20 Old Canary',
                'email' =>
                    'old@example.test',
                'whatsapp_number' =>
                    '+94770000112',
                'created_at' =>
                    now()->subDays(90),
                'updated_at' =>
                    now()->subDays(90),
            ]);

        $response =
            $this->actingAs(
                $admin,
                'admin'
            )
                ->get(
                    route(
                        'admin.users.index',
                        ['segment' => 'RECENT']
                    )
                )
                ->assertOk();

        $response
            ->assertSee($recent->name)
            ->assertDontSee($old->name);
    }

    private function invitationPayload(
        array $overrides = []
    ): array {
        return array_merge(
            [
                'name' =>
                    'ADMIN-20 Customer',
                'email' =>
                    'customer.admin20@example.test',
                'whatsapp_number' =>
                    '+94771112233',
                'country_code' =>
                    'LK',
                'language_code' =>
                    'en',
                'referral_code' =>
                    null,
                'internal_note' =>
                    null,
            ],
            $overrides
        );
    }

    private function createCustomer(
        array $overrides = []
    ): User {
        return User::factory()
            ->create(
                array_merge(
                    [
                        'uuid' =>
                            (string) Str::uuid(),
                        'whatsapp_number' =>
                            '+9477'
                            .random_int(
                                1000000,
                                9999999
                            ),
                        'whatsapp_verified_at' =>
                            now(),
                        'referral_code' =>
                            'TX'
                            .Str::upper(
                                Str::random(10)
                            ),
                        'country_code' =>
                            'LK',
                        'language_code' =>
                            'en',
                        'account_type' =>
                            'FREE',
                        'status' =>
                            'ACTIVE',
                    ],
                    $overrides
                )
            );
    }

    private function adminWithPermissions(
        array $codes
    ): AdminUser {
        $admin =
            AdminUser::query()
                ->create([
                    'uuid' =>
                        (string) Str::uuid(),
                    'name' =>
                        'ADMIN-20 Test Actor',
                    'email' =>
                        'admin20-'
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
                        'ADMIN20_TEST_'
                        .Str::upper(
                            Str::random(8)
                        ),
                    'name' =>
                        'ADMIN-20 Test Role',
                    'description' =>
                        null,
                    'is_system' =>
                        false,
                    'is_active' =>
                        true,
                ]);

        $permissionIds = [];

        foreach ($codes as $code) {
            $permissionIds[] =
                AdminPermission::query()
                    ->where(
                        'code',
                        $code
                    )
                    ->firstOrFail()
                    ->id;
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
