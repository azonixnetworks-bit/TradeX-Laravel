(() => {
    'use strict';

    const STORAGE_KEY = 'tradex.admin.theme';
    const root = document.documentElement;
    let theme = 'dark';

    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);

        if (stored === 'light' || stored === 'dark') {
            theme = stored;
        }
    } catch (_error) {
        // Storage can be unavailable in hardened/private browser contexts.
        // Dark remains the safe default so the existing TradeX UI is preserved.
    }

    root.dataset.theme = theme;
    root.style.colorScheme = theme;

    const metaTheme = document.querySelector('meta[name="theme-color"]');

    if (metaTheme) {
        metaTheme.setAttribute('content', theme === 'light' ? '#eef3f8' : '#03070d');
    }
})();
