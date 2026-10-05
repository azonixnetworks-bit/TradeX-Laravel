(() => {
    'use strict';

    const script = document.currentScript;

    if (!script) {
        return;
    }

    const screenerUrl = script.dataset.screenerUrl;
    const screenerMarketsUrl = script.dataset.screenerMarketsUrl;
    const dashboardUrl = script.dataset.dashboardUrl;
    const marketClockUrl = script.dataset.marketClockUrl;
    const nav = document.querySelector('.sidebar-nav');

    if (!screenerUrl || !nav) {
        return;
    }

    const links = Array.from(nav.querySelectorAll('.nav-link'));

    if (
        links.some((link) => link.getAttribute('href') === screenerUrl)
        && links.some((link) => link.getAttribute('href') === screenerMarketsUrl)
    ) {
        return;
    }

    const dashboardLink = dashboardUrl
        ? links.find((link) => link.getAttribute('href') === dashboardUrl)
        : null;

    const marketClockLink = marketClockUrl
        ? links.find((link) => link.getAttribute('href') === marketClockUrl)
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

    const anchor = marketClockLink || dashboardLink || overviewTitle;
    anchor.insertAdjacentElement('afterend', link);

    if (!screenerMarketsUrl) {
        return;
    }

    const marketLink = document.createElement('a');
    marketLink.href = screenerMarketsUrl;
    marketLink.className = 'nav-link screener-market-nav-link';
    marketLink.setAttribute('aria-label', 'Open Screener Market Results');

    const marketPath = new URL(screenerMarketsUrl, window.location.origin)
        .pathname
        .replace(/\/$/, '');

    if (currentPath === marketPath) {
        marketLink.classList.add('active');
    }

    const marketIcon = document.createElement('span');
    marketIcon.className = 'nav-icon';
    marketIcon.textContent = '↳';

    const marketLabel = document.createElement('span');
    marketLabel.textContent = 'Market Results';

    marketLink.append(marketIcon, marketLabel);
    link.insertAdjacentElement('afterend', marketLink);
})();
