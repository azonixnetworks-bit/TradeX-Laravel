<?php

namespace Tests\Feature\Admin\Legal;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\LegalPolicyVersion;
use App\Services\Admin\Legal\AdminLegalPolicyService;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class LegalPolicyManagementTest extends TestCase
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
    }

    public function test_guest_is_redirected_from_legal_management(): void
    {
        $this->get(
            route(
                'admin.legal.index'
            )
        )->assertRedirect(
            route('admin.login')
        );
    }

    public function test_legal_view_permission_is_required(): void
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
                    'admin.legal.index'
                )
            )
            ->assertForbidden();
    }

    public function test_index_lists_all_locked_policy_categories(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.legal.index'
                )
            )
            ->assertOk()
            ->assertSee('Terms of Service')
            ->assertSee('Privacy Policy')
            ->assertSee('No-Refund Policy')
            ->assertSee('Trading Risk Disclosure')
            ->assertSee('Affiliate Program Terms')
            ->assertSee('Marketing Consent Notice')
            ->assertSee('Complaints Policy');
    }

    public function test_index_explicitly_does_not_claim_acceptance_runtime_is_wired(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.legal.index'
                )
            )
            ->assertOk()
            ->assertSee(
                'NOT_WIRED'
            );
    }

    public function test_create_requires_manage_permission(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.legal.create',
                    'TERMS_OF_SERVICE'
                )
            )
            ->assertForbidden();
    }

    public function test_authorized_admin_can_create_version_one_draft(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
                'legal.manage',
            ]);

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.legal.store'
                ),
                $this->draftPayload()
            )
            ->assertRedirect();

        $policy =
            LegalPolicyVersion::query()
                ->where(
                    'policy_type',
                    'TERMS_OF_SERVICE'
                )
                ->firstOrFail();

        $this->assertSame(
            1,
            $policy->version_number
        );

        $this->assertSame(
            LegalPolicyVersion::STATUS_DRAFT,
            $policy->status
        );

        $this->assertNull(
            $policy->published_at
        );

        $this->assertSame(
            hash(
                'sha256',
                $policy->content
            ),
            $policy->content_sha256
        );
    }

    public function test_second_draft_for_same_policy_type_is_rejected(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
                'legal.manage',
            ]);

        $this->draftPolicy(
            $admin
        );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.legal.store'
                ),
                $this->draftPayload()
            )
            ->assertStatus(409);

        $this->assertSame(
            1,
            LegalPolicyVersion::query()
                ->where(
                    'policy_type',
                    'TERMS_OF_SERVICE'
                )
                ->count()
        );
    }

    public function test_draft_update_increments_revision_and_rehashes_content(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
                'legal.manage',
            ]);

        $policy =
            $this->draftPolicy(
                $admin
            );

        $newContent =
            'Updated legal policy text for ADMIN-17 testing with sufficient length.';

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.legal.update',
                    $policy
                ),
                $this->updatePayload([
                    'content' =>
                        $newContent,
                ])
            )
            ->assertRedirect();

        $policy->refresh();

        $this->assertSame(
            1,
            $policy->revision_version
        );

        $this->assertSame(
            hash(
                'sha256',
                $newContent
            ),
            $policy->content_sha256
        );
    }

    public function test_stale_draft_revision_is_rejected(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
                'legal.manage',
            ]);

        $policy =
            $this->draftPolicy(
                $admin,
                [
                    'revision_version' =>
                        2,
                ]
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.legal.update',
                    $policy
                ),
                $this->updatePayload([
                    'revision_version' =>
                        1,
                ])
            )
            ->assertStatus(409);

        $this->assertSame(
            2,
            $policy->fresh()
                ->revision_version
        );
    }

    public function test_publish_requires_distinct_publish_permission(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
                'legal.manage',
            ]);

        $policy =
            $this->draftPolicy(
                $admin
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.legal.publish',
                    $policy
                ),
                $this->publishPayload()
            )
            ->assertForbidden();
    }

    public function test_publisher_does_not_need_manage_permission(): void
    {
        $author =
            $this->adminWithPermissions([
                'legal.view',
                'legal.manage',
            ]);

        $publisher =
            $this->adminWithPermissions([
                'legal.view',
                'legal.publish',
            ]);

        $policy =
            $this->draftPolicy(
                $author
            );

        $this->actingAs(
            $publisher,
            'admin'
        )
            ->post(
                route(
                    'admin.legal.publish',
                    $policy
                ),
                $this->publishPayload()
            )
            ->assertRedirect();

        $this->assertSame(
            LegalPolicyVersion::STATUS_PUBLISHED,
            $policy->fresh()->status
        );
    }

    public function test_publish_requires_explicit_irreversible_confirmation(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
                'legal.publish',
            ]);

        $policy =
            $this->draftPolicy(
                $admin
            );

        $payload =
            $this->publishPayload();

        $payload['publish_confirmation'] =
            false;

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.legal.publish',
                    $policy
                ),
                $payload
            )
            ->assertSessionHasErrors(
                'publish_confirmation'
            );

        $this->assertTrue(
            $policy->fresh()->isDraft()
        );
    }

    public function test_immediate_publication_sets_effective_and_published_timestamps(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
                'legal.publish',
            ]);

        $policy =
            $this->draftPolicy(
                $admin
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.legal.publish',
                    $policy
                ),
                $this->publishPayload()
            )
            ->assertRedirect();

        $policy->refresh();

        $this->assertTrue(
            $policy->isPublished()
        );

        $this->assertNotNull(
            $policy->published_at
        );

        $this->assertNotNull(
            $policy->effective_at
        );

        $this->assertSame(
            $admin->id,
            $policy->published_by_admin_id
        );
    }

    public function test_backdated_effective_time_is_rejected(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
                'legal.publish',
            ]);

        $policy =
            $this->draftPolicy(
                $admin
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.legal.publish',
                    $policy
                ),
                $this->publishPayload([
                    'effective_at' =>
                        now()
                            ->subDay()
                            ->format(
                                'Y-m-d H:i:s'
                            ),
                ])
            )
            ->assertStatus(422);

        $this->assertTrue(
            $policy->fresh()->isDraft()
        );
    }

    public function test_future_effective_publication_is_reported_as_scheduled(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
                'legal.publish',
            ]);

        $policy =
            $this->draftPolicy(
                $admin
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.legal.publish',
                    $policy
                ),
                $this->publishPayload([
                    'effective_at' =>
                        now()
                            ->addDay()
                            ->format(
                                'Y-m-d H:i:s'
                            ),
                ])
            )
            ->assertRedirect();

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.legal.show',
                    $policy
                )
            )
            ->assertOk()
            ->assertSee(
                'SCHEDULED'
            );
    }

    public function test_published_policy_cannot_be_updated_through_admin_service(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
                'legal.manage',
            ]);

        $policy =
            $this->publishedPolicy(
                $admin
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->patch(
                route(
                    'admin.legal.update',
                    $policy
                ),
                $this->updatePayload([
                    'title' =>
                        'Must Never Replace Published Text',
                ])
            )
            ->assertStatus(409);

        $this->assertNotSame(
            'Must Never Replace Published Text',
            $policy->fresh()->title
        );
    }

    public function test_model_guard_rejects_direct_update_of_published_policy(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
            ]);

        $policy =
            $this->publishedPolicy(
                $admin
            );

        $this->expectException(
            LogicException::class
        );

        $policy->forceFill([
            'title' =>
                'Direct Mutation Must Fail',
        ])->save();
    }

    public function test_model_guard_rejects_deletion_of_any_legal_version(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
            ]);

        $policy =
            $this->draftPolicy(
                $admin
            );

        $this->expectException(
            LogicException::class
        );

        $policy->delete();
    }

    public function test_new_draft_after_publication_receives_next_version_number(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
                'legal.manage',
            ]);

        $this->publishedPolicy(
            $admin,
            'TERMS_OF_SERVICE',
            1
        );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.legal.store'
                ),
                $this->draftPayload()
            )
            ->assertRedirect();

        $this->assertSame(
            2,
            LegalPolicyVersion::query()
                ->where(
                    'policy_type',
                    'TERMS_OF_SERVICE'
                )
                ->where(
                    'status',
                    LegalPolicyVersion::STATUS_DRAFT
                )
                ->value(
                    'version_number'
                )
        );
    }

    public function test_latest_effective_version_is_current_without_mutating_history(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
            ]);

        $v1 =
            $this->publishedPolicy(
                $admin,
                'PRIVACY_POLICY',
                1,
                [
                    'effective_at' =>
                        now()->subDays(2),

                    'published_at' =>
                        now()->subDays(2),
                ]
            );

        $v2 =
            $this->publishedPolicy(
                $admin,
                'PRIVACY_POLICY',
                2,
                [
                    'effective_at' =>
                        now()->subDay(),

                    'published_at' =>
                        now()->subDay(),
                ]
            );

        $current =
            app(
                AdminLegalPolicyService::class
            )->currentPublished(
                'PRIVACY_POLICY'
            );

        $this->assertTrue(
            $current->is(
                $v2
            )
        );

        $this->assertTrue(
            $v1->fresh()->isPublished()
        );

        $this->assertTrue(
            $v2->fresh()->isPublished()
        );
    }

    public function test_policy_content_is_rendered_as_escaped_plain_text(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
            ]);

        $content =
            '<script>alert("ADMIN17")</script> This legal text remains plain text.';

        $policy =
            $this->draftPolicy(
                $admin,
                [
                    'content' =>
                        $content,

                    'content_sha256' =>
                        hash(
                            'sha256',
                            $content
                        ),
                ]
            );

        $this->actingAs(
            $admin,
            'admin'
        )
            ->get(
                route(
                    'admin.legal.show',
                    $policy
                )
            )
            ->assertOk()
            ->assertSee(
                '<script>alert("ADMIN17")</script>',
                true
            )
            ->assertDontSee(
                '<script>alert("ADMIN17")</script>',
                false
            );
    }

    public function test_audit_never_copies_full_legal_content(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
                'legal.manage',
            ]);

        $contentCanary =
            'LEGAL-CONTENT-CANARY-ADMIN17 '
            .'This sentence is deliberately long enough for validation.';

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.legal.store'
                ),
                $this->draftPayload([
                    'content' =>
                        $contentCanary,
                ])
            )
            ->assertRedirect();

        $audit =
            AuditLog::query()
                ->where(
                    'action',
                    'LEGAL_POLICY_DRAFT_CREATED'
                )
                ->latest('id')
                ->firstOrFail();

        $json =
            json_encode(
                $audit->new_values,
                JSON_THROW_ON_ERROR
            );

        $this->assertStringNotContainsString(
            $contentCanary,
            $json
        );

        $this->assertStringContainsString(
            'TERMS_OF_SERVICE',
            $json
        );
    }

    public function test_crlf_content_is_canonicalized_before_hashing(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
                'legal.manage',
            ]);

        $submitted =
            "Line one legal text.\r\n"
            ."Line two legal text with enough content.";

        $expected =
            "Line one legal text.\n"
            ."Line two legal text with enough content.";

        $this->actingAs(
            $admin,
            'admin'
        )
            ->post(
                route(
                    'admin.legal.store'
                ),
                $this->draftPayload([
                    'content' =>
                        $submitted,
                ])
            )
            ->assertRedirect();

        $policy =
            LegalPolicyVersion::query()
                ->firstOrFail();

        $this->assertSame(
            $expected,
            $policy->content
        );

        $this->assertSame(
            hash(
                'sha256',
                $expected
            ),
            $policy->content_sha256
        );
    }

    public function test_legal_pages_send_no_store_headers(): void
    {
        $admin =
            $this->adminWithPermissions([
                'legal.view',
            ]);

        $response =
            $this->actingAs(
                $admin,
                'admin'
            )
                ->get(
                    route(
                        'admin.legal.index'
                    )
                )
                ->assertOk()
                ->assertHeader(
                    'Referrer-Policy',
                    'no-referrer'
                )
                ->assertHeader(
                    'X-Content-Type-Options',
                    'nosniff'
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

    public function test_admin_17_has_exactly_seven_routes_and_no_delete_route(): void
    {
        $routes =
            collect(
                app('router')
                    ->getRoutes()
                    ->getRoutes()
            )
                ->filter(
                    fn ($route): bool =>
                        str_starts_with(
                            (string) $route->uri(),
                            'admin/legal'
                        )
                );

        $this->assertCount(
            7,
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

    private function draftPayload(
        array $overrides = []
    ): array {
        return array_merge(
            [
                'policy_type' =>
                    'TERMS_OF_SERVICE',

                'title' =>
                    'TradeX Terms of Service',

                'summary' =>
                    'ADMIN-17 test legal summary.',

                'content' =>
                    'This is ADMIN-17 test legal policy text with enough characters for validation.',
            ],
            $overrides
        );
    }

    private function updatePayload(
        array $overrides = []
    ): array {
        return array_merge(
            [
                'title' =>
                    'TradeX Terms of Service',

                'summary' =>
                    'Updated ADMIN-17 test legal summary.',

                'content' =>
                    'This is updated ADMIN-17 legal policy text with enough characters for validation.',

                'revision_version' =>
                    0,
            ],
            $overrides
        );
    }

    private function publishPayload(
        array $overrides = []
    ): array {
        return array_merge(
            [
                'effective_at' =>
                    null,

                'revision_version' =>
                    0,

                'publish_confirmation' =>
                    true,
            ],
            $overrides
        );
    }

    private function draftPolicy(
        AdminUser $admin,
        array $overrides = []
    ): LegalPolicyVersion {
        $content =
            (string) (
                $overrides['content']
                ?? 'Draft legal policy content for ADMIN-17 testing with enough characters.'
            );

        return LegalPolicyVersion::query()
            ->create(
                array_merge(
                    [
                        'policy_type' =>
                            'TERMS_OF_SERVICE',

                        'version_number' =>
                            1,

                        'title' =>
                            'TradeX Legal Draft',

                        'summary' =>
                            'Test summary',

                        'content' =>
                            $content,

                        'content_sha256' =>
                            hash(
                                'sha256',
                                $content
                            ),

                        'status' =>
                            LegalPolicyVersion::STATUS_DRAFT,

                        'effective_at' =>
                            null,

                        'published_at' =>
                            null,

                        'revision_version' =>
                            0,

                        'created_by_admin_id' =>
                            $admin->id,

                        'updated_by_admin_id' =>
                            $admin->id,

                        'published_by_admin_id' =>
                            null,
                    ],
                    $overrides
                )
            );
    }

    private function publishedPolicy(
        AdminUser $admin,
        string $policyType = 'TERMS_OF_SERVICE',
        int $version = 1,
        array $overrides = []
    ): LegalPolicyVersion {
        $content =
            (string) (
                $overrides['content']
                ?? 'Published legal policy content for ADMIN-17 testing with enough characters.'
            );

        return LegalPolicyVersion::query()
            ->create(
                array_merge(
                    [
                        'policy_type' =>
                            $policyType,

                        'version_number' =>
                            $version,

                        'title' =>
                            'TradeX Published Legal Policy',

                        'summary' =>
                            'Published test summary',

                        'content' =>
                            $content,

                        'content_sha256' =>
                            hash(
                                'sha256',
                                $content
                            ),

                        'status' =>
                            LegalPolicyVersion::STATUS_PUBLISHED,

                        'effective_at' =>
                            now()->subMinute(),

                        'published_at' =>
                            now()->subMinute(),

                        'revision_version' =>
                            0,

                        'created_by_admin_id' =>
                            $admin->id,

                        'updated_by_admin_id' =>
                            $admin->id,

                        'published_by_admin_id' =>
                            $admin->id,
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
                        Str::uuid()
                            ->toString(),

                    'name' =>
                        'ADMIN-17 Test Actor',

                    'email' =>
                        'admin17-'
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
                        'ADMIN17_TEST_'
                        .Str::upper(
                            Str::random(8)
                        ),

                    'name' =>
                        'ADMIN-17 Test Role',

                    'description' =>
                        null,

                    'is_system' =>
                        false,

                    'is_active' =>
                        true,
                ]);

        $permissionIds = [];

        foreach (
            $codes
            as $code
        ) {
            $permission =
                AdminPermission::query()
                    ->where(
                        'code',
                        $code
                    )
                    ->firstOrFail();

            $permissionIds[] =
                $permission->id;
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
