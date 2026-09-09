(() => {
    'use strict';

    const script = document.currentScript;

    if (!script) {
        return;
    }

    const marketClockUrl = script.dataset.marketClockUrl;
    const dashboardUrl = script.dataset.dashboardUrl;
    const nav = document.querySelector('.sidebar-nav');

    if (!marketClockUrl || !nav) {
        return;
    }

    const links = Array.from(nav.querySelectorAll('.nav-link'));

    if (links.some((link) => link.getAttribute('href') === marketClockUrl)) {
        return;
    }

    const dashboardLink = dashboardUrl
        ? links.find((link) => link.getAttribute('href') === dashboardUrl)
        : null;

    let overviewTitle = Array.from(
        nav.querySelectorAll('.nav-section-title')
    ).find((title) => title.textContent.trim() === 'OVERVIEW');

    if (!overviewTitle) {
        overviewTitle = document.createElement('div');
        overviewTitle.className = 'nav-section-title';
        overviewTitle.textContent = 'OVERVIEW';
        nav.prepend(overviewTitle);
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

    if (dashboardLink) {
        dashboardLink.insertAdjacentElement('afterend', link);
    } else {
        overviewTitle.insertAdjacentElement('afterend', link);
    }
})();
