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
        href="{{ asset('admin/css/branding.css') }}"
    >

    <link
        rel="stylesheet"
        href="{{ asset('admin/css/forms.css') }}"
    >

    @stack('styles')
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

            @if(session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if(session('error'))
                <div class="alert alert-danger">
                    {{ session('error') }}
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

<script
    src="{{ asset('admin/js/branding.js') }}">
</script>

@stack('scripts')

</body>
</html>