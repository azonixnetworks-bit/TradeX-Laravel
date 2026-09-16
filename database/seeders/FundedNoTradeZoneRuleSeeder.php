<?php

namespace Database\Seeders;

use App\Models\FundedAccountPartner;
use App\Models\FundedNoTradeRule;
use Illuminate\Database\Seeder;

class FundedNoTradeZoneRuleSeeder extends Seeder
{
    private const VERIFIED_AT = '2026-09-16 00:00:00';

    public function run(): void
    {
        $ftmo = $this->partner(
            'FTMO',
            'https://ftmo.com/',
            ['Standard', 'Swing'],
            10
        );
        $fundingPips = $this->partner(
            'FundingPips',
            'https://fundingpips.com/',
            ['1 Step Flex', '2 Step Standard', '2 Step Flex', '2 Step Pro', 'Zero'],
            20
        );
        $the5ers = $this->partner(
            'The5ers',
            'https://the5ers.com/',
            ['High Stakes', 'Instant Funding (Hyper-Growth)', 'Bootcamp'],
            30
        );
        $fundedNext = $this->partner(
            'FundedNext',
            'https://fundednext.com/',
            ['Stellar 1-Step', 'Stellar 2-Step', 'Stellar Lite'],
            40
        );

        $this->seedFtmo($ftmo);
        $this->seedFundingPips($fundingPips);
        $this->seedThe5ers($the5ers);
        $this->seedFundedNext($fundedNext);
    }

