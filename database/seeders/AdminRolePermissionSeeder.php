<?php

namespace Database\Seeders;

use App\Models\AdminPermission;
use App\Models\AdminRole;
use Illuminate\Database\Seeder;

class AdminRolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $rolePermissions = [
            'CONTENT_ADMIN' => [
                'dashboard.view',
                'education.view',
                'education.create',
                'education.update',
                'education.delete',
                'education.publish',
                'our_channel.view',
                'our_channel.manage',
                'notifications.view',
            ],

            'TRADE_IDEA_ADMIN' => [
                'dashboard.view',
                'trade_ideas.view',
                'trade_ideas.create',
                'trade_ideas.update',
                'trade_ideas.delete',
                'trade_ideas.publish',
                'trade_ideas.close',
                'trade_ideas.distribute',
                'notifications.view',
            ],

            'COPY_TRADING_ADMIN' => [
                'dashboard.view',
                'users.view',
                'users.view_details',
                'copy_trading.view',
                'copy_trading.brokers.manage',
                'copy_trading.accounts.view',
                'copy_trading.accounts.verify',
                'copy_trading.accounts.activate',
                'copy_trading.accounts.reject',
                'copy_trading.accounts.request_resubmission',
                'copy_trading.accounts.escalate',
                'copy_trading.evidence.view',
                'copy_trading.billing.view',
                'copy_trading.billing.manage',
                'copy_trading.billing.calendar.manage',
            ],

            'AFFILIATE_ADMIN' => [
                'dashboard.view',
                'users.view',
                'users.view_details',
                'affiliates.view',
                'affiliates.view_tree',
                'affiliates.manage',
                'affiliate_stages.manage',
                'commissions.view',
                'commissions.manage',
            ],

            'INVESTMENT_ADMIN' => [
                'dashboard.view',
                'users.view',
                'users.view_details',
                'investments.view',
                'investments.create',
                'investments.update',
                'investments.activate',
                'investments.customers.view',
                'investments.agreements.view',
                'investments.agreements.manage',
                'investments.agreements.verify',
                'investments.deposits.view',
                'investments.deposits.record',
                'investments.deposits.verify',
                'investments.risk_acceptance.record',
                'investments.wallets.verify',
                'investments.profit_periods.view',
                'investments.profit_periods.prepare',
                'investments.trades.record', // ADMIN-24-PHASE3-V3-INVESTMENT-ADMIN
                'investments.evidence.view',
                'investments.evidence.manage',
                'investments.costs.record',
                'investments.adjustments.record',
                'investments.topups.record',
                'investments.payouts.view',
                'investments.payouts.prepare',
                'investments.transactions.view',
                'investments.early_termination.review',
                'investments.settlements.view',
                'investments.settlements.prepare',
                'investments.settlements.wallets.record',
                'investments.reports.view',
            ],

            'FINANCE_ADMIN' => [
                'dashboard.view',
                'products.view',
                'products.manage',
                'payments.view',
                'payments.verify',
                'payments.reject',
                'payments.reconcile',
                'payment_accounts.manage',
                'finance.view',
                'finance.reports',
                'finance.reconciliation',
                'investments.view',
                'investments.customers.view',
                'investments.profit_periods.view', // ADMIN-24-PHASE3-V3-FINANCE
                'investments.profit_periods.approve',
                'investments.trades.verify',
                'investments.evidence.view',
                'investments.evidence.verify',
                'investments.costs.verify',
                'investments.adjustments.verify',
                'investments.topups.verify',
                'investments.payouts.view',
                'investments.payouts.approve',
                'investments.transactions.view',
                'investments.transactions.record',
                'investments.transactions.verify',
                'investments.principal_settlement.approve',
                'investments.settlements.view',
                'investments.settlements.wallets.verify',
                'investments.settlements.payments.record',
                'investments.settlements.payments.verify',
                'investments.settlements.close',
                'investments.reports.view',
                'copy_trading.billing.view',
                'copy_trading.billing.manage',
                'copy_trading.billing.adjust',
                'commissions.view',
                'payouts.view',
                'payouts.approve',
                'payouts.reject',
                'payouts.mark_paid',
            ],

            'KYC_ADMIN' => [
                'dashboard.view',
                'users.view',
                'users.view_details',
                'kyc.view',
                'kyc.view_documents',
                'kyc.approve',
                'kyc.reject',
                'kyc.request_resubmission',
            ],

            'SUPPORT_ADMIN' => [
                'dashboard.view',
                'users.view',
                'users.view_details',
                'education.view',
                'products.view',
                'our_channel.view',
                'trade_ideas.view',
                'affiliates.view',
                'payments.view',
                'notifications.view',
            ],
        ];

        $superAdmin = AdminRole::where(
            'code',
            'SUPER_ADMIN'
        )->firstOrFail();

        $superAdmin->permissions()->sync(
            AdminPermission::pluck('id')->all()
        );

        foreach ($rolePermissions as $roleCode => $permissionCodes) {
            $role = AdminRole::where(
                'code',
                $roleCode
            )->firstOrFail();

            $permissionIds = AdminPermission::whereIn(
                'code',
                $permissionCodes
            )->pluck('id')->all();

            $role->permissions()->sync($permissionIds);
        }
    }
}
