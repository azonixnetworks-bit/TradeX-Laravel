(() => {
    'use strict';

    const script = document.currentScript;
    const nav = document.querySelector('.sidebar-nav');

    if (!script) {
        return;
    }

    const responsiveHref = new URL('../css/economic-news-laptop.css', script.src).href;
    if (!document.querySelector(`link[data-economic-news-responsive="true"]`)) {
        const responsiveStyles = document.createElement('link');
        responsiveStyles.rel = 'stylesheet';
        responsiveStyles.href = responsiveHref;
        responsiveStyles.dataset.economicNewsResponsive = 'true';
        document.head.appendChild(responsiveStyles);
    }

    if (!nav) {
        return;
    }

    const links = [
        ['overviewUrl', '◉', 'Overview'],
        ['highUrl', '!', 'High Impact'],
        ['mediumUrl', '◆', 'Medium Impact'],
        ['liveUrl', '●', 'Live Updates'],
        ['calendarUrl', '◷', 'Economic Calendar'],
    ];

    const resolved = links
        .map(([key, icon, label]) => [script.dataset[key], icon, label])
        .filter(([url]) => Boolean(url));

    if (!resolved.length) {
        return;
    }

    let sectionTitle = Array.from(nav.querySelectorAll('.nav-section-title'))
        .find((title) => title.textContent.trim() === 'ECONOMIC NEWS');

    if (!sectionTitle) {
        sectionTitle = document.createElement('div');
        sectionTitle.className = 'nav-section-title';
        sectionTitle.textContent = 'ECONOMIC NEWS';

        const tradingToolsTitle = Array.from(nav.querySelectorAll('.nav-section-title'))
            .find((title) => title.textContent.trim() === 'TRADING TOOLS');

        if (tradingToolsTitle) {
            const nextSection = Array.from(nav.children)
                .slice(Array.from(nav.children).indexOf(tradingToolsTitle) + 1)
                .find((element) => element.classList?.contains('nav-section-title'));

            if (nextSection) {
                nextSection.insertAdjacentElement('beforebegin', sectionTitle);
            } else {
                nav.appendChild(sectionTitle);
            }
        } else {
            nav.appendChild(sectionTitle);
        }
    }

    let anchor = sectionTitle;
    const currentPath = window.location.pathname.replace(/\/$/, '');

    resolved.forEach(([url, iconText, labelText]) => {
        const existing = Array.from(nav.querySelectorAll('.nav-link'))
            .find((link) => link.getAttribute('href') === url);

        if (existing) {
            anchor = existing;
            return;
        }

        const link = document.createElement('a');
        link.href = url;
        link.className = 'nav-link';
        link.setAttribute('aria-label', `Open ${labelText}`);

        const targetPath = new URL(url, window.location.origin)
            .pathname
            .replace(/\/$/, '');

        if (currentPath === targetPath) {
            link.classList.add('active');
        }

        const icon = document.createElement('span');
        icon.className = 'nav-icon';
        icon.textContent = iconText;

        const label = document.createElement('span');
        label.textContent = labelText;

        link.append(icon, label);
        anchor.insertAdjacentElement('afterend', link);
        anchor = link;
    });
})();
