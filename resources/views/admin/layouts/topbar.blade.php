@php
    $admin = auth('admin')->user();
@endphp

<header class="topbar">

    <div class="topbar-left">

        <button
            id="sidebarToggle"
            class="sidebar-toggle"
            type="button"
            aria-label="Toggle navigation"
        >
            ☰
        </button>

        <div>

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