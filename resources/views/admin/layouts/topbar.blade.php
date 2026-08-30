@php
    $admin = auth('admin')->user();
@endphp

<header class="topbar">

    <div class="topbar-left">

        <button
            id="sidebarToggle"
            class="sidebar-toggle"
            type="button"
            aria-label="Open navigation"
            aria-controls="adminSidebar"
            aria-expanded="false"
        >
            ☰
        </button>

        <div class="topbar-heading">

            <div class="topbar-title">
                @yield('page-title', 'Dashboard')
            </div>

            <div class="topbar-breadcrumb">
                TradeX / @yield('page-title', 'Dashboard')
            </div>

        </div>

    </div>


    <div class="topbar-right">

        <div class="system-status">

            <span class="status-dot"></span>

            System Operational

        </div>

        <button
            id="adminThemeToggle"
            class="theme-toggle"
            type="button"
            aria-label="Switch to light theme"
            aria-pressed="false"
            title="Switch to light theme"
        >
            <span class="theme-toggle-icon" data-theme-icon aria-hidden="true">☀</span>
            <span class="theme-toggle-state" data-theme-state>Light</span>
        </button>

        <a
            href="{{ route('admin.account.totp.settings') }}"
            class="topbar-totp-link"
            title="Admin two-factor authentication"
        >
            2FA:
            {{ $admin->hasConfirmedTotp() ? 'ON' : 'SETUP' }}
        </a>


        <div class="topbar-admin">

            <div class="topbar-avatar">
                {{ strtoupper(substr($admin->name, 0, 1)) }}
            </div>

            <div class="topbar-admin-info">

                <strong>
                    {{ $admin->name }}
                </strong>

                <span>
                    {{ $admin->roles->first()?->name ?? 'Administrator' }}
                </span>

            </div>

        </div>

    </div>

</header>
