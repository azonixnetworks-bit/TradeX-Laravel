<?php

namespace App\Support\Admin;

use App\Models\AdminUser;

final class AdminAccessMap
{
    /**
     * Ordered list of safe landing destinations for authenticated admins.
     * The first permission the admin actually owns determines the landing page.
     * Dashboard remains the compatibility fallback for legacy/no-role test
     * accounts, while valid custom roles should always resolve earlier.
     *
     * @var array<int, array{permission: string, route: string}>
     */
    private const LANDING_ROUTES = [
        ['permission' => 'dashboard.view', 'route' => 'admin.dashboard'],
        ['permission' => 'users.view', 'route' => 'admin.customers.dashboard'],
        ['permission' => 'users.invite', 'route' => 'admin.customers.create'],
        ['permission' => 'education.view', 'route' => 'admin.education.courses.index'],
        ['permission' => 'our_channel.view', 'route' => 'admin.our_channel.index'],
        ['permission' => 'notifications.view', 'route' => 'admin.notifications.index'],
        ['permission' => 'trade_ideas.view', 'route' => 'admin.trade_ideas.index'],
        ['permission' => 'copy_trading.view', 'route' => 'admin.copy_trading.brokers.index'],
        ['permission' => 'copy_trading.accounts.view', 'route' => 'admin.copy_trading.verifications.index'],
        ['permission' => 'copy_trading.billing.view', 'route' => 'admin.copy_trading.billing.index'],
        ['permission' => 'investments.view', 'route' => 'admin.investments.dashboard'],
        ['permission' => 'investments.create', 'route' => 'admin.investments.create'],
        ['permission' => 'investments.deposits.view', 'route' => 'admin.investments.deposits.index'],
        ['permission' => 'investments.agreements.view', 'route' => 'admin.investments.agreements.index'],
        ['permission' => 'investments.activate', 'route' => 'admin.investments.activation.index'],
        ['permission' => 'investments.profit_periods.view', 'route' => 'admin.investments.profit_periods.index'],
        ['permission' => 'investments.settlements.view', 'route' => 'admin.investments.settlements.index'],
        ['permission' => 'affiliates.view', 'route' => 'admin.affiliates.index'],
        ['permission' => 'commissions.view', 'route' => 'admin.affiliates.commissions.index'],
        ['permission' => 'products.view', 'route' => 'admin.products.index'],
        ['permission' => 'payments.view', 'route' => 'admin.payments.index'],
        ['permission' => 'payment_accounts.manage', 'route' => 'admin.payments.accounts.index'],
        ['permission' => 'payouts.view', 'route' => 'admin.payouts.index'],
        ['permission' => 'finance.view', 'route' => 'admin.finance.dashboard'],
        ['permission' => 'kyc.view', 'route' => 'admin.kyc.index'],
        ['permission' => 'security.view', 'route' => 'admin.security.home'],
        ['permission' => 'security.events', 'route' => 'admin.security.home'],
        ['permission' => 'audit.view', 'route' => 'admin.audit.index'],
        ['permission' => 'admins.view', 'route' => 'admin.administration.admins.index'],
        ['permission' => 'roles.view', 'route' => 'admin.administration.roles.index'],
        ['permission' => 'settings.view', 'route' => 'admin.settings.general'],
        ['permission' => 'integrations.view', 'route' => 'admin.integrations.index'],
        ['permission' => 'legal.view', 'route' => 'admin.legal.index'],
        ['permission' => 'languages.view', 'route' => 'admin.languages.index'],
        ['permission' => 'market_clock.view', 'route' => 'admin.market_clock.index'],
        ['permission' => 'screener.monitor.view', 'route' => 'admin.screener.index'],
    ];

