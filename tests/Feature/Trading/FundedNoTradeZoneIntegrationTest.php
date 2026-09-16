<?php

namespace Tests\Feature\Trading;

use App\Models\EconomicEvent;
use App\Models\EconomicEventPair;
use App\Models\FundedAccountPartner;
use App\Models\FundedNoTradeRule;
use App\Models\TradingRoutinePreference;
use App\Services\Trading\FundedAccountSelectionService;
use App\Services\Trading\FundedNoTradeZoneCustomerService;
use App\Services\Trading\TradingRoutineDailyPlanService;
use App\Services\Trading\TradingRoutinePdfService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTradeXMobileSession;
use Tests\TestCase;

class FundedNoTradeZoneIntegrationTest extends TestCase
{
    use RefreshDatabase;
    use CreatesTradeXMobileSession;

    public function test_funded_rule_flows_from_economic_event_into_daily_routine_and_pdf(): void
    {
        $user = $this->createApiUser();

        $partner = FundedAccountPartner::query()->create([
            'name' => 'Test Prop Firm',
            'is_active' => true,
            'is_featured' => false,
            'priority' => 10,
        ]);

        FundedNoTradeRule::query()->create([
            'funded_account_partner_id' => $partner->id,
            'program_name' => '2-Step',
            'phase' => FundedNoTradeRule::PHASE_CHALLENGE,
            'policy' => FundedNoTradeRule::POLICY_RESTRICTED,
            'impact_levels' => ['HIGH'],
            'minutes_before' => 30,
            'minutes_after' => 30,
            'affected_instruments_only' => true,
            'restricted_currencies' => ['USD'],
            'restricted_instruments' => ['EURUSD'],
            'block_open_new_trades' => true,
            'block_close_trades' => false,
            'block_pending_orders' => true,
            'rule_summary' => 'Do not open trades around high-impact USD news.',
            'source_reference' => 'Official test rule',
            'verified_at' => CarbonImmutable::parse('2026-09-15 12:00:00', 'UTC'),
            'is_active' => true,
            'priority' => 10,
        ]);

        $event = EconomicEvent::query()->create([
            'title' => 'US CPI',
            'country_code' => 'US',
            'currency' => 'USD',
            'impact' => EconomicEvent::IMPACT_HIGH,
            'scheduled_at_utc' => CarbonImmutable::parse('2026-09-16 12:00:00', 'UTC'),
            'source_timezone' => 'UTC',
            'status' => EconomicEvent::STATUS_SCHEDULED,
            'bias' => EconomicEvent::BIAS_NEUTRAL,
            'confidence_score' => 0,
            'volatility' => EconomicEvent::VOLATILITY_HIGH,
            'is_published' => true,
            'published_at' => CarbonImmutable::parse('2026-09-15 12:00:00', 'UTC'),
        ]);

        EconomicEventPair::query()->create([
            'economic_event_id' => $event->id,
            'market' => 'FOREX',
            'symbol' => 'EURUSD',
            'bias' => EconomicEvent::BIAS_NEUTRAL,
            'confidence_score' => 0,
            'volatility' => EconomicEvent::VOLATILITY_HIGH,
            'mapping_source' => 'TEST',
            'is_primary' => true,
            'sort_order' => 1,
        ]);

        app(FundedAccountSelectionService::class)->save(
            $user,
            $partner,
            '2-Step',
            FundedNoTradeRule::PHASE_CHALLENGE
        );

        $snapshot = app(FundedNoTradeZoneCustomerService::class)->snapshotForUserDate(
            $user,
            '2026-09-16',
            'UTC',
            CarbonImmutable::parse('2026-09-16 11:45:00', 'UTC')
        );

        $this->assertSame('NO_TRADE_ACTIVE', $snapshot['status']);
        $this->assertSame(1, $snapshot['zone_count']);
        $this->assertSame('11:30 - 12:30', $snapshot['zones'][0]['local_window_label']);
        $this->assertSame(['EURUSD'], $snapshot['zones'][0]['affected_instruments']);
        $this->assertSame('VERIFIED', $snapshot['zones'][0]['rule_verification_status']);

        $preference = TradingRoutinePreference::query()->create([
            'user_id' => $user->id,
            'enabled' => true,
            'timezone' => 'UTC',
            'delivery_time_local' => '06:00:00',
            'planning_balance' => 10000,
            'risk_per_trade_percent' => 0.5,
            'daily_risk_cap_percent' => 1.0,
            'max_trades' => 3,
            'max_consecutive_losses' => 2,
            'minimum_rr' => 2,
            'markets_json' => ['FOREX'],
            'sessions_json' => ['NEW_YORK'],
            'whatsapp_pdf_enabled' => false,
        ]);

        $plan = app(TradingRoutineDailyPlanService::class)->createOrGet(
            $user,
            $preference,
            CarbonImmutable::parse('2026-09-16 10:00:00', 'UTC')
        );

        $planData = (array) $plan->plan_json;
        $this->assertSame(1, $planData['funded_no_trade_zone']['zone_count']);
        $this->assertStringContainsString(
            'FUNDED NO TRADE 11:30 - 12:30 UTC',
            implode("\n", (array) $planData['no_trade_rules'])
        );

        $pdf = app(TradingRoutinePdfService::class)->render($plan);
        $this->assertStringStartsWith('%PDF-1.4', $pdf);
        $this->assertStringContainsString('FUNDED NO TRADE', $pdf);
    }
}
