@props([
    'title' => 'TradeX Report',
    'subtitle' => null,
])

<header class="tradex-report-brand">
    <img
        class="tradex-report-brand-logo"
        src="{{ asset('admin/brand/tradex-logo.webp') }}"
        alt="TradeX — Learn. Trade. Grow."
        width="310"
        height="320"
    >

    <div class="tradex-report-brand-meta">
        <strong>{{ $title }}</strong>

        @if(filled($subtitle))
            <span>{{ $subtitle }}</span>
        @endif

        <span>LEARN • TRADE • GROW</span>
    </div>
</header>
