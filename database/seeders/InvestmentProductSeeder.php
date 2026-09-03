<?php

namespace Database\Seeders;

use App\Models\InvestmentProduct;
use Illuminate\Database\Seeder;

class InvestmentProductSeeder extends Seeder
{
    public function run(): void
    {
        InvestmentProduct::updateOrCreate(
            ['code' => InvestmentProduct::CODE_CRYPTO],
            [
                'name' => 'Crypto Investment',
                'status' => InvestmentProduct::STATUS_ACTIVE,
                'deposit_currency' => 'USDT',
                'deposit_network' => 'TRC20',
                'customer_profit_share_bps' => 8000,
                'company_profit_share_bps' => 2000,
                'early_exit_return_bps' => 8000,
                'risk_disclosure_required' => true,
                'sort_order' => 10,
            ]
        );

        InvestmentProduct::updateOrCreate(
            ['code' => InvestmentProduct::CODE_INDIAN_STOCKS],
            [
                'name' => 'Indian Stocks Investment',
                'status' => InvestmentProduct::STATUS_COMING_SOON,
                'deposit_currency' => null,
                'deposit_network' => null,
                'customer_profit_share_bps' => 8000,
                'company_profit_share_bps' => 2000,
                'early_exit_return_bps' => 8000,
                'risk_disclosure_required' => true,
                'sort_order' => 20,
            ]
        );

        InvestmentProduct::updateOrCreate(
            ['code' => InvestmentProduct::CODE_US_STOCKS],
            [
                'name' => 'US Stocks Investment',
                'status' => InvestmentProduct::STATUS_COMING_SOON,
                'deposit_currency' => null,
                'deposit_network' => null,
                'customer_profit_share_bps' => 8000,
                'company_profit_share_bps' => 2000,
                'early_exit_return_bps' => 8000,
                'risk_disclosure_required' => true,
                'sort_order' => 30,
            ]
        );
    }
}
