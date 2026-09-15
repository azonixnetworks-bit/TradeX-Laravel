<?php

namespace Tests\Feature\Admin\TradingTools;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\FundedAccountPartner;
use App\Models\FundedAdvisorReport;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class FundedAccountAdvisorAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_without_permission_cannot_open_advisor(): void
    {
        $admin = $this->createAdmin();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.funded_advisor.index'))
            ->assertForbidden();
    }

    public function test_authorized_admin_sees_customer_owned_report_monitor_not_customer_input_form(): void
    {
        $admin = $this->createAdminWithPermissions(['funded_advisor.view']);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.funded_advisor.index'));

        $response->assertOk();
        $response->assertSee('Funded Account Advisor');
        $response->assertSee('Customer-owned workflow');
        $response->assertSee('Customer-Generated Reports');
        $response->assertDontSee('Generate Customer Risk Plan');
        $response->assertDontSee('Customer Name');
        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control')
        );
    }

    public function test_authorized_admin_can_download_existing_customer_pdf_with_partner_link(): void
    {
        $admin = $this->createAdminWithPermissions(['funded_advisor.view']);
        $report = FundedAdvisorReport::query()->create([
            'customer_name' => 'PDF Customer',
            'customer_email' => 'pdf-customer@tradex.test',
            'prop_firm_name' => 'Example Funding',
            'partner_name_snapshot' => 'TradeX Test Funding',
            'affiliate_url_snapshot' => 'https://partner.example/ref/tradex',
            'promo_code_snapshot' => 'TRADEX10',
            'partner_offer_snapshot' => 'TradeX customer offer',
            'affiliate_disclosure_snapshot' => 'TradeX may receive a referral commission.',
            'inputs_json' => $this->validAdvisorInput(),
            'output_json' => [
                'status' => 'SAFE',
                'recommended_risk_amount' => 250,
                'recommended_risk_percent' => 0.25,
                'recommended_entries' => 3,
                'planned_daily_exposure' => 600,
                'minimum_rr' => 2,
                'target_profit_per_win' => 500,
                'daily_stop_reference' => 99400,
                'daily_loss_basis' => 'EQUITY',
                'daily_limit_amount' => 5000,
                'daily_loss_used' => 0,
                'daily_loss_remaining' => 5000,
                'daily_loss_used_percent' => 0,
                'drawdown_threshold' => 90000,
                'overall_drawdown_remaining' => 10000,
                'losses_before_rule_capacity' => 20,
                'profit_target_amount' => 10000,
                'remaining_profit_target' => 10000,
                'target_progress_percent' => 0,
                'estimated_wins_to_target' => 20,
                'remaining_minimum_trading_days' => 4,
                'entry_plan' => [
                    ['entry' => 1, 'risk_amount' => 250, 'risk_factor' => 1, 'instruction' => 'Use only if setup is valid.'],
                    ['entry' => 2, 'risk_amount' => 200, 'risk_factor' => .8, 'instruction' => 'Reduce risk after a loss.'],
                ],
                'warnings' => [],
                'disclaimer' => 'Risk planning only.',
            ],
            'generated_at' => now(),
        ]);

        $response = $this->actingAs($admin, 'admin')
            ->get(route('admin.funded_advisor.reports.pdf', $report));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-1.4', $response->getContent());
        $this->assertStringContainsString('https://partner.example/ref/tradex', $response->getContent());
        $this->assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control')
        );
    }

    public function test_partner_management_requires_separate_permission(): void
    {
        $viewer = $this->createAdminWithPermissions(['funded_advisor.view']);
        $manager = $this->createAdminWithPermissions(['funded_advisor.partners.manage']);

        $this->actingAs($viewer, 'admin')
            ->get(route('admin.funded_advisor.partners.index'))
            ->assertForbidden();

        $response = $this->actingAs($manager, 'admin')
            ->post(route('admin.funded_advisor.partners.store'), [
                'name' => 'Managed Funding Partner',
                'website_url' => 'https://funding.example',
                'affiliate_url' => 'https://funding.example/tradex',
                'promo_code' => 'TRADEX',
                'offer_text' => 'Partner offer',
                'priority' => 10,
                'is_active' => '1',
                'is_featured' => '1',
            ]);

        $partner = FundedAccountPartner::query()
            ->where('name', 'Managed Funding Partner')
            ->firstOrFail();

        $response->assertRedirect(route('admin.funded_advisor.partners.edit', $partner));
        $this->assertTrue($partner->is_active);
        $this->assertTrue($partner->is_featured);
    }

    /** @return array<string, mixed> */
    private function validAdvisorInput(): array
    {
        return [
            'prop_firm_name' => 'Example Funding',
            'phase' => 'EVALUATION',
            'account_size' => 100000,
            'profit_target_percent' => 10,
            'daily_loss_limit_percent' => 5,
            'max_drawdown_percent' => 10,
            'daily_loss_basis' => 'EQUITY',
            'daily_start_reference' => 100000,
            'drawdown_type' => 'STATIC',
            'drawdown_basis' => 'EQUITY',
            'trailing_reference_amount' => null,
            'current_balance' => 100000,
            'current_equity' => 100000,
            'desired_risk_percent' => .25,
            'planned_max_entries' => 3,
            'minimum_rr' => 2,
            'minimum_trading_days' => 5,
            'completed_trading_days' => 1,
        ];
    }

    /** @param array<int, string> $permissionCodes */
    private function createAdminWithPermissions(array $permissionCodes): AdminUser
    {
        $admin = $this->createAdmin();
        $role = AdminRole::query()->create([
            'code' => 'FUNDED_ADVISOR_TEST_'.strtoupper(Str::random(8)),
            'name' => 'Funded Advisor Test Role',
            'description' => 'Automated funded advisor RBAC role.',
            'is_system' => false,
            'is_active' => true,
        ]);

        foreach ($permissionCodes as $permissionCode) {
            $permission = AdminPermission::query()->create([
                'code' => $permissionCode,
                'name' => $permissionCode,
                'module' => 'TRADING_TOOLS',
                'description' => 'Automated funded advisor permission fixture.',
            ]);
            $role->permissions()->attach($permission->id);
        }

        $admin->roles()->attach($role->id);

        return $admin->fresh();
    }

    private function createAdmin(): AdminUser
    {
        return AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Funded Advisor Test Admin',
            'email' => 'funded-advisor-'.Str::lower(Str::random(12)).'@tradex.test',
            'email_verified_at' => now(),
            'password' => 'TestingPassword123!',
            'is_active' => true,
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }
}
