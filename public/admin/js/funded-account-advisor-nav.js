(() => {
    'use strict';

    const script = document.currentScript;
    const nav = document.querySelector('.sidebar-nav');
    const advisorUrl = script?.dataset.advisorUrl;

    if (!nav || !advisorUrl) {
        return;
    }

    const existingLinks = Array.from(nav.querySelectorAll('.nav-link'));
    if (existingLinks.some((link) => link.getAttribute('href') === advisorUrl)) {
        return;
    }

    let sectionTitle = Array.from(nav.querySelectorAll('.nav-section-title'))
        .find((title) => title.textContent.trim() === 'TRADING TOOLS');

    if (!sectionTitle) {
        sectionTitle = document.createElement('div');
        sectionTitle.className = 'nav-section-title';
        sectionTitle.textContent = 'TRADING TOOLS';

        const nextSection = Array.from(nav.querySelectorAll('.nav-section-title'))
            .find((title) => ['COPY TRADING', 'INVESTMENTS', 'AFFILIATES', 'FINANCE', 'SECURITY', 'SYSTEM']
                .includes(title.textContent.trim()));

        if (nextSection) {
            nextSection.insertAdjacentElement('beforebegin', sectionTitle);
        } else {
            nav.appendChild(sectionTitle);
        }
    }

    const link = document.createElement('a');
    link.href = advisorUrl;
    link.className = 'nav-link';
    link.setAttribute('aria-label', 'Open Funded Account Advisor');

    const currentPath = window.location.pathname.replace(/\/$/, '');
    const advisorPath = new URL(advisorUrl, window.location.origin)
        .pathname
        .replace(/\/$/, '');

    if (currentPath === advisorPath || currentPath.startsWith(`${advisorPath}/`)) {
        link.classList.add('active');
    }

    const icon = document.createElement('span');
    icon.className = 'nav-icon';
    icon.textContent = '◇';

    const label = document.createElement('span');
    label.textContent = 'Funded Account Advisor';
    link.append(icon, label);

    const riskRewardLink = Array.from(nav.querySelectorAll('.nav-link'))
        .find((item) => item.textContent.includes('Risk Reward Calculator'));

    if (riskRewardLink) {
        riskRewardLink.insertAdjacentElement('afterend', link);
    } else {
        sectionTitle.insertAdjacentElement('afterend', link);
    }
})();
