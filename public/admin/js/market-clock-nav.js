(() => {
    'use strict';

    const script = document.currentScript;

    if (!script) {
        return;
    }

    const marketClockUrl = script.dataset.marketClockUrl;
    const dashboardUrl = script.dataset.dashboardUrl;

    if (!marketClockUrl || !dashboardUrl) {
        return;
    }

    const dashboardLink = Array.from(
        document.querySelectorAll('.sidebar-nav .nav-link')
    ).find((link) => link.getAttribute('href') === dashboardUrl);

    if (!dashboardLink) {
        return;
    }

    const existing = Array.from(
        document.querySelectorAll('.sidebar-nav .nav-link')
    ).find((link) => link.getAttribute('href') === marketClockUrl);

    if (existing) {
        return;
    }

    const link = document.createElement('a');
    link.href = marketClockUrl;
    link.className = 'nav-link';
    link.setAttribute('aria-label', 'Open Market Clock');

    const currentPath = window.location.pathname.replace(/\/$/, '');
    const marketPath = new URL(marketClockUrl, window.location.origin)
        .pathname
        .replace(/\/$/, '');

    if (currentPath === marketPath || currentPath.startsWith(`${marketPath}/`)) {
        link.classList.add('active');
    }

    const icon = document.createElement('span');
    icon.className = 'nav-icon';
    icon.textContent = '◷';

    const label = document.createElement('span');
    label.textContent = 'Market Clock';

    link.append(icon, label);
    dashboardLink.insertAdjacentElement('afterend', link);
})();
