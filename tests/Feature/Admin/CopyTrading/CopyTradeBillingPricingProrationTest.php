<?php

namespace Tests\Feature\Admin\CopyTrading;

use App\Services\CopyTrading\Billing\CopyTradeBillingPricingService;
use App\Services\CopyTrading\Billing\CopyTradeBillingProrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CopyTradeBillingPricingProrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_frozen_pricing_boundaries_are_exact(): void
    {
        $pricing = app(
            CopyTradeBillingPricingService::class
        );

        $cases = [
            [24999, null],
            [25000, 500],
            [99999, 500],
            [100000, 1000],
            [299999, 1000],
            [300000, 2000],
            [999999, 2000],
            [1000000, 5000],
            [4999999, 5000],
            [5000000, 10000],
            [9999999, 10000],
            [10000000, 19900],
            [19999999, 19900],
            [20000000, 29900],
            [29999999, 29900],
            [30000000, 39900],
            [49999999, 39900],
            [50000000, 59900],
            [99999999, 59900],
            [100000000, 99900],
        ];

        foreach ($cases as [$balance, $fee]) {
            $tier = $pricing->tierForBalance(
                $balance
            );

            if ($fee === null) {
                $this->assertNull($tier);
                continue;
            }

            $this->assertSame(
                $fee,
                $tier[
                    'full_monthly_fee_minor'
                ]
            );
        }
    }

    public function test_proration_uses_final_commercial_half_up_rounding(): void
    {
        $service = app(
            CopyTradeBillingProrationService::class
        );

        $this->assertSame(
            250,
            $service->prorate(
                500,
                15,
                30
            )
        );

        $this->assertSame(
            167,
            $service->prorate(
                500,
                10,
                30
            )
        );

        $this->assertSame(
            0,
            $service->upgradeCharge(
                1000,
                1000,
                15,
                30
            )
        );

        $this->assertSame(
            250,
            $service->upgradeCharge(
                1000,
                500,
                15,
                30
            )
        );
    }
}
