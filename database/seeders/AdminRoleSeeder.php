<?php

namespace Database\Seeders;

use App\Models\AdminRole;
use Illuminate\Database\Seeder;

class AdminRoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'code' => 'SUPER_ADMIN',
                'name' => 'Super Admin',
                'description' => 'Full TradeX administration access.',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'CONTENT_ADMIN',
                'name' => 'Content Admin',
                'description' => 'Courses, modules, lessons, videos, assignments and Our Channel.',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'TRADE_IDEA_ADMIN',
                'name' => 'Trade Idea Admin',
                'description' => 'Trade Ideas, updates, publishing and distribution.',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'COPY_TRADING_ADMIN',
                'name' => 'Copy Trading Admin',
                'description' => 'Broker configuration and normal broker-account verification for Copy Trading.',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'AFFILIATE_ADMIN',
                'name' => 'Affiliate Admin',
                'description' => 'Affiliate members, stages, referral information and commissions.',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'INVESTMENT_ADMIN',
                'name' => 'Investment Admin',
                'description' => 'Investment onboarding, agreements, deposit verification, profit preparation and investment operations. Final outgoing payout approval remains separated.',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'FINANCE_ADMIN',
                'name' => 'Finance Admin',
                'description' => 'Payments, deposits, commissions, payouts and financial reports.',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'KYC_ADMIN',
                'name' => 'KYC Admin',
                'description' => 'Identity verification review and approval.',
                'is_system' => true,
                'is_active' => true,
            ],
            [
                'code' => 'SUPPORT_ADMIN',
                'name' => 'Support Admin',
                'description' => 'User support and limited customer account visibility.',
                'is_system' => true,
                'is_active' => true,
            ],
        ];

        foreach ($roles as $role) {
            AdminRole::updateOrCreate(
                ['code' => $role['code']],
                $role
            );
        }
    }
}
