(() => {
    'use strict';

    const script = document.currentScript;

    if (!script) {
        return;
    }

    const monitorUrl = script.dataset.screenerUrl;
    const liveUrl = script.dataset.liveScreenerUrl;
    const dashboardUrl = script.dataset.dashboardUrl;
    const marketClockUrl = script.dataset.marketClockUrl;

    if (!monitorUrl || !liveUrl || !dashboardUrl) {
        return;
    }

    const links = Array.from(
        document.querySelectorAll('.sidebar-nav .nav-link')
    );

    const dashboardLink = links.find(
        (link) => link.getAttribute('href') === dashboardUrl
    );

    if (!dashboardLink) {
        return;
    }

    const marketClockLink = marketClockUrl
        ? links.find((link) => link.getAttribute('href') === marketClockUrl)
        : null;

    const currentPath = window.location.pathname.replace(/\/$/, '');

    const buildLink = (
        url,
        label,
        iconText,
        ariaLabel,
        includeDescendants = true
    ) => {
        const link = document.createElement('a');
        link.href = url;
        link.className = 'nav-link';
        link.setAttribute('aria-label', ariaLabel);

        const targetPath = new URL(url, window.location.origin)
            .pathname
            .replace(/\/$/, '');

        if (
            currentPath === targetPath
            || (includeDescendants && currentPath.startsWith(`${targetPath}/`))
        ) {
            link.classList.add('active');
        }

        const icon = document.createElement('span');
        icon.className = 'nav-icon';
        icon.textContent = iconText;

        const text = document.createElement('span');
        text.textContent = label;

        link.append(icon, text);
        return link;
    };

    let anchor = marketClockLink || dashboardLink;

    const existingLive = links.find(
        (link) => link.getAttribute('href') === liveUrl
    );

    if (existingLive) {
        anchor = existingLive;
    } else {
        const liveLink = buildLink(
            liveUrl,
            'Live Screener',
            '⌁',
            'Open Live Screener',
            true
        );
        anchor.insertAdjacentElement('afterend', liveLink);
        anchor = liveLink;
    }

    const existingMonitor = Array.from(
        document.querySelectorAll('.sidebar-nav .nav-link')
    ).find((link) => link.getAttribute('href') === monitorUrl);

    if (!existingMonitor) {
        const monitorLink = buildLink(
            monitorUrl,
            'Screener Monitor',
            '◉',
            'Open Screener Monitor',
            false
        );
        anchor.insertAdjacentElement('afterend', monitorLink);
    }
})();
