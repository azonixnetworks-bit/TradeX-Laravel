<div
    style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:18px;"
>
    <a
        class="{{ request()->routeIs('admin.settings.general*') ? 'primary-btn' : 'secondary-btn' }}"
        href="{{ route('admin.settings.general') }}"
    >
        General
    </a>

    <a
        class="{{ request()->routeIs('admin.settings.affiliate') ? 'primary-btn' : 'secondary-btn' }}"
        href="{{ route('admin.settings.affiliate') }}"
    >
        Affiliate
    </a>

    <a
        class="{{ request()->routeIs('admin.settings.payments') ? 'primary-btn' : 'secondary-btn' }}"
        href="{{ route('admin.settings.payments') }}"
    >
        Payments
    </a>

    <a
        class="{{ request()->routeIs('admin.settings.security') ? 'primary-btn' : 'secondary-btn' }}"
        href="{{ route('admin.settings.security') }}"
    >
        Security
    </a>

    <a
        class="{{ request()->routeIs('admin.settings.video') ? 'primary-btn' : 'secondary-btn' }}"
        href="{{ route('admin.settings.video') }}"
    >
        Video
    </a>
</div>
