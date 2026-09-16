<?php

namespace Tests\Feature\Trading;

use Tests\TestCase;

class FundedNoTradeZoneAdminIndexContractTest extends TestCase
{
    public function test_admin_index_orders_rules_by_actual_partner_foreign_key(): void
    {
        $source = file_get_contents(
            app_path('Http/Controllers/Admin/TradingTools/FundedNoTradeZoneController.php')
        );

        $this->assertIsString($source);
        $this->assertStringContainsString(
            "->orderBy('funded_account_partner_id')",
            $source
        );
        $this->assertStringNotContainsString(
            "->orderBy('partner_id')",
            $source
        );
    }
}
