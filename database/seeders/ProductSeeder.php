<?php

namespace Database\Seeders;

use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        Product::query()->firstOrCreate(
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
                    7900,
                'is_active' =>
                    true,
            ]
        );
    }
}
