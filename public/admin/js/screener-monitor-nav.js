(() => {
    'use strict';

    const script = document.currentScript;

    if (!script) {
        return;
    }

    const screenerUrl = script.dataset.screenerUrl;
    const dashboardUrl = script.dataset.dashboardUrl;
    const marketClockUrl = script.dataset.marketClockUrl;

    if (!screenerUrl || !dashboardUrl) {
        return;
    }

    const links = Array.from(
        document.querySelectorAll('.sidebar-nav .nav-link')
    );

    if (links.some((link) => link.getAttribute('href') === screenerUrl)) {
        return;
    }

    const dashboardLink = links.find(
        (link) => link.getAttribute('href') === dashboardUrl
    );

    if (!dashboardLink) {
        return;
    }

    const marketClockLink = marketClockUrl
        ? links.find((link) => link.getAttribute('href') === marketClockUrl)
        : null;

    const anchor = marketClockLink || dashboardLink;
    const link = document.createElement('a');
    link.href = screenerUrl;
    link.className = 'nav-link';
    link.setAttribute('aria-label', 'Open Screener Monitor');

    const currentPath = window.location.pathname.replace(/\/$/, '');
    const screenerPath = new URL(screenerUrl, window.location.origin)
        .pathname
        .replace(/\/$/, '');

    if (currentPath === screenerPath || currentPath.startsWith(`${screenerPath}/`)) {
        link.classList.add('active');
    }

    const icon = document.createElement('span');
    icon.className = 'nav-icon';
    icon.textContent = '⌁';

    const label = document.createElement('span');
    label.textContent = 'Screener Monitor';

    link.append(icon, label);
    anchor.insertAdjacentElement('afterend', link);
})();
