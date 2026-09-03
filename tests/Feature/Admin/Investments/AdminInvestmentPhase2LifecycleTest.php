<?php

namespace Tests\Feature\Admin\Investments;

use App\Models\AdminRole;
use App\Models\AdminUser;
use App\Models\Investment;
use App\Models\InvestmentAgreement;
use App\Models\InvestmentProduct;
use App\Models\InvestmentRiskAcceptance;
use App\Models\InvestmentTransaction;
use App\Models\User;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\AdminRolePermissionSeeder;
use Database\Seeders\AdminRoleSeeder;
use Database\Seeders\InvestmentProductSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminInvestmentPhase2LifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('private');

        $this->seed(AdminRoleSeeder::class);
        $this->seed(AdminPermissionSeeder::class);
        $this->seed(AdminRolePermissionSeeder::class);
        $this->seed(InvestmentProductSeeder::class);
    }

    public function test_phase2_schema_adds_evidence_and_agreement_snapshot_fields(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'investment_risk_acceptances',
                [
                    'evidence_source',
                    'acceptance_reference',
                    'recorded_by_admin_id',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumns(
                'investment_agreements',
                [
                    'template_version',
                    'template_status',
                    'risk_disclosure_version',
                    'terms_snapshot',
                ]
            )
        );
    }

    public function test_investment_admin_phase2_permissions_preserve_payout_separation(): void
    {
        $role = AdminRole::query()
            ->where('code', 'INVESTMENT_ADMIN')
            ->firstOrFail();

        foreach ([
            'investments.deposits.record',
            'investments.risk_acceptance.record',
            'investments.agreements.verify',
            'investments.wallets.verify',
        ] as $permission) {
            $this->assertTrue(
                $role->hasPermission($permission),
                $permission
            );
        }

        $this->assertFalse(
            $role->hasPermission(
                'investments.payouts.approve'
            )
        );
    }

    public function test_deposit_must_exactly_match_investment_amount(): void
    {
        [$admin, $investment] =
            $this->investmentFixture();

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.deposits.store',
                    $investment
                ),
                [
                    'amount' => '9999',
                    'txid' => str_repeat('a', 64),
                    'receiving_address' =>
                        'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE',
                    'occurred_at' =>
                        now()->toDateTimeString(),
                ]
            )
            ->assertSessionHasErrors('deposit');

        $this->assertDatabaseCount(
            'investment_transactions',
            0
        );
    }

    public function test_deposit_record_verify_and_risk_acceptance_advance_lifecycle(): void
    {
        [$admin, $investment] =
            $this->investmentFixture();

        $transaction = $this->recordDeposit(
            $admin,
            $investment,
            'b'
        );

        $investment->refresh();

        $this->assertSame(
            Investment::STATUS_DEPOSIT_VERIFICATION,
            $investment->status
        );

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.deposits.verify',
                    [$investment, $transaction]
                )
            )
            ->assertRedirect();

        $investment->refresh();
        $transaction->refresh();

        $this->assertSame(
            InvestmentTransaction::STATUS_VERIFIED,
            $transaction->status
        );

        $this->assertSame(
            Investment::STATUS_AWAITING_RISK_ACCEPTANCE,
            $investment->status
        );

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.risk.store',
                    $investment
                ),
                $this->riskPayload()
            )
            ->assertRedirect();

        $investment->refresh();

        $this->assertSame(
            Investment::STATUS_AWAITING_AGREEMENT,
            $investment->status
        );

        $this->assertDatabaseHas(
            'investment_risk_acceptances',
            [
                'investment_id' =>
                    $investment->getKey(),
                'disclosure_version' =>
                    config(
                        'tradex_investments.risk.version'
                    ),
                'evidence_source' =>
                    InvestmentRiskAcceptance::SOURCE_ADMIN_RECORDED,
                'acceptance_reference' =>
                    'CUSTOMER-EVIDENCE-001',
            ]
        );
    }

    public function test_duplicate_trc20_txid_is_blocked_globally(): void
    {
        [$admin, $first] =
            $this->investmentFixture();

        [, $second] =
            $this->investmentFixture();

        $this->recordDeposit(
            $admin,
            $first,
            'c'
        );

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.deposits.store',
                    $second
                ),
                [
                    'amount' => '10000.00000000',
                    'txid' => str_repeat('c', 64),
                    'receiving_address' =>
                        'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE',
                    'occurred_at' =>
                        now()->toDateTimeString(),
                ]
            )
            ->assertSessionHasErrors('deposit');

        $this->assertDatabaseCount(
            'investment_transactions',
            1
        );
    }

    public function test_risk_acceptance_is_blocked_before_verified_deposit(): void
    {
        [$admin, $investment] =
            $this->investmentFixture();

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.risk.store',
                    $investment
                ),
                $this->riskPayload()
            )
            ->assertSessionHasErrors(
                'risk_acceptance'
            );

        $this->assertDatabaseCount(
            'investment_risk_acceptances',
            0
        );
    }

    public function test_agreement_generation_creates_private_hashed_snapshot(): void
    {
        [$admin, $investment] =
            $this->readyForAgreement('d');

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.agreements.generate',
                    $investment
                )
            )
            ->assertRedirect();

        $agreement =
            InvestmentAgreement::query()
                ->firstOrFail();

        $this->assertSame(
            InvestmentAgreement::STATUS_GENERATED,
            $agreement->status
        );

        $this->assertSame(
            config('tradex_investments.risk.version'),
            $agreement->risk_disclosure_version
        );

        $this->assertFalse(
            (bool) (
                $agreement->terms_snapshot[
                    'fixed_return_guaranteed'
                ] ?? true
            )
        );

        Storage::disk('private')
            ->assertExists(
                $agreement->document_path
            );

        $bytes = Storage::disk('private')
            ->get($agreement->document_path);

        $this->assertSame(
            hash('sha256', $bytes),
            $agreement->document_sha256
        );
    }

    public function test_complete_gates_activate_and_generate_six_two_month_periods_for_one_year(): void
    {
        [$admin, $investment] =
            $this->readyForAgreement(
                'e',
                years: 1,
                interval: 2
            );

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.wallet.verify',
                    $investment
                )
            )
            ->assertRedirect();

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.agreements.generate',
                    $investment
                )
            )
            ->assertRedirect();

        $agreement =
            InvestmentAgreement::query()
                ->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.agreements.signed.upload',
                    [$investment, $agreement]
                ),
                [
                    'signed_document' =>
                        $this->signedPdf(),
                    'signed_at' =>
                        now()->toDateTimeString(),
                ]
            )
            ->assertRedirect();

        $agreement->refresh();

        $this->assertSame(
            InvestmentAgreement::STATUS_SIGNED,
            $agreement->status
        );

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.agreements.verify',
                    [$investment, $agreement]
                )
            )
            ->assertRedirect();

        $agreement->refresh();

        $this->assertSame(
            InvestmentAgreement::STATUS_VERIFIED,
            $agreement->status
        );

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.activate',
                    $investment
                )
            )
            ->assertRedirect(
                route(
                    'admin.investments.show',
                    $investment
                )
            );

        $investment->refresh();

        $this->assertSame(
            Investment::STATUS_ACTIVE,
            $investment->status
        );

        $this->assertNotNull($investment->start_date);
        $this->assertNotNull($investment->maturity_date);
        $this->assertNotNull(
            $investment->principal_settlement_date
        );

        $this->assertSame(
            '10000.00000000',
            $investment->high_water_mark_usdt
        );

        $this->assertSame(
            6,
            $investment->profitPeriods()->count()
        );

        $this->assertDatabaseHas(
            'audit_logs',
            [
                'action' =>
                    'INVESTMENT_ACTIVATED',
                'module' => 'INVESTMENTS',
                'target_id' =>
                    $investment->getKey(),
            ]
        );
    }

    public function test_production_activation_uses_signed_agreement_template_snapshot_for_legal_gate(): void
    {
        config([
            'tradex_investments.agreement.template_status'
                => 'DRAFT_REQUIRES_LEGAL_REVIEW',
        ]);

        [$admin, $investment] =
            $this->readyForAgreement('9');

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.wallet.verify',
                    $investment
                )
            )
            ->assertRedirect();

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.agreements.generate',
                    $investment
                )
            )
            ->assertRedirect();

        $agreement =
            InvestmentAgreement::query()
                ->firstOrFail();

        $this->assertSame(
            'DRAFT_REQUIRES_LEGAL_REVIEW',
            $agreement->template_status
        );

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.agreements.signed.upload',
                    [$investment, $agreement]
                ),
                [
                    'signed_document' =>
                        $this->signedPdf(),
                    'signed_at' =>
                        now()->toDateTimeString(),
                ]
            )
            ->assertRedirect();

        $agreement->refresh();

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.agreements.verify',
                    [$investment, $agreement]
                )
            )
            ->assertRedirect();

        config([
            'tradex_investments.agreement.template_status'
                => 'APPROVED',
            'tradex_investments.agreement.production_activation_requires_approved_template'
                => true,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.activate',
                    $investment
                )
            )
            ->assertSessionHasErrors('activation');

        $this->assertNotSame(
            Investment::STATUS_ACTIVE,
            $investment->fresh()->status
        );
    }

    public function test_activation_remains_blocked_without_verified_agreement_and_wallet(): void
    {
        [$admin, $investment] =
            $this->readyForAgreement('f');

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.activate',
                    $investment
                )
            )
            ->assertSessionHasErrors('activation');

        $this->assertNotSame(
            Investment::STATUS_ACTIVE,
            $investment->fresh()->status
        );
    }

    private function readyForAgreement(
        string $txChar,
        int $years = 2,
        int $interval = 4
    ): array {
        [$admin, $investment] =
            $this->investmentFixture(
                years: $years,
                interval: $interval
            );

        $transaction = $this->recordDeposit(
            $admin,
            $investment,
            $txChar
        );

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.deposits.verify',
                    [$investment, $transaction]
                )
            )
            ->assertRedirect();

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.risk.store',
                    $investment
                ),
                $this->riskPayload()
            )
            ->assertRedirect();

        return [
            $admin,
            $investment->fresh(),
        ];
    }

    private function recordDeposit(
        AdminUser $admin,
        Investment $investment,
        string $hexCharacter
    ): InvestmentTransaction {
        $txid = str_repeat(
            strtolower($hexCharacter),
            64
        );

        $this->actingAs($admin, 'admin')
            ->post(
                route(
                    'admin.investments.deposits.store',
                    $investment
                ),
                [
                    'amount' => '10000.00000000',
                    'txid' => $txid,
                    'receiving_address' =>
                        'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE',
                    'occurred_at' =>
                        now()->toDateTimeString(),
                ]
            )
            ->assertRedirect();

        return InvestmentTransaction::query()
            ->where(
                'investment_id',
                $investment->getKey()
            )
            ->latest('id')
            ->firstOrFail();
    }

    private function riskPayload(): array
    {
        return [
            'accepted_at' =>
                now()->toDateTimeString(),
            'acceptance_reference' =>
                'CUSTOMER-EVIDENCE-001',
            'ack_read_disclosure' => '1',
            'ack_capital_loss' => '1',
            'ack_crypto_volatility' => '1',
            'ack_stablecoin_risk' => '1',
            'ack_network_cyber_risk' => '1',
            'ack_regulatory_risk' => '1',
            'ack_variable_returns' => '1',
            'ack_early_exit_rule' => '1',
        ];
    }

    private function signedPdf(): UploadedFile
    {
        $path = tempnam(
            sys_get_temp_dir(),
            'tradex-signed-'
        );

        file_put_contents(
            $path,
            "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF"
        );

        return new UploadedFile(
            $path,
            'signed-agreement.pdf',
            'application/pdf',
            null,
            true
        );
    }

    private function investmentFixture(
        int $years = 2,
        int $interval = 4
    ): array {
        $admin = $this->investmentAdmin();

        $user = User::factory()->create([
            'uuid' => Str::uuid()->toString(),
            'status' => 'ACTIVE',
        ]);

        $product = InvestmentProduct::query()
            ->where(
                'code',
                InvestmentProduct::CODE_CRYPTO
            )
            ->firstOrFail();

        $investment = Investment::query()->create([
            'uuid' => Str::uuid()->toString(),
            'investment_code' =>
                'TXI-TEST-'
                .Str::upper(Str::random(8)),
            'user_id' => $user->getKey(),
            'investment_product_id' =>
                $product->getKey(),
            'status' =>
                Investment::STATUS_AWAITING_DEPOSIT,
            'profit_model' =>
                Investment::PROFIT_MODEL_PERFORMANCE_SHARE,
            'deposit_amount' => '10000.00000000',
            'deposit_currency' => 'USDT',
            'deposit_network' => 'TRC20',
            'investment_period_years' => $years,
            'profit_review_interval_months' =>
                $interval,
            'customer_profit_share_bps' => 8000,
            'company_profit_share_bps' => 2000,
            'early_exit_return_bps' => 8000,
            'fixed_return_guaranteed' => false,
            'settlement_mode' =>
                Investment::SETTLEMENT_ON_MATURITY,
            'principal_settlement_status' =>
                Investment::PRINCIPAL_NOT_DUE,
            'payout_wallet_address' =>
                'TQn9Y2khEsLJW1ChVWFMSMeRDow5KcbLSE',
            'payout_wallet_network' => 'TRC20',
            'high_water_mark_usdt' => '0',
            'loss_carryforward_usdt' => '0',
            'created_by_admin_id' =>
                $admin->getKey(),
            'updated_by_admin_id' =>
                $admin->getKey(),
        ]);

        return [$admin, $investment];
    }

    private function investmentAdmin(): AdminUser
    {
        $admin = AdminUser::query()->create([
            'uuid' => Str::uuid()->toString(),
            'name' => 'Investment Phase 2 Admin',
            'email' =>
                'investment-phase2-'
                .Str::lower(Str::random(8))
                .'@example.test',
            'email_verified_at' => now(),
            'password' =>
                'Investment#AdminPassword2026!',
            'is_active' => true,
            'failed_login_attempts' => 0,
        ]);

        $role = AdminRole::query()
            ->where('code', 'INVESTMENT_ADMIN')
            ->firstOrFail();

        $admin->roles()->attach(
            $role->getKey()
        );

        return $admin;
    }
}
