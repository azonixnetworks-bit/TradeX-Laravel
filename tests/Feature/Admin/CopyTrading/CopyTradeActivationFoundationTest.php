<?php

namespace Tests\Feature\Admin\CopyTrading;

use App\Models\CopyTradeAccountBenefit;
use App\Models\CopyTradeBrokerAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CopyTradeActivationFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_activation_foundation_tables_exist_with_required_columns(): void
    {
        $this->assertTrue(
            Schema::hasColumns(
                'copy_trade_verified_balance_snapshots',
                [
                    'uuid',
                    'broker_account_id',
                    'currency',
                    'verified_billable_balance_minor',
                    'source',
                    'observed_at',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumns(
                'copy_trade_account_benefits',
                [
                    'uuid',
                    'user_id',
                    'broker_account_id',
                    'education_entitlement_id',
                    'market_category',
                    'account_slot',
                    'cohort_position',
                    'benefit_type',
                    'assigned_at',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumns(
                'copy_trade_entitlement_periods',
                [
                    'uuid',
                    'broker_account_id',
                    'account_benefit_id',
                    'user_entitlement_id',
                    'entitlement_type',
                    'product_code',
                    'starts_at',
                    'ends_at',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumns(
                'copy_trade_activation_evaluations',
                [
                    'uuid',
                    'broker_account_id',
                    'overall_status',
                    'trigger_source',
                    'evaluated_at',
                    'activated_at',
                ]
            )
        );

        $this->assertTrue(
            Schema::hasColumns(
                'copy_trade_activation_evaluation_gates',
                [
                    'activation_evaluation_id',
                    'gate_code',
                    'passed',
                    'reason_code',
                    'evidence',
                ]
            )
        );
    }

    public function test_frozen_activation_commercial_boundaries_are_configured(): void
    {
        $this->assertSame(
            25000,
            config(
                'tradex_copy_trading.activation.minimum_verified_billable_balance_minor'
            )
        );

        $this->assertSame(
            'USD',
            config('tradex_copy_trading.activation.currency')
        );

        $this->assertSame(
            1000,
            config(
                'tradex_copy_trading.activation.first_eligible_users_lifetime_free_count'
            )
        );

        $this->assertSame(
            30,
            config(
                'tradex_copy_trading.activation.later_user_account_1_free_days'
            )
        );

        $this->assertSame(
            5,
            config('tradex_copy_trading.max_accounts_per_market')
        );
    }

    public function test_account_benefit_types_match_frozen_account_one_rules(): void
    {
        $this->assertSame(
            [
                'LIFETIME_FREE_ACCOUNT_1',
                'THIRTY_DAY_FREE_ACCOUNT_1',
                'PAID_REQUIRED',
            ],
            CopyTradeAccountBenefit::VALID_BENEFIT_TYPES
        );
    }

    public function test_broker_account_exposes_activation_foundation_relationships(): void
    {
        foreach ([
            'verifiedBalanceSnapshots',
            'latestVerifiedBalanceSnapshot',
            'accountBenefit',
            'entitlementPeriods',
            'activationEvaluations',
            'latestActivationEvaluation',
        ] as $relationship) {
            $this->assertTrue(
                method_exists(
                    CopyTradeBrokerAccount::class,
                    $relationship
                ),
                'Missing relationship: '.$relationship
            );
        }
    }
}
