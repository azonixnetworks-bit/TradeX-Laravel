<?php

namespace Tests\Feature\Trading;

use App\Models\EconomicEvent;
use App\Models\FundedAccountPartner;
use App\Models\FundedNoTradeRule;
use App\Services\Trading\FundedNoTradeZoneService;
use Carbon\CarbonImmutable;
use Database\Seeders\FundedNoTradeZoneRuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FundedNoTradeZoneRuleSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_official_rule_pack_is_idempotent_and_matches_only_real_no_trade_rules(): void
    {
        $this->seed(FundedNoTradeZoneRuleSeeder::class);
        $firstCount = FundedNoTradeRule::query()->count();
        $this->seed(FundedNoTradeZoneRuleSeeder::class);

        $this->assertSame($firstCount, FundedNoTradeRule::query()->count());
        $this->assertSame(4, FundedAccountPartner::query()->whereIn('name', [
            'FTMO', 'FundingPips', 'The5ers', 'FundedNext',
        ])->count());

        $engine = app(FundedNoTradeZoneService::class);
        $ftmo = FundedAccountPartner::query()->where('name', 'FTMO')->firstOrFail();

        $cpi = $this->event('CPI y/y', 'USD');
        $zone = $engine->buildZone(
            $ftmo,
            $cpi,
            'Standard',
            FundedNoTradeRule::PHASE_FUNDED,
            CarbonImmutable::parse('2026-09-16 11:59:00', 'UTC')
        );

        $this->assertNotNull($zone);
        $this->assertSame('HARD_BREACH', $zone['enforcement_type']);
        $this->assertSame(2, $zone['minutes_before']);
        $this->assertSame(2, $zone['minutes_after']);

        $unrelated = $this->event('Core Retail Sales m/m', 'USD');
        $this->assertNull($engine->buildZone(
            $ftmo,
            $unrelated,
            'Standard',
            FundedNoTradeRule::PHASE_FUNDED,
            CarbonImmutable::parse('2026-09-16 11:59:00', 'UTC')
        ));

        $fundedNext = FundedAccountPartner::query()->where('name', 'FundedNext')->firstOrFail();
        $conditionalRule = $engine->resolveRule(
            $fundedNext,
            $cpi,
            'Stellar 2-Step',
            FundedNoTradeRule::PHASE_FUNDED,
            CarbonImmutable::parse('2026-09-16 11:59:00', 'UTC')
        );

        $this->assertNotNull($conditionalRule);
        $this->assertSame(FundedNoTradeRule::POLICY_CONDITIONAL, $conditionalRule->policy);
        $this->assertSame(FundedNoTradeRule::ENFORCEMENT_REWARD_SHARE, $conditionalRule->enforcement_type);
        $this->assertNull($engine->buildZone(
            $fundedNext,
            $cpi,
            'Stellar 2-Step',
            FundedNoTradeRule::PHASE_FUNDED,
            CarbonImmutable::parse('2026-09-16 11:59:00', 'UTC')
        ));
    }

    private function event(string $title, string $currency): EconomicEvent
    {
        return EconomicEvent::query()->create([
            'title' => $title,
            'country_code' => 'US',
            'currency' => $currency,
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
    }
}
