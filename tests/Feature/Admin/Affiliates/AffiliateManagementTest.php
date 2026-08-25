<?php

namespace Tests\Feature\Admin\Affiliates;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\AffiliateProfile;
use App\Models\AffiliateStage;
use App\Models\User;
use App\Services\Affiliates\AffiliateCommercialService;
use Database\Seeders\AffiliateStageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AffiliateManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(AffiliateStageSeeder::class);
    }

    public function test_guest_is_redirected_from_affiliate_admin(): void
    {
        $this->get(
            route('admin.affiliates.index')
        )->assertRedirect(
            route('admin.login')
        );
    }

    public function test_affiliate_view_permission_is_required(): void
    {
        $admin = $this->adminWithPermissions([]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.affiliates.index'))
            ->assertForbidden();
    }

    public function test_mutation_form_request_enforces_affiliate_manage_permission_before_validation(): void
    {
        $admin = $this->adminWithPermissions([
            'affiliates.view',
        ]);

        $affiliate = $this->user('RBACMUTATE');

        $this->actingAs($admin, 'admin')
            ->patch(
                route(
                    'admin.affiliates.special_commission.update',
                    $affiliate
                ),
                [
                    'rate_percent' => '99.00',
                    'reason' => '',
                ]
            )
            ->assertForbidden();

        $this->assertDatabaseMissing('affiliate_profiles', [
            'user_id' => $affiliate->id,
        ]);
    }

    public function test_admin_can_view_affiliate_created_by_direct_referral_activity(): void
    {
        $admin = $this->adminWithPermissions([
            'affiliates.view',
        ]);

        $affiliate = $this->user('AFFROOT');
        $this->user('AFFDIRECT', $affiliate);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.affiliates.index'))
            ->assertOk()
            ->assertSee($affiliate->name)
            ->assertSee('AFFROOT');
    }

    public function test_referral_tree_is_direct_only_and_does_not_expand_second_level(): void
    {
        $admin = $this->adminWithPermissions([
            'affiliates.view_tree',
        ]);

        $affiliate = $this->user('ROOTCODE');
        $direct = $this->user('DIRECTCODE', $affiliate);
        $secondLevel = $this->user('SECONDLEVEL', $direct);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.affiliates.tree', $affiliate))
            ->assertOk()
            ->assertSee($direct->email)
            ->assertDontSee($secondLevel->email)
            ->assertSee('DIRECT_REFERRALS_ONLY');
    }

    public function test_management_can_approve_and_clear_special_commission_with_audit(): void
    {
        $admin = $this->adminWithPermissions([
            'affiliates.manage',
        ]);

        $affiliate = $this->user('SPECIAL01');

        $this->actingAs($admin, 'admin')
            ->patch(
                route(
                    'admin.affiliates.special_commission.update',
                    $affiliate
                ),
                [
                    'rate_percent' => '23.50',
                    'reason' => 'Founder-approved strategic partner exception.',
                ]
            )
            ->assertRedirect(
                route('admin.affiliates.show', $affiliate)
            );

        $profile = AffiliateProfile::query()
            ->where('user_id', $affiliate->id)
            ->firstOrFail();

        $this->assertSame(2350, $profile->special_commission_bps);
        $this->assertSame(
            $admin->id,
            $profile->special_commission_approved_by_admin_id
        );

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'AFFILIATE_SPECIAL_COMMISSION_APPROVED',
            'module' => 'AFFILIATES',
            'target_id' => $profile->id,
        ]);

        $this->actingAs($admin, 'admin')
            ->delete(
                route(
                    'admin.affiliates.special_commission.destroy',
                    $affiliate
                )
            )
            ->assertRedirect(
                route('admin.affiliates.show', $affiliate)
            );

        $profile->refresh();

        $this->assertNull($profile->special_commission_bps);
        $this->assertNull($profile->special_commission_approved_at);
        $this->assertNull($profile->special_commission_approved_by_admin_id);
        $this->assertNull($profile->special_commission_reason);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'AFFILIATE_SPECIAL_COMMISSION_CLEARED',
            'module' => 'AFFILIATES',
            'target_id' => $profile->id,
        ]);
    }

    public function test_special_commission_cannot_exceed_twenty_four_percent(): void
    {
        $admin = $this->adminWithPermissions([
            'affiliates.manage',
        ]);

        $affiliate = $this->user('SPECIALMAX');

        $this->actingAs($admin, 'admin')
            ->from(route('admin.affiliates.index'))
            ->patch(
                route(
                    'admin.affiliates.special_commission.update',
                    $affiliate
                ),
                [
                    'rate_percent' => '24.01',
                    'reason' => 'Invalid over-ceiling attempt.',
                ]
            )
            ->assertRedirect(route('admin.affiliates.index'))
            ->assertSessionHasErrors('rate_percent');

        $this->assertDatabaseMissing('affiliate_profiles', [
            'user_id' => $affiliate->id,
        ]);
    }

    public function test_stage_manager_can_update_future_commercial_values_without_changing_structural_band(): void
    {
        $admin = $this->adminWithPermissions([
            'affiliate_stages.manage',
        ]);

        $stage = AffiliateStage::query()
            ->where('code', AffiliateStage::STARTER)
            ->firstOrFail();

        $originalMin = $stage->min_qualified_sales;
        $originalMax = $stage->max_qualified_sales;
        $originalMilestone = $stage->milestone_sales;

        $this->actingAs($admin, 'admin')
            ->patch(
                route('admin.affiliates.stages.update', $stage),
                [
                    'name' => 'Starter Plus',
                    'standard_commission_percent' => '16.00',
                    'milestone_gift_usd' => '80.00',
                    'commercial_rule' => 'Updated future-sale commercial rule.',
                ]
            )
            ->assertRedirect(
                route('admin.affiliates.stages.index')
            );

        $stage->refresh();

        $this->assertSame('Starter Plus', $stage->name);
        $this->assertSame(1600, $stage->standard_commission_bps);
        $this->assertSame(8000, $stage->milestone_gift_cents);
        $this->assertSame($originalMin, $stage->min_qualified_sales);
        $this->assertSame($originalMax, $stage->max_qualified_sales);
        $this->assertSame($originalMilestone, $stage->milestone_sales);

        $growth = AffiliateStage::query()
            ->where('code', AffiliateStage::GROWTH)
            ->firstOrFail();

        $this->assertSame(20500, $growth->cumulative_gift_cents);

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'AFFILIATE_STAGE_COMMERCIAL_UPDATED',
            'module' => 'AFFILIATES',
            'target_id' => $stage->id,
        ]);
    }

    public function test_commission_viewer_can_read_existing_immutable_ledger(): void
    {
        $admin = $this->adminWithPermissions([
            'commissions.view',
        ]);

        $affiliate = $this->user('LEDGERROOT');
        $referred = $this->user('LEDGERBUYER', $affiliate);

        app(AffiliateCommercialService::class)
            ->recordClearedPayPalSale(
                $affiliate,
                $referred,
                'PAYPAL-ADMIN-AFF-001',
                10000
            );

        $this->actingAs($admin, 'admin')
            ->get(route('admin.affiliates.commissions.index'))
            ->assertOk()
            ->assertSee($affiliate->name)
            ->assertSee('15.00%')
            ->assertSee('$15.00');
    }

    private function adminWithPermissions(array $codes): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'ADMIN Affiliate Test',
            'email' => 'admin-aff-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);

        $role = AdminRole::query()->create([
            'code' => 'AFFTEST_'.strtoupper(Str::random(8)),
            'name' => 'Affiliate Test Role',
            'is_system' => false,
            'is_active' => true,
        ]);

        $admin->roles()->attach($role->id);

        foreach ($codes as $code) {
            $permission = AdminPermission::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'module' => 'AFFILIATES',
                ]
            );

            $role->permissions()->attach($permission->id);
        }

        return $admin->fresh();
    }

    private function user(
        string $referralCode,
        ?User $referrer = null
    ): User {
        return User::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'User '.$referralCode,
            'email' => Str::lower($referralCode).'@tradex.test',
            'whatsapp_number' => '+947'.random_int(10000000, 99999999),
            'whatsapp_verified_at' => now(),
            'referral_code' => $referralCode,
            'referred_by_user_id' => $referrer?->id,
            'country_code' => 'LK',
            'language_code' => 'en',
            'account_type' => 'PREMIUM',
            'status' => 'ACTIVE',
            'premium_activated_at' => now(),
        ]);
    }
}
