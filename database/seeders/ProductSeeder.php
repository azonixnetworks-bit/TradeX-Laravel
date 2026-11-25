<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::query()->updateOrCreate(
            [
                'code' =>
                    Product::CODE_EDUCATION_PREMIUM,
            ],
            [
                'name' =>
                    'TradeX Education Premium',
                'description' =>
                    'One-time lifetime premium education access.',
                'billing_type' =>
                    Product::BILLING_ONE_TIME_LIFETIME,
                'currency' =>
                    'USD',
                'amount_minor' =>
                    15000,
                'lkr_amount_minor' =>
                    5000000,
                'affiliate_discount_percent' =>
                    15,
                'is_active' =>
                    true,
            ]
        );
    }
}
