(() => {
    'use strict';

    const script = document.currentScript;
    const nav = document.querySelector('.sidebar-nav');
    const routineUrl = script?.dataset.routineUrl;

    if (!nav || !routineUrl) {
        return;
    }

    const existingLinks = Array.from(nav.querySelectorAll('.nav-link'));
    if (existingLinks.some((link) => link.getAttribute('href') === routineUrl)) {
        return;
    }

    let sectionTitle = Array.from(nav.querySelectorAll('.nav-section-title'))
        .find((title) => title.textContent.trim() === 'TRADING TOOLS');

    if (!sectionTitle) {
        sectionTitle = document.createElement('div');
        sectionTitle.className = 'nav-section-title';
        sectionTitle.textContent = 'TRADING TOOLS';
        nav.appendChild(sectionTitle);
    }

    const link = document.createElement('a');
    link.href = routineUrl;
    link.className = 'nav-link';
    link.setAttribute('aria-label', 'Open Trading Routine Planner');

    const currentPath = window.location.pathname.replace(/\/$/, '');
    const routinePath = new URL(routineUrl, window.location.origin)
        .pathname
        .replace(/\/$/, '');

    if (currentPath === routinePath || currentPath.startsWith(`${routinePath}/`)) {
        link.classList.add('active');
    }

    const icon = document.createElement('span');
    icon.className = 'nav-icon';
    icon.textContent = '◷';

    const label = document.createElement('span');
    label.textContent = 'Trading Routine';
    link.append(icon, label);

    const fundedLink = Array.from(nav.querySelectorAll('.nav-link'))
        .find((item) => item.textContent.includes('Funded Account Advisor'));

    if (fundedLink) {
        fundedLink.insertAdjacentElement('afterend', link);
    } else {
        sectionTitle.insertAdjacentElement('afterend', link);
    }
})();
