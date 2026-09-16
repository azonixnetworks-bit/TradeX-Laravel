(() => {
    'use strict';

    const script = document.currentScript;
    const nav = document.querySelector('.sidebar-nav');
    const noTradeUrl = script?.dataset.noTradeUrl;

    if (!nav || !noTradeUrl) {
        return;
    }

    const existingLinks = Array.from(nav.querySelectorAll('.nav-link'));
    if (existingLinks.some((link) => link.getAttribute('href') === noTradeUrl)) {
        return;
    }

    let sectionTitle = Array.from(nav.querySelectorAll('.nav-section-title'))
        .find((title) => title.textContent.trim() === 'TRADING TOOLS');

    if (!sectionTitle) {
        sectionTitle = document.createElement('div');
        sectionTitle.className = 'nav-section-title';
        sectionTitle.textContent = 'TRADING TOOLS';

        const nextSection = Array.from(nav.querySelectorAll('.nav-section-title'))
            .find((title) => ['ECONOMIC NEWS', 'COPY TRADING', 'INVESTMENTS', 'AFFILIATES', 'FINANCE', 'SECURITY', 'SYSTEM']
                .includes(title.textContent.trim()));

        if (nextSection) {
            nextSection.insertAdjacentElement('beforebegin', sectionTitle);
        } else {
            nav.appendChild(sectionTitle);
        }
    }

    const link = document.createElement('a');
    link.href = noTradeUrl;
    link.className = 'nav-link';
    link.setAttribute('aria-label', 'Open Funded No Trade Zone');

    const currentPath = window.location.pathname.replace(/\/$/, '');
    const targetPath = new URL(noTradeUrl, window.location.origin)
        .pathname
        .replace(/\/$/, '');

    if (currentPath === targetPath || currentPath.startsWith(`${targetPath}/`)) {
        link.classList.add('active');
    }

    const icon = document.createElement('span');
    icon.className = 'nav-icon';
    icon.textContent = '⊘';

    const label = document.createElement('span');
    label.textContent = 'Funded No Trade Zone';
    link.append(icon, label);

    const fundedAdvisorLink = Array.from(nav.querySelectorAll('.nav-link'))
        .find((item) => item.textContent.includes('Funded Account Advisor'));
    const tradingRoutineLink = Array.from(nav.querySelectorAll('.nav-link'))
        .find((item) => item.textContent.includes('Trading Routine'));

    if (fundedAdvisorLink) {
        fundedAdvisorLink.insertAdjacentElement('afterend', link);
    } else if (tradingRoutineLink) {
        tradingRoutineLink.insertAdjacentElement('beforebegin', link);
    } else {
        sectionTitle.insertAdjacentElement('afterend', link);
    }
})();
