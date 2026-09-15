<?php

namespace Tests\Unit\Trading;

use Tests\TestCase;

class RiskRewardCalculatorUiContractTest extends TestCase
{
    public function test_calculator_view_exposes_extended_result_fields_and_copy_action(): void
    {
        $view = file_get_contents(base_path(
            'resources/views/admin/trading_tools/risk_reward/index.blade.php'
        ));

        $this->assertStringContainsString('riskRewardPositionUnits', $view);
        $this->assertStringContainsString('riskRewardRiskUtilization', $view);
        $this->assertStringContainsString('riskRewardCopyButton', $view);
        $this->assertStringContainsString('risk-reward-copy.js', $view);
        $this->assertStringContainsString('risk-reward-enhancements.css', $view);
    }

    public function test_calculator_script_renders_backend_units_and_utilization_fields(): void
    {
        $script = file_get_contents(base_path(
            'public/admin/js/risk-reward-calculator.js'
        ));

        $this->assertStringContainsString('data.position_units', $script);
        $this->assertStringContainsString('data.risk_utilization_percent', $script);
        $this->assertStringContainsString('riskRewardPositionUnits', $script);
        $this->assertStringContainsString('riskRewardRiskUtilization', $script);
    }

    public function test_copy_script_builds_plain_text_summary_without_network_requests(): void
    {
        $script = file_get_contents(base_path(
            'public/admin/js/risk-reward-copy.js'
        ));

        $this->assertStringContainsString('TradeX Risk Reward Calculator', $script);
        $this->assertStringContainsString('navigator.clipboard', $script);
        $this->assertStringContainsString("document.execCommand('copy')", $script);
        $this->assertStringNotContainsString('fetch(', $script);
        $this->assertStringNotContainsString('XMLHttpRequest', $script);
    }
}
