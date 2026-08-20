<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>
        @yield('title', 'TradeX Administration')
    </title>

    <link
        rel="stylesheet"
        href="{{ asset('admin/css/admin.css') }}"
    >

    @stack('styles')
</head>

<body>

<div class="admin-app">

    @include('admin.layouts.sidebar')

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

@stack('scripts')

</body>
</html>