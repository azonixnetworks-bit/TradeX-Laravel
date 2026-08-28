<?php

namespace Database\Seeders;

use App\Models\AdminPermission;
use Illuminate\Database\Seeder;

class AdminPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [

            // DASHBOARD
            ['dashboard.view', 'View Dashboard', 'DASHBOARD'],

            // USERS / CUSTOMERS
            ['users.view', 'View Users', 'USERS'],
            ['users.view_details', 'View User Details', 'USERS'],
            ['users.invite', 'Invite Customers', 'USERS'],
            ['users.activate', 'Activate Users', 'USERS'],
            ['users.suspend', 'Suspend Users', 'USERS'],
            ['users.block', 'Block Users', 'USERS'],
            ['users.manage_entitlements', 'Manage User Entitlements', 'USERS'],
            ['users.manage_sessions', 'Manage User Sessions', 'USERS'],
            ['users.devices.view','View User Devices','USERS'],
            ['users.devices.revoke','Revoke User Devices','USERS'],
            ['users.devices.reset','Reset User Device Slots','USERS'],
            ['users.sessions.revoke','Revoke User Sessions','USERS'],

            // EDUCATION
            ['education.view', 'View Education', 'EDUCATION'],
            ['education.create', 'Create Education Content', 'EDUCATION'],
            ['education.update', 'Update Education Content', 'EDUCATION'],
            ['education.delete', 'Delete Education Content', 'EDUCATION'],
            ['education.publish', 'Publish Education Content', 'EDUCATION'],

            // OUR CHANNEL
            ['our_channel.view', 'View Our Channel', 'OUR_CHANNEL'],
            ['our_channel.manage', 'Manage Our Channel', 'OUR_CHANNEL'],

            // TRADE IDEAS
            ['trade_ideas.view', 'View Trade Ideas', 'TRADE_IDEAS'],
            ['trade_ideas.create', 'Create Trade Ideas', 'TRADE_IDEAS'],
            ['trade_ideas.update', 'Update Trade Ideas', 'TRADE_IDEAS'],
            ['trade_ideas.delete', 'Delete Trade Ideas', 'TRADE_IDEAS'],
            ['trade_ideas.publish', 'Publish Trade Ideas', 'TRADE_IDEAS'],
            ['trade_ideas.close', 'Close Trade Ideas', 'TRADE_IDEAS'],
            ['trade_ideas.distribute', 'Distribute Trade Ideas', 'TRADE_IDEAS'],

            // COPY TRADING
            ['copy_trading.view', 'View Copy Trading', 'COPY_TRADING'],
            ['copy_trading.brokers.manage', 'Manage Copy Trading Brokers', 'COPY_TRADING'],
            ['copy_trading.accounts.view', 'View Copy Trading Broker Accounts', 'COPY_TRADING'],
            ['copy_trading.accounts.verify', 'Verify Copy Trading Broker Accounts', 'COPY_TRADING'],
            ['copy_trading.accounts.reject', 'Reject Copy Trading Broker Accounts', 'COPY_TRADING'],
            ['copy_trading.accounts.request_resubmission', 'Request Broker Account Resubmission', 'COPY_TRADING'],
            ['copy_trading.accounts.escalate', 'Escalate Broker Account Verification', 'COPY_TRADING'],
            ['copy_trading.evidence.view', 'View Copy Trading Verification Evidence', 'COPY_TRADING'],

            // AFFILIATES
            ['affiliates.view', 'View Affiliates', 'AFFILIATES'],
            ['affiliates.view_tree', 'View Referral Tree', 'AFFILIATES'],
            ['affiliates.manage', 'Manage Affiliates', 'AFFILIATES'],
            ['affiliate_stages.manage', 'Manage Affiliate Stages', 'AFFILIATES'],
            ['commissions.view', 'View Commissions', 'AFFILIATES'],
            ['commissions.manage', 'Manage Commissions', 'AFFILIATES'],

            // PAYMENTS
            ['payments.view', 'View Payments', 'PAYMENTS'],
            ['payments.verify', 'Verify Payments', 'PAYMENTS'],
            ['payments.reject', 'Reject Payments', 'PAYMENTS'],
            ['payments.reconcile', 'Reconcile Payments', 'PAYMENTS'],
            ['payment_accounts.manage', 'Manage Company Payment Accounts', 'PAYMENTS'],

            // FINANCE
            ['finance.view', 'View Finance Dashboard', 'FINANCE'],
            ['finance.reports', 'View Financial Reports', 'FINANCE'],
            ['finance.reconciliation', 'Manage Reconciliation', 'FINANCE'],

            // PAYOUTS
            ['payouts.view', 'View Payout Requests', 'PAYOUTS'],
            ['payouts.approve', 'Approve Payout Requests', 'PAYOUTS'],
            ['payouts.reject', 'Reject Payout Requests', 'PAYOUTS'],
            ['payouts.mark_paid', 'Mark Payout as Paid', 'PAYOUTS'],

            // KYC
            ['kyc.view', 'View KYC Submissions', 'KYC'],
            ['kyc.view_documents', 'View KYC Documents', 'KYC'],
            ['kyc.approve', 'Approve KYC', 'KYC'],
            ['kyc.reject', 'Reject KYC', 'KYC'],
            ['kyc.request_resubmission', 'Request KYC Resubmission', 'KYC'],

            // NOTIFICATIONS
            ['notifications.view', 'View Notifications', 'NOTIFICATIONS'],
            ['notifications.create', 'Create Notifications', 'NOTIFICATIONS'],
            ['notifications.send', 'Send Notifications', 'NOTIFICATIONS'],

            // INTEGRATIONS
            ['integrations.view', 'View Integrations', 'INTEGRATIONS'],
            ['integrations.manage', 'Manage Integrations', 'INTEGRATIONS'],

            // LEGAL
            ['legal.view', 'View Legal Policies', 'LEGAL'],
            ['legal.manage', 'Manage Legal Policies', 'LEGAL'],
            ['legal.publish', 'Publish Legal Policies', 'LEGAL'],

            // LANGUAGES
            ['languages.view', 'View Languages', 'LANGUAGES'],
            ['languages.manage', 'Manage Languages', 'LANGUAGES'],

            // SECURITY
            ['security.view', 'View Security Settings', 'SECURITY'],
            ['security.manage', 'Manage Security Settings', 'SECURITY'],
            ['security.events', 'View Security Events', 'SECURITY'],

            // ADMINISTRATION
            ['admins.view', 'View Admin Users', 'ADMINISTRATION'],
            ['admins.create', 'Create Admin Users', 'ADMINISTRATION'],
            ['admins.update', 'Update Admin Users', 'ADMINISTRATION'],
            ['admins.disable', 'Disable Admin Users', 'ADMINISTRATION'],
            ['roles.view', 'View Roles and Permissions', 'ADMINISTRATION'],
            ['roles.manage', 'Manage Roles and Permissions', 'ADMINISTRATION'],

            // AUDIT
            ['audit.view', 'View Audit Logs', 'AUDIT'],

            // SETTINGS
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
