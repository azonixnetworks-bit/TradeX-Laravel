@php
    $admin = auth('admin')->user();
    $customerSegment = strtoupper((string) request()->query('segment', ''));
@endphp

<aside
    class="sidebar"
    id="adminSidebar"
>

    <div class="sidebar-brand">

        <div class="brand-mark">
            X
        </div>

        <div>
            <div class="brand-name">
                TRADE<span>X</span>
            </div>

            <div class="brand-subtitle">
                ADMINISTRATION
            </div>
        </div>

    </div>

    <nav class="sidebar-nav">

        <div class="nav-section-title">
            OVERVIEW
        </div>

        @if($admin->hasPermission('dashboard.view'))

            <a
                href="{{ route('admin.dashboard') }}"
                class="nav-link
                {{ request()->routeIs('admin.dashboard') ? 'active' : '' }}"
            >
                <span class="nav-icon">◈</span>
                <span>Dashboard</span>
            </a>

        @endif


        @if(
            $admin->hasPermission('users.view')
            || $admin->hasPermission('users.invite')
        )
            <div class="nav-section-title">
                CUSTOMERS
            </div>
        @endif


        @if($admin->hasPermission('users.view'))

            <a
                href="{{ route('admin.customers.dashboard') }}"
                class="nav-link
                {{ request()->routeIs('admin.customers.dashboard') ? 'active' : '' }}"
            >
                <span class="nav-icon">◎</span>
                <span>Customer Dashboard</span>
            </a>

            <a
                href="{{ route('admin.users.index') }}"
                class="nav-link
                {{ request()->routeIs('admin.users.*') && $customerSegment === '' ? 'active' : '' }}"
            >
                <span class="nav-icon">☰</span>
                <span>All Customers</span>
            </a>

        @endif


        @if($admin->hasPermission('users.invite'))

            <a
                href="{{ route('admin.customers.create') }}"
                class="nav-link
                {{ request()->routeIs('admin.customers.create') ? 'active' : '' }}"
            >
                <span class="nav-icon">＋</span>
                <span>Add Customer</span>
            </a>

        @endif


        @if($admin->hasPermission('users.view'))

            <a
                href="{{ route('admin.customers.onboarding') }}"
                class="nav-link
                {{ request()->routeIs('admin.customers.onboarding') ? 'active' : '' }}"
            >
                <span class="nav-icon">◌</span>
                <span>Pending Onboarding</span>
            </a>

            <a
                href="{{ route('admin.users.index', ['segment' => 'PENDING']) }}"
                class="nav-link
                {{ request()->routeIs('admin.users.index') && $customerSegment === 'PENDING' ? 'active' : '' }}"
            >
                <span class="nav-icon">◔</span>
                <span>Pending Accounts</span>
            </a>

            <a
                href="{{ route('admin.users.index', ['segment' => 'FREE']) }}"
                class="nav-link
                {{ request()->routeIs('admin.users.index') && $customerSegment === 'FREE' ? 'active' : '' }}"
            >
                <span class="nav-icon">○</span>
                <span>Free Customers</span>
            </a>

            <a
                href="{{ route('admin.users.index', ['segment' => 'PREMIUM']) }}"
                class="nav-link
                {{ request()->routeIs('admin.users.index') && $customerSegment === 'PREMIUM' ? 'active' : '' }}"
            >
                <span class="nav-icon">◆</span>
                <span>Premium Customers</span>
            </a>

            <a
                href="{{ route('admin.users.index', ['segment' => 'RESTRICTED']) }}"
                class="nav-link
                {{ request()->routeIs('admin.users.index') && $customerSegment === 'RESTRICTED' ? 'active' : '' }}"
            >
                <span class="nav-icon">⊘</span>
                <span>Suspended / Blocked</span>
            </a>

            <a
                href="{{ route('admin.users.index', ['segment' => 'RECENT']) }}"
                class="nav-link
                {{ request()->routeIs('admin.users.index') && $customerSegment === 'RECENT' ? 'active' : '' }}"
            >
                <span class="nav-icon">◷</span>
                <span>Recently Joined</span>
            </a>

        @endif


        <div class="nav-section-title">
            MANAGEMENT
        </div>


        @if($admin->hasPermission('education.view'))

            <a
                href="{{ route('admin.education.courses.index') }}"
                class="nav-link
                {{ request()->routeIs('admin.education.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">▣</span>
                <span>Education</span>
            </a>

        @endif


        @if($admin->hasPermission('our_channel.view'))

            <a
                href="{{ route('admin.our_channel.index') }}"
                class="nav-link
                {{ request()->routeIs('admin.our_channel.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">▶</span>
                <span>Our Channel</span>
            </a>

        @endif


        @if($admin->hasPermission('trade_ideas.view'))

            <a
                href="{{ route('admin.trade_ideas.index') }}"
                class="nav-link
                {{ request()->routeIs('admin.trade_ideas.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">◇</span>
                <span>Long Term Trade Ideas</span>
            </a>

        @endif


        @if($admin->hasPermission('affiliates.view'))

            <a
                href="{{ route('admin.affiliates.index') }}"
                class="nav-link
                {{ request()->routeIs('admin.affiliates.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">♢</span>
                <span>Affiliates</span>
            </a>

        @endif


        @if($admin->hasPermission('notifications.view'))

            <a
                href="{{ route('admin.notifications.index') }}"
                class="nav-link
                {{ request()->routeIs('admin.notifications.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">✦</span>
                <span>Notifications</span>
            </a>

        @endif


        @if(
            $admin->hasPermission('payments.view')
            ||
            $admin->hasPermission('finance.view')
            ||
            $admin->hasPermission('payouts.view')
        )

            <div class="nav-section-title">
                FINANCE
            </div>

        @endif


        @if($admin->hasPermission('payments.view'))

            <a
                href="{{ route('admin.payments.index') }}"
                class="nav-link
                {{ request()->routeIs('admin.payments.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">$</span>
                <span>Payments</span>
            </a>

        @endif


        @if($admin->hasPermission('payouts.view'))

            <a
                href="{{ route('admin.payouts.index') }}"
                class="nav-link
                {{ request()->routeIs('admin.payouts.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">⇅</span>
                <span>Payouts</span>
            </a>

        @endif


        @if($admin->hasPermission('finance.view'))

            <a
                href="{{ route('admin.finance.dashboard') }}"
                class="nav-link
                {{ request()->routeIs('admin.finance.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">◫</span>
                <span>Finance</span>
            </a>

        @endif


        @if(
            $admin->hasPermission('kyc.view')
            ||
            $admin->hasPermission('security.view')
            ||
            $admin->hasPermission('security.events')
            ||
            $admin->hasPermission('audit.view')
        )

            <div class="nav-section-title">
                SECURITY
            </div>

        @endif


        @if($admin->hasPermission('kyc.view'))

            <a
                href="{{ route('admin.kyc.index') }}"
                class="nav-link
                {{ request()->routeIs('admin.kyc.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">◉</span>
                <span>KYC Verification</span>
            </a>

        @endif


        @if(
            $admin->hasPermission('security.view')
            ||
            $admin->hasPermission('security.events')
        )

            <a
                href="{{ route('admin.security.home') }}"
                class="nav-link
                {{ request()->routeIs('admin.security.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">⌾</span>
                <span>Security</span>
            </a>

        @endif


        @if($admin->hasPermission('audit.view'))

            <a
                href="{{ route('admin.audit.index') }}"
                class="nav-link
                {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">▤</span>
                <span>Audit Logs</span>
            </a>

        @endif


        @if(
            $admin->hasPermission('admins.view')
            ||
            $admin->hasPermission('roles.view')
            ||
            $admin->hasPermission('settings.view')
            ||
            $admin->hasPermission('integrations.view')
            ||
            $admin->hasPermission('legal.view')
            ||
            $admin->hasPermission('languages.view')
        )

            <div class="nav-section-title">
                SYSTEM
            </div>

        @endif


        @if($admin->hasPermission('admins.view'))

            <a
                href="{{ route('admin.administration.admins.index') }}"
                class="nav-link
                {{ request()->routeIs('admin.administration.admins.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">♙</span>
                <span>Admin Users</span>
            </a>

        @endif


        @if($admin->hasPermission('roles.view'))

            <a
                href="{{ route('admin.administration.roles.index') }}"
                class="nav-link
                {{ request()->routeIs('admin.administration.roles.*') || request()->routeIs('admin.administration.permissions.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">⚿</span>
                <span>Roles & Permissions</span>
            </a>

        @endif


        @if($admin->hasPermission('settings.view'))

            <a
                href="{{ route('admin.settings.general') }}"
                class="nav-link
                {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">⚙</span>
                <span>Settings</span>
            </a>

        @endif


        @if($admin->hasPermission('integrations.view'))

            <a
                href="{{ route('admin.integrations.index') }}"
                class="nav-link
                {{ request()->routeIs('admin.integrations.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">⌘</span>
                <span>Integrations</span>
            </a>

        @endif


        @if($admin->hasPermission('legal.view'))

            <a
                href="{{ route('admin.legal.index') }}"
                class="nav-link
                {{ request()->routeIs('admin.legal.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">§</span>
                <span>Legal & Policies</span>
            </a>

        @endif


        @if($admin->hasPermission('languages.view'))

            <a
                href="{{ route('admin.languages.index') }}"
                class="nav-link
                {{ request()->routeIs('admin.languages.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">文</span>
                <span>Languages</span>
            </a>

        @endif

    </nav>

    <div class="sidebar-footer">

        <div class="sidebar-user">

            <div class="sidebar-avatar">
                {{ strtoupper(substr($admin->name, 0, 1)) }}
            </div>

            <div class="sidebar-user-info">

                <strong>
                    {{ $admin->name }}
                </strong>

                <span>
                    {{ $admin->roles->first()?->name ?? 'Administrator' }}
                </span>

            </div>

        </div>

        <form
            method="POST"
            action="{{ route('admin.logout') }}"
        >
            @csrf

            <button
                type="submit"
                class="sidebar-logout"
            >
                Secure Logout
            </button>
        </form>

    </div>

</aside>