    private function seedFtmo(FundedAccountPartner $partner): void
    {
        $source = 'https://ftmo.com/faq/can-i-trade-news/';

        foreach ([FundedNoTradeRule::PHASE_CHALLENGE, FundedNoTradeRule::PHASE_VERIFICATION] as $phase) {
            $this->rule($partner, 'FTMO Standard evaluation news rule '.$phase, [
                'program_name' => 'Standard',
                'phase' => $phase,
                'policy' => FundedNoTradeRule::POLICY_ALLOWED,
                'enforcement_type' => FundedNoTradeRule::ENFORCEMENT_GUIDANCE,
                'impact_levels' => ['HIGH'],
                'minutes_before' => 0,
                'minutes_after' => 0,
                'affected_instruments_only' => true,
                'block_open_new_trades' => false,
                'block_close_trades' => false,
                'block_pending_orders' => false,
                'rule_summary' => 'FTMO states its selected-news restriction does not apply during the Evaluation Process, including Challenge and Verification, subject to its forbidden trading practices.',
                'source_url' => $source,
                'priority' => 100,
            ]);
        }

        $this->rule($partner, 'FTMO Swing news trading allowed', [
            'program_name' => 'Swing',
            'phase' => FundedNoTradeRule::PHASE_ALL,
            'policy' => FundedNoTradeRule::POLICY_ALLOWED,
            'enforcement_type' => FundedNoTradeRule::ENFORCEMENT_GUIDANCE,
            'impact_levels' => ['HIGH'],
            'minutes_before' => 0,
            'minutes_after' => 0,
            'affected_instruments_only' => true,
            'block_open_new_trades' => false,
            'block_close_trades' => false,
            'block_pending_orders' => false,
            'rule_summary' => 'FTMO states Swing accounts have no special restrictions for trading during news releases.',
            'source_url' => $source,
            'priority' => 100,
        ]);

        $currencyRules = [
            'USD' => [
                'keywords' => [
                    'FEDERAL FUNDS RATE', 'FOMC STATEMENT', 'NON-FARM EMPLOYMENT CHANGE',
                    'NONFARM PAYROLL', 'UNEMPLOYMENT RATE', 'AVERAGE HOURLY EARNINGS',
                    'ADVANCE GDP', 'FOMC MEETING MINUTES', 'CPI Y/Y',
                ],
                'instruments' => [
                    'EURUSD', 'GBPUSD', 'USDJPY', 'USDCHF', 'AUDUSD', 'NZDUSD', 'USDCAD',
                    'XAUUSD', 'US30', 'US100', 'US500', 'US2000', 'DXY',
                    'US30.CASH', 'US100.CASH', 'US500.CASH', 'US2000.CASH', 'DXY.CASH',
                ],
            ],
            'EUR' => ['keywords' => ['MAIN REFINANCING RATE']],
            'GBP' => ['keywords' => ['OFFICIAL BANK RATE', 'MPC VOTES', 'MPC OFFICIAL BANK RATE VOTES', 'CPI Y/Y']],
            'CAD' => ['keywords' => ['OVERNIGHT RATE', 'BOC RATE STATEMENT', 'CPI M/M', 'EMPLOYMENT CHANGE', 'UNEMPLOYMENT RATE']],
            'AUD' => ['keywords' => ['CASH RATE', 'RBA STATEMENT', 'EMPLOYMENT CHANGE', 'UNEMPLOYMENT RATE', 'CPI M/M', 'CPI Y/Y', 'GDP Q/Q']],
            'NZD' => ['keywords' => ['OFFICIAL CASH RATE', 'RBNZ RATE STATEMENT', 'EMPLOYMENT CHANGE', 'UNEMPLOYMENT RATE', 'CPI Q/Q', 'GDP Q/Q']],
            'CHF' => ['keywords' => ['SNB POLICY RATE']],
        ];

        $priority = 10;
        foreach ($currencyRules as $currency => $config) {
            $this->rule($partner, 'FTMO Standard funded selected news '.$currency, [
                'program_name' => 'Standard',
                'phase' => FundedNoTradeRule::PHASE_FUNDED,
                'policy' => FundedNoTradeRule::POLICY_RESTRICTED,
                'enforcement_type' => FundedNoTradeRule::ENFORCEMENT_HARD_BREACH,
                'impact_levels' => ['HIGH'],
                'event_keywords' => $config['keywords'],
                'minutes_before' => 2,
                'minutes_after' => 2,
                'affected_instruments_only' => $currency !== 'USD',
                'restricted_currencies' => [$currency],
                'restricted_instruments' => $config['instruments'] ?? null,
                'block_open_new_trades' => true,
                'block_close_trades' => true,
                'block_pending_orders' => true,
                'rule_summary' => 'FTMO Standard FTMO Accounts may not open or close targeted instruments, including pending-order execution such as SL/TP, from 2 minutes before until 2 minutes after selected macroeconomic releases. TradeX matches only the selected release names maintained in this rule.',
                'source_url' => $source,
                'priority' => $priority++,
            ]);
        }

        $this->rule($partner, 'FTMO Standard funded crude oil inventories', [
            'program_name' => 'Standard',
            'phase' => FundedNoTradeRule::PHASE_FUNDED,
            'policy' => FundedNoTradeRule::POLICY_RESTRICTED,
            'enforcement_type' => FundedNoTradeRule::ENFORCEMENT_HARD_BREACH,
            'impact_levels' => ['HIGH'],
            'event_keywords' => ['CRUDE OIL INVENTORIES'],
            'minutes_before' => 2,
            'minutes_after' => 2,
            'affected_instruments_only' => false,
            'restricted_currencies' => ['USD'],
            'restricted_instruments' => ['USOIL', 'UKOIL', 'USOIL.CASH', 'UKOIL.CASH'],
            'block_open_new_trades' => true,
            'block_close_trades' => true,
            'block_pending_orders' => true,
            'rule_summary' => 'FTMO lists Crude Oil Inventories as a restricted selected release for its crude-oil instruments on Standard FTMO Accounts.',
            'source_url' => $source,
            'priority' => 9,
        ]);
    }

