<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#03070d">

    <title>
        @yield('title', 'TradeX Administration')
    </title>

    <script src="{{ asset('admin/js/theme-init.js') }}"></script>

    <link
        rel="icon"
        type="image/png"
        href="{{ asset('admin/brand/tradex-favicon.png') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('admin/css/admin.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('admin/css/responsive.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('admin/css/sidebar-enhancements.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('admin/css/branding.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('admin/css/forms.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('admin/css/notifications.css') }}"
    >

    @stack('styles')

    <link
        rel="stylesheet"
        href="{{ asset('admin/css/selects.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('admin/css/themes.css') }}"
    >
</head>

<body>

<div
    id="tradexLoader"
    class="tradex-loader"
    role="status"
    aria-label="Loading TradeX Administration"
    aria-live="polite"
>
    <div class="tradex-loader-card">
        <img
            src="{{ asset('admin/brand/tradex-favicon.png') }}"
            alt=""
            width="64"
            height="64"
        >
        <span>TRADEX ADMINISTRATION</span>
    </div>
</div>

@include('admin.components.flash-notifications')

<div class="admin-app">

    @include('admin.layouts.sidebar')

    <div
        id="sidebarBackdrop"
        class="sidebar-backdrop"
        aria-hidden="true"
    ></div>

    <div class="admin-main">

        @include('admin.layouts.topbar')

        <main class="admin-content">

            @if (request()->routeIs('admin.payments.accounts.index'))
                <div hidden aria-hidden="true">
                    @include('admin.payments._provider-security-status')
                </div>
            @endif

            @yield('content')

        </main>

        @include('admin.layouts.footer')

    </div>

</div>

<script
    src="{{ asset('admin/js/admin.js') }}">
</script>

<script
    src="{{ asset('admin/js/sidebar.js') }}">
</script>

@if(auth('admin')->user()?->hasPermission('dashboard.view'))
<script
    src="{{ asset('admin/js/market-clock-nav.js') }}"
    data-dashboard-url="{{ route('admin.dashboard') }}"
    data-market-clock-url="{{ route('admin.market_clock.index') }}"
></script>
<script
    src="{{ asset('admin/js/screener-monitor-nav.js') }}"
    data-dashboard-url="{{ route('admin.dashboard') }}"
    data-market-clock-url="{{ route('admin.market_clock.index') }}"
    data-screener-url="{{ route('admin.screener.index') }}"
></script>
@endif

<script
    src="{{ asset('admin/js/sidebar-enhancements.js') }}">
</script>

<script
    src="{{ asset('admin/js/branding.js') }}">
</script>

<script
    src="{{ asset('admin/js/notifications.js') }}">
</script>

<script
    src="{{ asset('admin/js/theme.js') }}">
</script>

@stack('scripts')

</body>
</html>
