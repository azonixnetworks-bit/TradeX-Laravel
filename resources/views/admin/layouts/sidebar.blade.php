@php
    $admin = auth('admin')->user();
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
                <span class="nav-icon">
                    ◈
                </span>

                <span>
                    Dashboard
                </span>
            </a>

        @endif


        <div class="nav-section-title">
            MANAGEMENT
        </div>

       @if($admin->hasPermission('users.view'))

            <a
                href="{{ route('admin.users.index') }}"
                class="nav-link
                {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
            >
                <span class="nav-icon">
                    ◎
                </span>

                <span>
                    Users
                </span>
            </a>

        @endif


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
                <span>Trade Ideas</span>
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
                href="#"
                class="nav-link nav-disabled"
            >
                <span class="nav-icon">⚙</span>
                <span>Settings</span>
                <span class="coming-soon">Soon</span>
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
