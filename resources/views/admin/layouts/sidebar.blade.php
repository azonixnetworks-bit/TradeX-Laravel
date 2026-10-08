@php
    $admin = auth('admin')->user();
    $customerSegment = strtoupper((string) request()->query('segment', ''));

    $hasCustomerNavigation =
        $admin->hasPermission('users.view')
        || $admin->hasPermission('users.invite');

    $hasTradingFloorNavigation =
        $admin->isSuperAdmin()
        || $admin->hasPermission('trading_floor.view');

    $hasContentNavigation =
        $admin->hasPermission('education.view')
        || $admin->hasPermission('our_channel.view')
        || $admin->hasPermission('notifications.view');

    $hasSecondBrainNavigation =
        $admin->hasPermission('trade_ideas.view')
        || $admin->isSuperAdmin();

    $hasCopyTradingCoreNavigation =
        $admin->hasPermission('copy_trading.view')
        || $admin->hasPermission('copy_trading.accounts.view');

    $hasCopyTradingNavigation =
        $hasCopyTradingCoreNavigation
        || $admin->hasPermission('copy_trading.billing.view');

    $hasInvestmentNavigation =
        $admin->hasPermission('investments.view')
        || $admin->hasPermission('investments.create')
        || $admin->hasPermission('investments.deposits.view')
        || $admin->hasPermission('investments.agreements.view')
        || $admin->hasPermission('investments.activate')
        || $admin->hasPermission('investments.profit_periods.view')
        || $admin->hasPermission('investments.settlements.view');

    $hasAffiliateNavigation =
        $admin->hasPermission('affiliates.view')
        || $admin->hasPermission('commissions.view');

    $hasFinanceNavigation =
        $admin->hasPermission('products.view')
        || $admin->hasPermission('payments.view')
        || $admin->hasPermission('payment_accounts.manage')
        || $admin->hasPermission('finance.view')
        || $admin->hasPermission('payouts.view');

    $hasSecurityNavigation =
        $admin->hasPermission('kyc.view')
        || $admin->hasPermission('security.view')
        || $admin->hasPermission('security.events')
        || $admin->hasPermission('audit.view');

    $hasSystemNavigation =
        $admin->hasPermission('admins.view')
        || $admin->hasPermission('roles.view')
        || $admin->hasPermission('settings.view')
        || $admin->hasPermission('integrations.view')
        || $admin->hasPermission('legal.view')
        || $admin->hasPermission('languages.view')
        || $admin->hasPermission('staff.manage');
@endphp

