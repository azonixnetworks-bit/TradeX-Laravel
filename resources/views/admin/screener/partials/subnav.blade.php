<nav class="screener-subnav" aria-label="Screener views">
    <a
        href="{{ route('admin.screener.index') }}"
        class="{{ request()->routeIs('admin.screener.index') ? 'active' : '' }}"
    >Health Monitor</a>
    <a
        href="{{ route('admin.screener.markets') }}"
        class="{{ request()->routeIs('admin.screener.markets') ? 'active' : '' }}"
    >Market Results</a>
</nav>
