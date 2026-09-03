<?php

namespace Database\Seeders;

use App\Models\AdminPermission;
use Illuminate\Database\Seeder;

class AdminPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['dashboard.view', 'View Dashboard', 'DASHBOARD'],

            ['users.view', 'View Users', 'USERS'],
            ['users.view_details', 'View User Details', 'USERS'],
            ['users.invite', 'Invite Customers', 'USERS'],
            ['users.activate', 'Activate Users', 'USERS'],
            ['users.suspend', 'Suspend Users', 'USERS'],
            ['users.block', 'Block Users', 'USERS'],
            ['users.manage_entitlements', 'Manage User Entitlements', 'USERS'],
            ['users.manage_sessions', 'Manage User Sessions', 'USERS'],
            ['users.devices.view', 'View User Devices', 'USERS'],
            ['users.devices.revoke', 'Revoke User Devices', 'USERS'],
            ['users.devices.reset', 'Reset User Device Slots', 'USERS'],
            ['users.sessions.revoke', 'Revoke User Sessions', 'USERS'],

            ['education.view', 'View Education', 'EDUCATION'],
            ['education.create', 'Create Education Content', 'EDUCATION'],
            ['education.update', 'Update Education Content', 'EDUCATION'],
            ['education.delete', 'Delete Education Content', 'EDUCATION'],
            ['education.publish', 'Publish Education Content', 'EDUCATION'],

            ['products.view', 'View Product Catalog', 'PRODUCTS'],
            ['products.manage', 'Manage Product Catalog', 'PRODUCTS'],

            ['our_channel.view', 'View Our Channel', 'OUR_CHANNEL'],
            ['our_channel.manage', 'Manage Our Channel', 'OUR_CHANNEL'],

            ['trade_ideas.view', 'View Trade Ideas', 'TRADE_IDEAS'],
            ['trade_ideas.create', 'Create Trade Ideas', 'TRADE_IDEAS'],
            ['trade_ideas.update', 'Update Trade Ideas', 'TRADE_IDEAS'],
            ['trade_ideas.delete', 'Delete Trade Ideas', 'TRADE_IDEAS'],
            ['trade_ideas.publish', 'Publish Trade Ideas', 'TRADE_IDEAS'],
            ['trade_ideas.close', 'Close Trade Ideas', 'TRADE_IDEAS'],
            ['trade_ideas.distribute', 'Distribute Trade Ideas', 'TRADE_IDEAS'],

            ['copy_trading.view', 'View Copy Trading', 'COPY_TRADING'],
            ['copy_trading.brokers.manage', 'Manage Copy Trading Brokers', 'COPY_TRADING'],
            ['copy_trading.accounts.view', 'View Copy Trading Broker Accounts', 'COPY_TRADING'],
            ['copy_trading.accounts.verify', 'Verify Copy Trading Broker Accounts', 'COPY_TRADING'],
            ['copy_trading.accounts.activate', 'Activate Eligible Copy Trading Broker Accounts', 'COPY_TRADING'],
            ['copy_trading.accounts.reject', 'Reject Copy Trading Broker Accounts', 'COPY_TRADING'],
            ['copy_trading.accounts.request_resubmission', 'Request Broker Account Resubmission', 'COPY_TRADING'],
            ['copy_trading.accounts.escalate', 'Escalate Broker Account Verification', 'COPY_TRADING'],
            ['copy_trading.evidence.view', 'View Copy Trading Verification Evidence', 'COPY_TRADING'],
            ['copy_trading.billing.view', 'View Copy Trading Billing', 'COPY_TRADING'],
            ['copy_trading.billing.manage', 'Manage Copy Trading Billing', 'COPY_TRADING'],
            ['copy_trading.billing.calendar.manage', 'Manage Copy Trading Official Billing Calendar', 'COPY_TRADING'],
            ['copy_trading.billing.adjust', 'Create Copy Trading Billing Adjustment Evidence', 'COPY_TRADING'],

            ['affiliates.view', 'View Affiliates', 'AFFILIATES'],
            ['affiliates.view_tree', 'View Referral Tree', 'AFFILIATES'],
            ['affiliates.manage', 'Manage Affiliates', 'AFFILIATES'],
            ['affiliate_stages.manage', 'Manage Affiliate Stages', 'AFFILIATES'],
            ['commissions.view', 'View Commissions', 'AFFILIATES'],
            ['commissions.manage', 'Manage Commissions', 'AFFILIATES'],

            ['payments.view', 'View Payments', 'PAYMENTS'],
            ['payments.verify', 'Verify Payments', 'PAYMENTS'],
            ['payments.reject', 'Reject Payments', 'PAYMENTS'],
            ['payments.reconcile', 'Reconcile Payments', 'PAYMENTS'],
            ['payment_accounts.manage', 'Manage Company Payment Accounts', 'PAYMENTS'],

            ['investments.view', 'View Investments', 'INVESTMENTS'],
            ['investments.create', 'Create Investments', 'INVESTMENTS'],
            ['investments.update', 'Update Investment Records', 'INVESTMENTS'],
            ['investments.activate', 'Activate Eligible Investments', 'INVESTMENTS'],
            ['investments.customers.view', 'View Investment Customer Details', 'INVESTMENTS'],
            ['investments.agreements.view', 'View Investment Agreements', 'INVESTMENTS'],
            ['investments.agreements.manage', 'Manage Investment Agreements', 'INVESTMENTS'],
            ['investments.agreements.verify', 'Verify Signed Investment Agreements', 'INVESTMENTS'],
            ['investments.deposits.view', 'View Investment Deposits', 'INVESTMENTS'],
            ['investments.deposits.record', 'Record Investment Deposits', 'INVESTMENTS'],
            ['investments.deposits.verify', 'Verify Investment Deposits', 'INVESTMENTS'],
            ['investments.risk_acceptance.record', 'Record Customer Investment Risk Acceptance Evidence', 'INVESTMENTS'],
            ['investments.wallets.verify', 'Verify Investment Customer Payout Wallet', 'INVESTMENTS'],
            ['investments.profit_periods.view', 'View Investment Profit Periods', 'INVESTMENTS'],
            ['investments.profit_periods.prepare', 'Prepare Investment Profit Periods', 'INVESTMENTS'],
            ['investments.profit_periods.approve', 'Approve Investment Profit Periods', 'INVESTMENTS'], // ADMIN-24-PHASE3-V3
            ['investments.trades.record', 'Record Investment Spot Trades', 'INVESTMENTS'],
            ['investments.trades.verify', 'Verify Investment Spot Trades', 'INVESTMENTS'],
            ['investments.evidence.view', 'View Investment P&L Evidence', 'INVESTMENTS'],
            ['investments.evidence.manage', 'Upload Investment P&L Evidence', 'INVESTMENTS'],
            ['investments.evidence.verify', 'Verify Investment P&L Evidence', 'INVESTMENTS'],
            ['investments.costs.record', 'Record Investment Direct Trading Costs', 'INVESTMENTS'],
            ['investments.costs.verify', 'Verify Investment Direct Trading Costs', 'INVESTMENTS'],
            ['investments.adjustments.record', 'Record Investment Financial Reversals and Adjustments', 'INVESTMENTS'],
            ['investments.adjustments.verify', 'Verify Investment Financial Reversals and Adjustments', 'INVESTMENTS'],
            ['investments.topups.record', 'Record Active Investment Top-ups', 'INVESTMENTS'],
            ['investments.topups.verify', 'Verify Active Investment Top-ups', 'INVESTMENTS'],
            ['investments.payouts.view', 'View Investment Payouts', 'INVESTMENTS'],
            ['investments.payouts.prepare', 'Prepare Investment Payouts', 'INVESTMENTS'],
            ['investments.payouts.approve', 'Approve Investment Payouts', 'INVESTMENTS'],
            ['investments.transactions.view', 'View Investment Transactions', 'INVESTMENTS'],
            ['investments.transactions.record', 'Record Investment Transactions', 'INVESTMENTS'],
            ['investments.transactions.verify', 'Verify Investment Transactions', 'INVESTMENTS'],
            ['investments.principal_settlement.approve', 'Approve Principal Settlement', 'INVESTMENTS'],
            ['investments.early_termination.review', 'Review Early Investment Termination', 'INVESTMENTS'],
            ['investments.reports.view', 'View Investment Reports', 'INVESTMENTS'],

            ['finance.view', 'View Finance Dashboard', 'FINANCE'],
            ['finance.reports', 'View Financial Reports', 'FINANCE'],
            ['finance.reconciliation', 'Manage Reconciliation', 'FINANCE'],

            ['payouts.view', 'View Payout Requests', 'PAYOUTS'],
            ['payouts.approve', 'Approve Payout Requests', 'PAYOUTS'],
            ['payouts.reject', 'Reject Payout Requests', 'PAYOUTS'],
            ['payouts.mark_paid', 'Mark Payout as Paid', 'PAYOUTS'],

            ['kyc.view', 'View KYC Submissions', 'KYC'],
            ['kyc.view_documents', 'View KYC Documents', 'KYC'],
            ['kyc.approve', 'Approve KYC', 'KYC'],
            ['kyc.reject', 'Reject KYC', 'KYC'],
            ['kyc.request_resubmission', 'Request KYC Resubmission', 'KYC'],

            ['notifications.view', 'View Notifications', 'NOTIFICATIONS'],
            ['notifications.create', 'Create Notifications', 'NOTIFICATIONS'],
            ['notifications.send', 'Send Notifications', 'NOTIFICATIONS'],

            ['integrations.view', 'View Integrations', 'INTEGRATIONS'],
            ['integrations.manage', 'Manage Integrations', 'INTEGRATIONS'],

            ['legal.view', 'View Legal Policies', 'LEGAL'],
            ['legal.manage', 'Manage Legal Policies', 'LEGAL'],
            ['legal.publish', 'Publish Legal Policies', 'LEGAL'],

            ['languages.view', 'View Languages', 'LANGUAGES'],
            ['languages.manage', 'Manage Languages', 'LANGUAGES'],

            ['security.view', 'View Security Settings', 'SECURITY'],
            ['security.manage', 'Manage Security Settings', 'SECURITY'],
            ['security.events', 'View Security Events', 'SECURITY'],

            ['admins.view', 'View Admin Users', 'ADMINISTRATION'],
            ['admins.create', 'Create Admin Users', 'ADMINISTRATION'],
            ['admins.update', 'Update Admin Users', 'ADMINISTRATION'],
            ['admins.disable', 'Disable Admin Users', 'ADMINISTRATION'],
            ['roles.view', 'View Roles and Permissions', 'ADMINISTRATION'],
            ['roles.manage', 'Manage Roles and Permissions', 'ADMINISTRATION'],

            ['audit.view', 'View Audit Logs', 'AUDIT'],

            ['settings.view', 'View Settings', 'SETTINGS'],
            ['settings.manage', 'Manage Settings', 'SETTINGS'],
        ];

        foreach ($permissions as [$code, $name, $module]) {
            AdminPermission::updateOrCreate(
                ['code' => $code],
                [
                    'name' => $name,
                    'module' => $module,
                    'description' => null,
                ]
            );
        }
    }
}