<aside class="sidebar" id="adminSidebar">
    <div class="sidebar-brand">
        <div class="brand-mark">X</div>
        <div>
            <div class="brand-name">TRADE<span>X</span></div>
            <div class="brand-subtitle">ADMINISTRATION</div>
        </div>
    </div>

    <nav class="sidebar-nav">
        @if($admin->hasPermission('dashboard.view'))
            <div class="nav-section-title">OVERVIEW</div>
            <a
                href="{{ route('admin.dashboard') }}"
                class="nav-link {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
            >
                <span class="nav-icon">◈</span>
                <span>Dashboard</span>
            </a>
        @endif

        @if($hasCustomerNavigation)
            <div class="nav-section-title">CUSTOMERS</div>

            @if($admin->hasPermission('users.view'))
                <a
                    href="{{ route('admin.customers.dashboard') }}"
                    class="nav-link {{ request()->routeIs('admin.customers.dashboard') ? 'active' : '' }}"
                >
                    <span class="nav-icon">◎</span>
                    <span>Customer Dashboard</span>
                </a>

                <a
                    href="{{ route('admin.users.index') }}"
                    class="nav-link {{ request()->routeIs('admin.users.*') && $customerSegment === '' ? 'active' : '' }}"
                >
                    <span class="nav-icon">☰</span>
                    <span>All Customers</span>
                </a>
            @endif

            @if($admin->hasPermission('users.invite'))
                <a
                    href="{{ route('admin.customers.create') }}"
                    class="nav-link {{ request()->routeIs('admin.customers.create') ? 'active' : '' }}"
                >
                    <span class="nav-icon">＋</span>
                    <span>Add Customer</span>
                </a>
            @endif

            @if($admin->hasPermission('users.view'))
                <a
                    href="{{ route('admin.customers.onboarding') }}"
                    class="nav-link {{ request()->routeIs('admin.customers.onboarding') ? 'active' : '' }}"
                >
                    <span class="nav-icon">◌</span>
                    <span>Pending Onboarding</span>
                </a>

                <a
                    href="{{ route('admin.users.index', ['segment' => 'FREE']) }}"
                    class="nav-link {{ request()->routeIs('admin.users.index') && $customerSegment === 'FREE' ? 'active' : '' }}"
                >
                    <span class="nav-icon">○</span>
                    <span>Free Customers</span>
                </a>

                <a
                    href="{{ route('admin.users.index', ['segment' => 'PREMIUM']) }}"
                    class="nav-link {{ request()->routeIs('admin.users.index') && $customerSegment === 'PREMIUM' ? 'active' : '' }}"
                >
                    <span class="nav-icon">◆</span>
                    <span>Premium Customers</span>
                </a>

                <a
                    href="{{ route('admin.users.index', ['segment' => 'RESTRICTED']) }}"
                    class="nav-link {{ request()->routeIs('admin.users.index') && $customerSegment === 'RESTRICTED' ? 'active' : '' }}"
                >
                    <span class="nav-icon">⊘</span>
                    <span>Suspended / Blocked</span>
                </a>

                <a
                    href="{{ route('admin.users.index', ['segment' => 'RECENT']) }}"
                    class="nav-link {{ request()->routeIs('admin.users.index') && $customerSegment === 'RECENT' ? 'active' : '' }}"
                >
                    <span class="nav-icon">◷</span>
                    <span>Recently Added</span>
                </a>
            @endif
        @endif

        @if($hasTradingFloorNavigation)
            <div class="nav-section-title">TRADING FLOOR</div>

            <a
                href="{{ route('admin.trading_floor.index') }}"
                class="nav-link {{ request()->routeIs('admin.trading_floor.index') ? 'active' : '' }}"
            >
                <span class="nav-icon">▦</span>
                <span>Floor Dashboard</span>
            </a>

            <a
                href="{{ route('admin.trading_floor.clients.index') }}"
                class="nav-link {{ request()->routeIs('admin.trading_floor.clients.index') || request()->routeIs('admin.trading_floor.clients.show') || request()->routeIs('admin.trading_floor.clients.edit') ? 'active' : '' }}"
            >
                <span class="nav-icon">◎</span>
                <span>Floor Customers</span>
            </a>

            @if($admin->isSuperAdmin() || $admin->hasPermission('trading_floor.clients.manage'))
                <a
                    href="{{ route('admin.trading_floor.clients.create') }}"
                    class="nav-link {{ request()->routeIs('admin.trading_floor.clients.create') ? 'active' : '' }}"
                >
                    <span class="nav-icon">＋</span>
                    <span>Register Customer</span>
                </a>
            @endif
        @endif

        @if($hasContentNavigation)
            <div class="nav-section-title">CONTENT</div>

            @if($admin->hasPermission('education.view'))
                <a
                    href="{{ route('admin.education.courses.index') }}"
                    class="nav-link {{ request()->routeIs('admin.education.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">▣</span>
                    <span>Education</span>
                </a>
            @endif

            @if($admin->hasPermission('our_channel.view'))
                <a
                    href="{{ route('admin.our_channel.index') }}"
                    class="nav-link {{ request()->routeIs('admin.our_channel.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">▶</span>
                    <span>Our Channel</span>
                </a>
            @endif

            @if($admin->hasPermission('notifications.view'))
                <a
                    href="{{ route('admin.notifications.index') }}"
                    class="nav-link {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">✦</span>
                    <span>Notifications</span>
                </a>
            @endif
        @endif

        @if($admin->hasPermission('trade_ideas.view'))
            <div class="nav-section-title">TRADE IDEAS</div>

            <a
                href="{{ route('admin.trade_ideas.index') }}"
                class="nav-link {{ request()->routeIs('admin.trade_ideas.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">◇</span>
                <span>Long-Term Trade Ideas</span>
            </a>

            <a
                href="{{ route('admin.daily_trade_ideas.index') }}"
                class="nav-link {{ request()->routeIs('admin.daily_trade_ideas.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">◈</span>
                <span>Daily Trade Ideas</span>
            </a>
        @endif

        @if($hasSecondBrainNavigation)
            <div class="nav-section-title">2ND BRAIN</div>

            @if($admin->hasPermission('trade_ideas.view'))
            <details class="nav-submenu-group">
                <summary class="nav-submenu-summary">
                    <span class="nav-icon">FX</span>
                    <span>2ND Brain Forex</span>
                    <span class="nav-submenu-chevron" aria-hidden="true">⌄</span>
                </summary>

                <div class="nav-submenu-panel" aria-label="2ND Brain Forex submenu">
                    <span class="nav-submenu-link nav-submenu-link-disabled">CRT Entry</span>
                    <span class="nav-submenu-link nav-submenu-link-disabled">Session (LKZ, NYKZ)</span>
                    <span class="nav-submenu-link nav-submenu-link-disabled">Brain Entry</span>
                    <span class="nav-submenu-link nav-submenu-link-disabled">News Trade</span>
                </div>
            </details>

            <details class="nav-submenu-group">
                <summary class="nav-submenu-summary">
                    <span class="nav-icon">₿</span>
                    <span>2ND Brain Crypto</span>
                    <span class="nav-submenu-chevron" aria-hidden="true">⌄</span>
                </summary>

                <div class="nav-submenu-panel" aria-label="2ND Brain Crypto submenu">
                    <span class="nav-submenu-link nav-submenu-link-disabled">CRT Entry</span>
                    <span class="nav-submenu-link nav-submenu-link-disabled">Brain Entry</span>
                </div>
            </details>

            <details class="nav-submenu-group">
                <summary class="nav-submenu-summary">
                    <span class="nav-icon">D</span>
                    <span>2ND Brain Deriv</span>
                    <span class="nav-submenu-chevron" aria-hidden="true">⌄</span>
                </summary>

                <div class="nav-submenu-panel" aria-label="2ND Brain Deriv submenu">
                    <span class="nav-submenu-link nav-submenu-link-disabled">CRT Entry</span>
                    <span class="nav-submenu-link nav-submenu-link-disabled">Brain Entry</span>
                </div>
            </details>
            @endif

            @if($admin->isSuperAdmin())
            <a
                href="{{ route('admin.second_brain_knowledge.index') }}"
                class="nav-link {{ request()->routeIs('admin.second_brain_knowledge.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">✦</span>
                <span>Knowledge Center</span>
            </a>
            @endif
        @endif

        @if($admin->hasPermission('screener.monitor.view'))
            <div class="nav-section-title">SCREENER MONITOR</div>

            <details class="nav-submenu-group" {{ request()->routeIs('admin.screener.*') ? 'open' : '' }}>
                <summary class="nav-submenu-summary">
                    <span class="nav-icon">◉</span>
                    <span>Screener Monitor</span>
                    <span class="nav-submenu-chevron" aria-hidden="true">⌄</span>
                </summary>

                <div class="nav-submenu-panel" aria-label="Screener Monitor submenu">
                    <a
                        href="{{ route('admin.screener.index') }}"
                        class="nav-submenu-link {{ request()->routeIs('admin.screener.index') ? 'active' : '' }}"
                    >Health Monitor</a>

                    <a
                        href="{{ route('admin.screener.markets') }}"
                        class="nav-submenu-link {{ request()->routeIs('admin.screener.markets') || request()->routeIs('admin.screener.markets.*') ? 'active' : '' }}"
                    >Market Results</a>

                    <a
                        href="{{ route('admin.screener.bubbles') }}"
                        class="nav-submenu-link {{ request()->routeIs('admin.screener.bubbles') ? 'active' : '' }}"
                    >Market Bubbles</a>
                </div>
            </details>
        @endif

        @if($hasCopyTradingNavigation)
            <div class="nav-section-title">COPY TRADING</div>

            @if($admin->hasPermission('copy_trading.view'))
                <a
                    href="{{ route('admin.copy_trading.brokers.index') }}"
                    class="nav-link {{ request()->routeIs('admin.copy_trading.brokers.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">◎</span>
                    <span>Broker Management</span>
                </a>
            @endif

            @if($admin->hasPermission('copy_trading.accounts.view'))
                <a
                    href="{{ route('admin.copy_trading.verifications.index') }}"
                    class="nav-link {{ request()->routeIs('admin.copy_trading.verifications.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">✓</span>
                    <span>Broker Account Verification</span>
                </a>

                <a
                    href="{{ route('admin.copy_trading.accounts.index') }}"
                    class="nav-link {{ request()->routeIs('admin.copy_trading.accounts.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">◫</span>
                    <span>Copy Trade Accounts</span>
                </a>
            @endif

            @if($admin->hasPermission('copy_trading.billing.view'))
                <a
                    href="{{ route('admin.copy_trading.billing.index') }}"
                    class="nav-link {{ request()->routeIs('admin.copy_trading.billing.index') || request()->routeIs('admin.copy_trading.billing.accounts.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">$</span>
                    <span>Copy Trade Billing</span>
                </a>

                <a
                    href="{{ route('admin.copy_trading.billing.calendar.index') }}"
                    class="nav-link {{ request()->routeIs('admin.copy_trading.billing.calendar.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">◷</span>
                    <span>Billing Calendar</span>
                </a>
            @endif
        @endif

        @if($hasInvestmentNavigation)
            <div class="nav-section-title">INVESTMENTS</div>

            @if($admin->hasPermission('investments.view'))
                <a
                    href="{{ route('admin.investments.dashboard') }}"
                    class="nav-link {{ request()->routeIs('admin.investments.dashboard') ? 'active' : '' }}"
                >
                    <span class="nav-icon">◈</span>
                    <span>Investment Dashboard</span>
                </a>

                <a
                    href="{{ route('admin.investments.index') }}"
                    class="nav-link {{ request()->routeIs('admin.investments.index') || request()->routeIs('admin.investments.show') ? 'active' : '' }}"
                >
                    <span class="nav-icon">▤</span>
                    <span>All Investments</span>
                </a>
            @endif

            @if($admin->hasPermission('investments.create'))
                <a
                    href="{{ route('admin.investments.create') }}"
                    class="nav-link {{ request()->routeIs('admin.investments.create') ? 'active' : '' }}"
                >
                    <span class="nav-icon">＋</span>
                    <span>Add Investment</span>
                </a>
            @endif

            @if($admin->hasPermission('investments.deposits.view'))
                <a
                    href="{{ route('admin.investments.deposits.index') }}"
                    class="nav-link {{ request()->routeIs('admin.investments.deposits.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">◉</span>
                    <span>Deposit Verification</span>
                </a>
            @endif

            @if($admin->hasPermission('investments.agreements.view'))
                <a
                    href="{{ route('admin.investments.agreements.index') }}"
                    class="nav-link {{ request()->routeIs('admin.investments.agreements.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">§</span>
                    <span>Agreements</span>
                </a>
            @endif

            @if($admin->hasPermission('investments.activate'))
                <a
                    href="{{ route('admin.investments.activation.index') }}"
                    class="nav-link {{ request()->routeIs('admin.investments.activation.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">✓</span>
                    <span>Activation Queue</span>
                </a>
            @endif

            @if($admin->hasPermission('investments.profit_periods.view'))
                <a
                    href="{{ route('admin.investments.profit_periods.index') }}"
                    class="nav-link {{ request()->routeIs('admin.investments.profit_periods.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">%</span>
                    <span>Profit Engine</span>
                </a>
            @endif

            @if($admin->hasPermission('investments.settlements.view'))
                <a
                    href="{{ route('admin.investments.settlements.index') }}"
                    class="nav-link {{ request()->routeIs('admin.investments.settlements.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">⇄</span>
                    <span>Settlements</span>
                </a>
            @endif
        @endif

        @if($hasAffiliateNavigation)
            <div class="nav-section-title">AFFILIATES</div>

            @if($admin->hasPermission('affiliates.view'))
                <a
                    href="{{ route('admin.affiliates.index') }}"
                    class="nav-link {{ request()->routeIs('admin.affiliates.index') || request()->routeIs('admin.affiliates.show') || request()->routeIs('admin.affiliates.tree') || request()->routeIs('admin.affiliates.analytics') || request()->routeIs('admin.affiliates.stages.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">♢</span>
                    <span>Affiliates</span>
                </a>
            @endif

            @if($admin->hasPermission('commissions.view'))
                <a
                    href="{{ route('admin.affiliates.commissions.index') }}"
                    class="nav-link {{ request()->routeIs('admin.affiliates.commissions.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">%</span>
                    <span>Commission Ledger</span>
                </a>
            @endif
        @endif

        @if($hasFinanceNavigation)
            <div class="nav-section-title">FINANCE</div>

            @if($admin->hasPermission('products.view'))
                <a
                    href="{{ route('admin.products.index') }}"
                    class="nav-link {{ request()->routeIs('admin.products.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">◇</span>
                    <span>Product Catalog</span>
                </a>
            @endif

            @if($admin->hasPermission('payments.view'))
                <a
                    href="{{ route('admin.payments.index') }}"
                    class="nav-link {{ request()->routeIs('admin.payments.index') || request()->routeIs('admin.payments.show') || request()->routeIs('admin.payments.paypal.*') || request()->routeIs('admin.payments.stripe.*') || request()->routeIs('admin.payments.nowpayments.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">$</span>
                    <span>Payments</span>
                </a>

                <a
                    href="{{ route('admin.payments.bank.index') }}"
                    class="nav-link {{ request()->routeIs('admin.payments.bank.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">▤</span>
                    <span>Bank Deposits</span>
                </a>

                <a
                    href="{{ route('admin.payments.crypto.index') }}"
                    class="nav-link {{ request()->routeIs('admin.payments.crypto.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">◇</span>
                    <span>Crypto Deposits</span>
                </a>

                <a
                    href="{{ route('admin.payments.usdt.index') }}"
                    class="nav-link {{ request()->routeIs('admin.payments.usdt.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">◈</span>
                    <span>USDT Deposits</span>
                </a>
            @endif

            @if($admin->hasPermission('payment_accounts.manage'))
                <a
                    href="{{ route('admin.payments.accounts.index') }}"
                    class="nav-link {{ request()->routeIs('admin.payments.accounts.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">◫</span>
                    <span>Payment Accounts</span>
                </a>
            @endif

            @if($admin->hasPermission('payouts.view'))
                <a
                    href="{{ route('admin.payouts.index') }}"
                    class="nav-link {{ request()->routeIs('admin.payouts.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">⇅</span>
                    <span>Payouts</span>
                </a>
            @endif

            @if($admin->hasPermission('finance.view'))
                <a
                    href="{{ route('admin.finance.dashboard') }}"
                    class="nav-link {{ request()->routeIs('admin.finance.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">◫</span>
                    <span>Finance</span>
                </a>
            @endif
        @endif

        @if($hasSecurityNavigation)
            <div class="nav-section-title">SECURITY</div>

            @if($admin->hasPermission('kyc.view'))
                <a
                    href="{{ route('admin.kyc.index') }}"
                    class="nav-link {{ request()->routeIs('admin.kyc.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">◉</span>
                    <span>KYC Verification</span>
                </a>
            @endif

            @if($admin->hasPermission('security.view') || $admin->hasPermission('security.events'))
                <a
                    href="{{ route('admin.security.home') }}"
                    class="nav-link {{ request()->routeIs('admin.security.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">⌾</span>
                    <span>Security</span>
                </a>
            @endif

            @if($admin->hasPermission('audit.view'))
                <a
                    href="{{ route('admin.audit.index') }}"
                    class="nav-link {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">▤</span>
                    <span>Audit Logs</span>
                </a>
            @endif
        @endif

        @if($hasSystemNavigation)
            <div class="nav-section-title">SYSTEM</div>

            @if($admin->hasPermission('admins.view'))
                <a
                    href="{{ route('admin.administration.admins.index') }}"
                    class="nav-link {{ request()->routeIs('admin.administration.admins.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">♙</span>
                    <span>Admin Users</span>
                </a>
            @endif

            @if($admin->isSuperAdmin() || $admin->hasPermission('staff.manage'))
                <a
                    href="{{ route('admin.staff.index') }}"
                    class="nav-link {{ request()->routeIs('admin.staff.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">♟</span>
                    <span>Staff Management</span>
                </a>
            @endif
            @if($admin->hasPermission('roles.view'))
                <a
                    href="{{ route('admin.administration.roles.index') }}"
                    class="nav-link {{ request()->routeIs('admin.administration.roles.*') || request()->routeIs('admin.administration.permissions.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">⚿</span>
                    <span>Roles & Permissions</span>
                </a>
            @endif

            @if($admin->hasPermission('settings.view'))
                <a
                    href="{{ route('admin.settings.general') }}"
                    class="nav-link {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">⚙</span>
                    <span>Settings</span>
                </a>
            @endif

            @if($admin->hasPermission('integrations.view'))
                <a
                    href="{{ route('admin.integrations.index') }}"
                    class="nav-link {{ request()->routeIs('admin.integrations.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">⌘</span>
                    <span>Integrations</span>
                </a>
            @endif

            @if($admin->hasPermission('legal.view'))
                <a
                    href="{{ route('admin.legal.index') }}"
                    class="nav-link {{ request()->routeIs('admin.legal.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">§</span>
                    <span>Legal & Policies</span>
                </a>
            @endif

            @if($admin->hasPermission('languages.view'))
                <a
                    href="{{ route('admin.languages.index') }}"
                    class="nav-link {{ request()->routeIs('admin.languages.*') ? 'active' : '' }}"
                >
                    <span class="nav-icon">文</span>
                    <span>Languages</span>
                </a>
            @endif
        @endif
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar">{{ strtoupper(substr($admin->name, 0, 1)) }}</div>
            <div class="sidebar-user-info">
                <strong>{{ $admin->name }}</strong>
                <span>{{ $admin->roles->first()?->name ?? 'Administrator' }}</span>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.logout') }}">
            @csrf
            <button type="submit" class="sidebar-logout">Secure Logout</button>
        </form>
    </div>
</aside>
