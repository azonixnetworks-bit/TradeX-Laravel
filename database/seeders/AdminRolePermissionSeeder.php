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
                'copy_trading.accounts.reject',
                'copy_trading.accounts.request_resubmission',
                'copy_trading.accounts.escalate',
                'copy_trading.evidence.view',
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

            'FINANCE_ADMIN' => [
                'dashboard.view',

                'users.view',
                'users.view_details',

                'payments.view',
                'payments.verify',
                'payments.reject',
                'payments.reconcile',
                'payment_accounts.manage',

                'finance.view',
                'finance.reports',
                'finance.reconciliation',

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
                'our_channel.view',

                'trade_ideas.view',

                'affiliates.view',

                'payments.view',

                'notifications.view',
            ],
        ];

        /*
         * SUPER_ADMIN:
         * Keep every permission attached as well,
         * even though AdminUser::isSuperAdmin() already bypasses checks.
         */
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
