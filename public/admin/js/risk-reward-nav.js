(() => {
    'use strict';

    const script = document.currentScript;

    if (!script) {
        return;
    }

    const calculatorUrl = script.dataset.calculatorUrl;
    const nav = document.querySelector('.sidebar-nav');

    if (!calculatorUrl || !nav) {
        return;
    }

    const links = Array.from(nav.querySelectorAll('.nav-link'));

    if (links.some((link) => link.getAttribute('href') === calculatorUrl)) {
        return;
    }

    let sectionTitle = Array.from(
        nav.querySelectorAll('.nav-section-title')
    ).find((title) => title.textContent.trim() === 'TRADING TOOLS');

    const link = document.createElement('a');
    link.href = calculatorUrl;
    link.className = 'nav-link';
    link.setAttribute('aria-label', 'Open Risk Reward Calculator');

    const currentPath = window.location.pathname.replace(/\/$/, '');
    const calculatorPath = new URL(calculatorUrl, window.location.origin)
        .pathname
        .replace(/\/$/, '');

    if (
        currentPath === calculatorPath
        || currentPath.startsWith(`${calculatorPath}/`)
    ) {
        link.classList.add('active');
    }

    const icon = document.createElement('span');
    icon.className = 'nav-icon';
    icon.textContent = '◎';

    const label = document.createElement('span');
    label.textContent = 'Risk Reward Calculator';

    link.append(icon, label);

    if (!sectionTitle) {
        sectionTitle = document.createElement('div');
        sectionTitle.className = 'nav-section-title';
        sectionTitle.textContent = 'TRADING TOOLS';

        const preferredNextSection = Array.from(
            nav.querySelectorAll('.nav-section-title')
        ).find((title) => ['COPY TRADING', 'INVESTMENTS', 'AFFILIATES', 'FINANCE', 'SECURITY', 'SYSTEM']
            .includes(title.textContent.trim()));

        if (preferredNextSection) {
            preferredNextSection.insertAdjacentElement('beforebegin', sectionTitle);
        } else {
            nav.appendChild(sectionTitle);
        }
    }

    sectionTitle.insertAdjacentElement('afterend', link);
})();
