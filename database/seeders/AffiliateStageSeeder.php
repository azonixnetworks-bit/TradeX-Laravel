<?php

namespace Database\Seeders;

use App\Models\AffiliateStage;
use Illuminate\Database\Seeder;

class AffiliateStageSeeder extends Seeder
{
    public function run(): void
    {
        $stages = [
            [
                'code' => AffiliateStage::STARTER,
                'name' => 'Starter',
                'min_qualified_sales' => 1,
                'max_qualified_sales' => 30,
                'standard_commission_bps' => 1500,
                'milestone_sales' => 30,
                'milestone_gift_cents' => 7500,
                'cumulative_gift_cents' => 7500,
                'commercial_rule' => 'Entry stage. Gift released once 30 qualified paid sales are cleared.',
                'sort_order' => 10,
                'is_active' => true,
            ],
            [
                'code' => AffiliateStage::GROWTH,
                'name' => 'Growth',
                'min_qualified_sales' => 31,
                'max_qualified_sales' => 80,
                'standard_commission_bps' => 1750,
                'milestone_sales' => 80,
                'milestone_gift_cents' => 12500,
                'cumulative_gift_cents' => 20000,
                'commercial_rule' => 'Higher commission applies only to sales inside this band.',
                'sort_order' => 20,
                'is_active' => true,
            ],
            [
                'code' => AffiliateStage::PRO,
                'name' => 'Pro',
                'min_qualified_sales' => 81,
                'max_qualified_sales' => 150,
                'standard_commission_bps' => 2000,
                'milestone_sales' => 150,
                'milestone_gift_cents' => 20000,
                'cumulative_gift_cents' => 40000,
                'commercial_rule' => '20% standard maximum commission reached.',
                'sort_order' => 30,
                'is_active' => true,
            ],
            [
                'code' => AffiliateStage::ELITE,
                'name' => 'Elite',
                'min_qualified_sales' => 151,
                'max_qualified_sales' => 300,
                'standard_commission_bps' => 2000,
                'milestone_sales' => 300,
                'milestone_gift_cents' => 40000,
                'cumulative_gift_cents' => 80000,
                'commercial_rule' => 'One-time milestone gift; no retroactive rate increase.',
                'sort_order' => 40,
                'is_active' => true,
            ],
            [
                'code' => AffiliateStage::DIAMOND,
                'name' => 'Diamond',
                'min_qualified_sales' => 301,
                'max_qualified_sales' => 500,
                'standard_commission_bps' => 2000,
                'milestone_sales' => 500,
                'milestone_gift_cents' => 60000,
                'cumulative_gift_cents' => 140000,
                'commercial_rule' => 'Qualified paid sales only; externally reversed or invalid payments are excluded.',
                'sort_order' => 50,
                'is_active' => true,
            ],
            [
                'code' => AffiliateStage::AMBASSADOR,
                'name' => 'Ambassador',
                'min_qualified_sales' => 501,
                'max_qualified_sales' => 750,
                'standard_commission_bps' => 2000,
                'milestone_sales' => 750,
                'milestone_gift_cents' => 80000,
                'cumulative_gift_cents' => 220000,
                'commercial_rule' => '20% commission cap continues; milestone gift is one-time.',
                'sort_order' => 60,
                'is_active' => true,
            ],
            [
                'code' => AffiliateStage::BUSINESS_PARTNER,
                'name' => 'Business Partner',
                'min_qualified_sales' => 751,
                'max_qualified_sales' => null,
                'standard_commission_bps' => 2000,
                'milestone_sales' => 1500,
                'milestone_gift_cents' => 220000,
                'cumulative_gift_cents' => 440000,
                'commercial_rule' => 'Top standard stage. Any higher commercial rate requires management approval.',
                'sort_order' => 70,
                'is_active' => true,
            ],
        ];

        foreach ($stages as $stage) {
            AffiliateStage::query()->updateOrCreate(
                ['code' => $stage['code']],
                $stage
            );
        }
    }
}
