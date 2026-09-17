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

    @if(request()->routeIs('admin.trading_floor.*'))
        <link
            rel="stylesheet"
            href="{{ asset('admin/css/trading-floor-premium.css') }}"
        >
        <link
            rel="stylesheet"
            href="{{ asset('admin/css/trading-floor-theme-lock.css') }}"
        >
    @endif
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

@if(auth('admin')->user()?->hasPermission('market_clock.view'))
<script
    src="{{ asset('admin/js/market-clock-nav.js') }}"
    data-dashboard-url="{{ route('admin.dashboard') }}"
    data-market-clock-url="{{ route('admin.market_clock.index') }}"
></script>
@endif

@if(auth('admin')->user()?->hasPermission('screener.monitor.view'))
<script
    src="{{ asset('admin/js/screener-monitor-nav.js') }}"
    data-dashboard-url="{{ route('admin.dashboard') }}"
    data-market-clock-url="{{ route('admin.market_clock.index') }}"
    data-screener-url="{{ route('admin.screener.index') }}"
></script>
@endif

@if(auth('admin')->user()?->hasPermission('risk_reward.view'))
<script
    src="{{ asset('admin/js/risk-reward-nav.js') }}"
    data-calculator-url="{{ route('admin.risk_reward.index') }}"
></script>
@endif

@if(auth('admin')->user()?->hasPermission('funded_advisor.view'))
<script
    src="{{ asset('admin/js/funded-account-advisor-nav.js') }}"
    data-advisor-url="{{ route('admin.funded_advisor.index') }}"
></script>
@endif

@if(auth('admin')->user()?->isSuperAdmin() || auth('admin')->user()?->hasPermission('funded_no_trade_zone.view'))
<script
    src="{{ asset('admin/js/funded-no-trade-zone-nav.js') }}"
    data-no-trade-url="{{ route('admin.funded_no_trade_zone.index') }}"
></script>
@endif

@if(auth('admin')->user()?->hasPermission('trading_routine.view'))
<script
    src="{{ asset('admin/js/trading-routine-nav.js') }}"
    data-routine-url="{{ route('admin.trading_routine.index') }}"
></script>
@endif

@if(auth('admin')->user()?->isSuperAdmin() || auth('admin')->user()?->hasPermission('economic_news.view'))
<script
    src="{{ asset('admin/js/economic-news-nav.js') }}"
    data-overview-url="{{ route('admin.economic_news.index') }}"
    data-high-url="{{ route('admin.economic_news.high_impact') }}"
    data-medium-url="{{ route('admin.economic_news.medium_impact') }}"
    data-live-url="{{ route('admin.economic_news.live') }}"
    data-calendar-url="{{ route('admin.economic_news.calendar') }}"
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