    private function seedFundingPips(FundedAccountPartner $partner): void
    {
        $source = 'https://help.fundingpips.com/hc/en-us/articles/34504137479441-News-Trading-Weekend-Holding';

        foreach ([FundedNoTradeRule::PHASE_CHALLENGE, FundedNoTradeRule::PHASE_VERIFICATION] as $phase) {
            $this->rule($partner, 'FundingPips evaluation purposeful news trading '.$phase, [
                'program_name' => null,
                'phase' => $phase,
                'policy' => FundedNoTradeRule::POLICY_CONDITIONAL,
                'enforcement_type' => FundedNoTradeRule::ENFORCEMENT_HARD_BREACH,
                'impact_levels' => ['HIGH'],
                'minutes_before' => 0,
                'minutes_after' => 0,
                'affected_instruments_only' => true,
                'block_open_new_trades' => false,
                'block_close_trades' => false,
                'block_pending_orders' => false,
                'rule_summary' => 'FundingPips says evaluation stages have no fixed news window restriction for holding/managing trades, but purposely trading high-impact news is prohibited and can close the account. This is stored as a conditional warning, not an automated no-trade window.',
                'source_url' => $source,
                'priority' => 100,
            ]);
        }

        foreach (['1 Step Flex', '2 Step Standard', '2 Step Flex', '2 Step Pro'] as $program) {
            $this->rule($partner, 'FundingPips '.$program.' Master high-impact news', [
                'program_name' => $program,
                'phase' => FundedNoTradeRule::PHASE_FUNDED,
                'policy' => FundedNoTradeRule::POLICY_RESTRICTED,
                'enforcement_type' => FundedNoTradeRule::ENFORCEMENT_PROFIT_DEDUCTION,
                'impact_levels' => ['HIGH'],
                'minutes_before' => 5,
                'minutes_after' => 5,
                'affected_instruments_only' => true,
                'block_open_new_trades' => true,
                'block_close_trades' => true,
                'block_pending_orders' => true,
                'rule_summary' => 'FundingPips Master-account trades opened or closed within 5 minutes before/after high-impact news on affected currencies can have the full profit deducted. Their official rule includes a 5-hour pre-event exception for some already-open trades; TradeX uses the conservative 5+5 no-trade recommendation because trade age is not part of the routine snapshot.',
                'source_url' => $source,
                'priority' => 20,
            ]);
        }

        $this->rule($partner, 'FundingPips Zero Master high-impact news', [
            'program_name' => 'Zero',
            'phase' => FundedNoTradeRule::PHASE_FUNDED,
            'policy' => FundedNoTradeRule::POLICY_RESTRICTED,
            'enforcement_type' => FundedNoTradeRule::ENFORCEMENT_HARD_BREACH,
            'impact_levels' => ['HIGH'],
            'minutes_before' => 10,
            'minutes_after' => 10,
            'affected_instruments_only' => true,
            'block_open_new_trades' => true,
            'block_close_trades' => true,
            'block_pending_orders' => true,
            'rule_summary' => 'FundingPips Zero Master Accounts may not open, close or hold positions during the 10 minutes before through 10 minutes after a high-impact event on the affected currency; the official policy describes this as a hard breach.',
            'source_url' => $source,
            'priority' => 10,
        ]);
    }

    private function seedThe5ers(FundedAccountPartner $partner): void
    {
        $source = 'https://the5ers.com/faqs/can-i-trade-during-news/';

        $this->rule($partner, 'The5ers High Stakes high-impact execution restriction', [
            'program_name' => 'High Stakes',
            'phase' => FundedNoTradeRule::PHASE_ALL,
            'policy' => FundedNoTradeRule::POLICY_RESTRICTED,
            'enforcement_type' => FundedNoTradeRule::ENFORCEMENT_SOFT_BREACH,
            'impact_levels' => ['HIGH'],
            'minutes_before' => 2,
            'minutes_after' => 2,
            'affected_instruments_only' => true,
            'block_open_new_trades' => true,
            'block_close_trades' => false,
            'block_pending_orders' => true,
            'rule_summary' => 'The5ers High Stakes allows holding positions over high-impact news but prohibits executing new market or pending-entry orders from 2 minutes before until 2 minutes after high-impact news in the related currency or index. The official FAQ classifies trades opened in this window as a soft breach.',
            'source_url' => $source,
            'priority' => 10,
        ]);

        foreach (['Instant Funding (Hyper-Growth)', 'Bootcamp'] as $program) {
            $this->rule($partner, 'The5ers '.$program.' news bracketing condition', [
                'program_name' => $program,
                'phase' => FundedNoTradeRule::PHASE_ALL,
                'policy' => FundedNoTradeRule::POLICY_CONDITIONAL,
                'enforcement_type' => FundedNoTradeRule::ENFORCEMENT_GUIDANCE,
                'impact_levels' => ['HIGH'],
                'minutes_before' => 0,
                'minutes_after' => 0,
                'affected_instruments_only' => true,
                'block_open_new_trades' => false,
                'block_close_trades' => false,
                'block_pending_orders' => false,
                'rule_summary' => 'The5ers states news trading is allowed for this program except bracket strategies around news. TradeX stores this as conditional compliance intelligence and does not create a mandatory no-trade window.',
                'source_url' => $source,
                'priority' => 100,
            ]);
        }
    }