    /**
     * Action permissions that require a parent visibility permission. This
     * prevents custom roles from receiving a mutation capability while having
     * no supported UI path to the protected resource.
     *
     * @var array<string, array<int, string>>
     */
    private const PERMISSION_DEPENDENCIES = [
        'users.view_details' => ['users.view'],
        'users.activate' => ['users.view'],
        'users.suspend' => ['users.view'],
        'users.block' => ['users.view'],
        'users.manage_entitlements' => ['users.view'],
        'users.manage_sessions' => ['users.view'],
        'users.devices.view' => ['users.view'],
        'users.devices.revoke' => ['users.view', 'users.devices.view'],
        'users.devices.reset' => ['users.view', 'users.devices.view'],
        'users.sessions.revoke' => ['users.view'],

        'education.create' => ['education.view'],
        'education.update' => ['education.view'],
        'education.delete' => ['education.view'],
        'education.publish' => ['education.view'],
        'products.manage' => ['products.view'],
        'our_channel.manage' => ['our_channel.view'],
        'trade_ideas.create' => ['trade_ideas.view'],
        'trade_ideas.update' => ['trade_ideas.view'],
        'trade_ideas.delete' => ['trade_ideas.view'],
        'trade_ideas.publish' => ['trade_ideas.view'],
        'trade_ideas.close' => ['trade_ideas.view'],
        'trade_ideas.distribute' => ['trade_ideas.view'],

        'copy_trading.brokers.manage' => ['copy_trading.view'],
        'copy_trading.accounts.verify' => ['copy_trading.accounts.view'],
        'copy_trading.accounts.activate' => ['copy_trading.accounts.view'],
        'copy_trading.accounts.reject' => ['copy_trading.accounts.view'],
        'copy_trading.accounts.request_resubmission' => ['copy_trading.accounts.view'],
        'copy_trading.accounts.escalate' => ['copy_trading.accounts.view'],
        'copy_trading.evidence.view' => ['copy_trading.accounts.view'],
        'copy_trading.billing.manage' => ['copy_trading.billing.view'],
        'copy_trading.billing.calendar.manage' => ['copy_trading.billing.view'],
        'copy_trading.billing.adjust' => ['copy_trading.billing.view'],

        'affiliates.view_tree' => ['affiliates.view'],
        'affiliates.manage' => ['affiliates.view'],
        'affiliate_stages.manage' => ['affiliates.view'],
        'commissions.manage' => ['commissions.view'],
        'payments.verify' => ['payments.view'],
        'payments.reject' => ['payments.view'],
        'payments.reconcile' => ['payments.view'],

        'investments.update' => ['investments.view'],
        'investments.customers.view' => ['investments.view'],
        'investments.agreements.manage' => ['investments.agreements.view'],
        'investments.agreements.verify' => ['investments.agreements.view'],
        'investments.deposits.record' => ['investments.deposits.view'],
        'investments.deposits.verify' => ['investments.deposits.view'],
        'investments.risk_acceptance.record' => ['investments.view'],
        'investments.wallets.verify' => ['investments.view'],
        'investments.profit_periods.prepare' => ['investments.profit_periods.view'],
        'investments.profit_periods.approve' => ['investments.profit_periods.view'],
        'investments.trades.record' => ['investments.view'],
        'investments.trades.verify' => ['investments.view'],
        'investments.evidence.view' => ['investments.view'],
        'investments.evidence.manage' => ['investments.view', 'investments.evidence.view'],
        'investments.evidence.verify' => ['investments.view', 'investments.evidence.view'],
        'investments.costs.record' => ['investments.view'],
        'investments.costs.verify' => ['investments.view'],
        'investments.adjustments.record' => ['investments.view'],
        'investments.adjustments.verify' => ['investments.view'],
        'investments.topups.record' => ['investments.view'],
        'investments.topups.verify' => ['investments.view'],
        'investments.payouts.prepare' => ['investments.payouts.view'],
        'investments.payouts.approve' => ['investments.payouts.view'],
        'investments.transactions.record' => ['investments.transactions.view'],
        'investments.transactions.verify' => ['investments.transactions.view'],
        'investments.principal_settlement.approve' => ['investments.settlements.view'],
        'investments.early_termination.review' => ['investments.view'],
        'investments.settlements.prepare' => ['investments.settlements.view'],
        'investments.settlements.wallets.record' => ['investments.settlements.view'],
        'investments.settlements.wallets.verify' => ['investments.settlements.view'],
        'investments.settlements.payments.record' => ['investments.settlements.view'],
        'investments.settlements.payments.verify' => ['investments.settlements.view'],
        'investments.settlements.close' => ['investments.settlements.view'],
        'investments.reports.view' => ['investments.view'],

        'finance.reports' => ['finance.view'],
        'finance.reconciliation' => ['finance.view'],
        'payouts.approve' => ['payouts.view'],
        'payouts.reject' => ['payouts.view'],
        'payouts.mark_paid' => ['payouts.view'],
        'kyc.view_documents' => ['kyc.view'],
        'kyc.approve' => ['kyc.view'],
        'kyc.reject' => ['kyc.view'],
        'kyc.request_resubmission' => ['kyc.view'],
        'notifications.create' => ['notifications.view'],
        'notifications.send' => ['notifications.view'],
        'integrations.manage' => ['integrations.view'],
        'legal.manage' => ['legal.view'],
        'legal.publish' => ['legal.view'],
        'languages.manage' => ['languages.view'],
        'security.manage' => ['security.view'],
        'admins.create' => ['admins.view'],
        'admins.update' => ['admins.view'],
        'admins.disable' => ['admins.view'],
        'roles.manage' => ['roles.view'],
        'settings.manage' => ['settings.view'],
    ];

    public static function landingRouteName(AdminUser $admin): string
    {
        foreach (self::LANDING_ROUTES as $entry) {
            if ($admin->hasPermission($entry['permission'])) {
                return $entry['route'];
            }
        }

        return 'admin.dashboard';
    }

    /**
     * @param iterable<int, string> $permissionCodes
     * @return array<string, array<int, string>>
     */
    public static function missingDependencies(iterable $permissionCodes): array
    {
        $selected = [];

        foreach ($permissionCodes as $code) {
            $selected[(string) $code] = true;
        }

        $missing = [];

        foreach (self::PERMISSION_DEPENDENCIES as $permission => $requirements) {
            if (! isset($selected[$permission])) {
                continue;
            }

            foreach ($requirements as $requirement) {
                if (! isset($selected[$requirement])) {
                    $missing[$permission][] = $requirement;
                }
            }
        }

        return $missing;
    }
}
