<!DOCTYPE html>
<html lang="{{ $brand['language'] }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#05090f">
    <meta name="robots" content="index, follow, max-image-preview:large, max-snippet:-1, max-video-preview:-1">

    <title>{{ $page['title'] }}</title>
    <meta name="description" content="{{ $page['description'] }}">
    <link rel="canonical" href="{{ $canonical }}">

    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $brand['name'] }}">
    <meta property="og:locale" content="{{ $brand['locale'] }}">
    <meta property="og:title" content="{{ $page['title'] }}">
    <meta property="og:description" content="{{ $page['description'] }}">
    <meta property="og:url" content="{{ $canonical }}">
    <meta property="og:image" content="{{ $baseUrl }}/admin/brand/tradex-logo.png">
    <meta property="og:image:alt" content="TradeX — The OS for Every Trader">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $page['title'] }}">
    <meta name="twitter:description" content="{{ $page['description'] }}">
    <meta name="twitter:image" content="{{ $baseUrl }}/admin/brand/tradex-logo.png">

    @if(config('seo.verification.google'))
        <meta name="google-site-verification" content="{{ config('seo.verification.google') }}">
    @endif
    @if(config('seo.verification.bing'))
        <meta name="msvalidate.01" content="{{ config('seo.verification.bing') }}">
    @endif

    <link rel="icon" type="image/png" href="{{ asset('admin/brand/tradex-favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('site/tradex.css') }}">

    <script type="application/ld+json">{!! json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
</head>
<body>
    <a class="skip-link" href="#main-content">Skip to content</a>

    <header class="site-header">
        <div class="container nav-wrap">
            <a class="brand" href="{{ route('site.home') }}" aria-label="TradeX home">
                <img src="{{ asset('admin/brand/tradex-favicon.png') }}" width="42" height="42" alt="">
                <span><strong>TradeX</strong><small>{{ $brand['tagline'] }}</small></span>
            </a>

            <nav class="primary-nav" aria-label="Primary navigation">
                <a href="{{ route('site.features') }}">Features</a>
                <a href="{{ route('site.trading-tools') }}">Trading Tools</a>
                <a href="{{ route('site.economic-calendar') }}">Economic Calendar</a>
                <a href="{{ route('site.market-news') }}">Market News</a>
                <a href="{{ route('site.education') }}">Education</a>
                <a href="{{ route('site.about') }}">About</a>
            </nav>
        </div>
    </header>

    <main id="main-content">
        @yield('content')
    </main>

    <footer class="site-footer">
        <div class="container footer-grid">
            <div>
                <div class="footer-brand">TradeX</div>
                <p>{{ $brand['tagline'] }}</p>
            </div>
            <div>
                <strong>Explore</strong>
                <a href="{{ route('site.features') }}">Features</a>
                <a href="{{ route('site.trading-tools') }}">Trading Tools</a>
                <a href="{{ route('site.economic-calendar') }}">Economic Calendar</a>
            </div>
            <div>
                <strong>Company</strong>
                <a href="{{ route('site.about') }}">About TradeX</a>
                <a href="{{ route('site.education') }}">Trading Education</a>
                <a href="{{ route('admin.login') }}" rel="nofollow">Admin access</a>
            </div>
        </div>
        <div class="container disclaimer">
            <p><strong>Risk notice:</strong> Trading and investing involve risk. TradeX provides education, planning, market intelligence, and decision-support tools and does not guarantee profits or market direction.</p>
            <p>&copy; {{ now()->year }} TradeX. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