    private function seedFundedNext(FundedAccountPartner $partner): void
    {
        $source = 'https://help.fundednext.com/en/articles/10701447-is-news-trading-allowed-at-fundednext';

        foreach (['Stellar 1-Step', 'Stellar 2-Step', 'Stellar Lite'] as $program) {
            $this->rule($partner, 'FundedNext '.$program.' funded news reward share', [
                'program_name' => $program,
                'phase' => FundedNoTradeRule::PHASE_FUNDED,
                'policy' => FundedNoTradeRule::POLICY_CONDITIONAL,
                'enforcement_type' => FundedNoTradeRule::ENFORCEMENT_REWARD_SHARE,
                'impact_levels' => ['HIGH'],
                'minutes_before' => 5,
                'minutes_after' => 5,
                'affected_instruments_only' => true,
                'block_open_new_trades' => false,
                'block_close_trades' => false,
                'block_pending_orders' => false,
                'rule_summary' => 'FundedNext allows news trading, but its FundedNext Account reward-share rule applies to profitable trades executed within 5 minutes before and 5 minutes after listed high-impact news for Stellar 1-Step, Stellar 2-Step and Stellar Lite. Because trading is allowed, TradeX does not label this as a mandatory No Trade Zone.',
                'source_url' => $source,
                'priority' => 100,
            ]);
        }
    }

    /** @param array<int, string> $challengeTypes */
    private function partner(
        string $name,
        string $websiteUrl,
        array $challengeTypes,
        int $priority
    ): FundedAccountPartner {
        $partner = FundedAccountPartner::query()->firstOrCreate(
            ['name' => $name],
            [
                'website_url' => $websiteUrl,
                'challenge_types' => $challengeTypes,
                'is_active' => true,
                'priority' => $priority,
            ]
        );

        $existingTypes = array_values((array) $partner->challenge_types);
        $partner->fill([
            'website_url' => $websiteUrl,
            'challenge_types' => array_values(array_unique(array_merge($existingTypes, $challengeTypes))),
            'is_active' => true,
            'priority' => $partner->priority ?: $priority,
        ])->save();

        return $partner;
    }

    /** @param array<string, mixed> $attributes */
    private function rule(
        FundedAccountPartner $partner,
        string $sourceReference,
        array $attributes
    ): void {
        $rule = FundedNoTradeRule::withTrashed()->firstOrNew([
            'funded_account_partner_id' => $partner->id,
            'program_name' => $attributes['program_name'] ?? null,
            'phase' => $attributes['phase'],
            'source_reference' => $sourceReference,
        ]);

        if ($rule->exists && $rule->trashed()) {
            $rule->restore();
        }

        $rule->fill(array_merge([
            'policy' => FundedNoTradeRule::POLICY_RESTRICTED,
            'enforcement_type' => FundedNoTradeRule::ENFORCEMENT_BLOCKED,
            'impact_levels' => ['HIGH'],
            'event_keywords' => null,
            'minutes_before' => 0,
            'minutes_after' => 0,
            'affected_instruments_only' => true,
            'restricted_currencies' => null,
            'restricted_instruments' => null,
            'block_open_new_trades' => false,
            'block_close_trades' => false,
            'block_pending_orders' => false,
            'rule_summary' => null,
            'source_url' => null,
            'verified_at' => self::VERIFIED_AT,
            'effective_from' => null,
            'expires_at' => null,
            'is_active' => true,
            'priority' => 100,
        ], $attributes, [
            'funded_account_partner_id' => $partner->id,
            'source_reference' => $sourceReference,
            'verified_at' => self::VERIFIED_AT,
            'is_active' => true,
        ]));

        $rule->save();
    }
}
