(() => {
    'use strict';

    const STORAGE_KEY = 'tradex.admin.theme';
    const root = document.documentElement;
    const toggle = document.getElementById('adminThemeToggle');
    const icon = toggle?.querySelector('[data-theme-icon]') ?? null;
    const state = toggle?.querySelector('[data-theme-state]') ?? null;
    const metaTheme = document.querySelector('meta[name="theme-color"]');

    const normalizeTheme = (value) => value === 'light' ? 'light' : 'dark';

    const currentTheme = () => normalizeTheme(root.dataset.theme);

    const updateToggle = (theme) => {
        if (!toggle) {
            return;
        }

        const isLight = theme === 'light';
        const nextLabel = isLight
            ? 'Switch to dark theme'
            : 'Switch to light theme';

        toggle.setAttribute('aria-label', nextLabel);
        toggle.setAttribute('title', nextLabel);
        toggle.setAttribute('aria-pressed', isLight ? 'true' : 'false');

        if (icon) {
            icon.textContent = isLight ? '☾' : '☀';
        }

        if (state) {
            state.textContent = isLight ? 'Dark' : 'Light';
        }
    };

    const applyTheme = (theme, persist = false) => {
        const normalized = normalizeTheme(theme);

        root.dataset.theme = normalized;
        root.style.colorScheme = normalized;
        updateToggle(normalized);

        metaTheme?.setAttribute(
            'content',
            normalized === 'light' ? '#eef3f8' : '#03070d'
        );

        if (persist) {
            try {
                window.localStorage.setItem(STORAGE_KEY, normalized);
            } catch (_error) {
                // Theme still works for this page when storage is unavailable.
            }
        }

        window.dispatchEvent(new CustomEvent('tradex:theme-changed', {
            detail: { theme: normalized },
        }));
    };

    updateToggle(currentTheme());

    toggle?.addEventListener('click', () => {
        applyTheme(currentTheme() === 'dark' ? 'light' : 'dark', true);
    });

    window.addEventListener('storage', (event) => {
        if (event.key !== STORAGE_KEY) {
            return;
        }

        if (event.newValue === 'light' || event.newValue === 'dark') {
            applyTheme(event.newValue, false);
        }
    });

    const hoverCapable = window.matchMedia?.('(hover: hover) and (pointer: fine)')?.matches ?? false;
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false;

    if (!hoverCapable || reducedMotion) {
        return;
    }

    const interactiveSelector = [
        '.quick-card-link',
        '.nav-link',
        '.primary-btn',
        '.secondary-btn',
        '.page-button:not(.disabled)',
        '.theme-toggle',
        '.sidebar-logout',
        '.topbar-totp-link',
    ].join(',');

    document.addEventListener('pointermove', (event) => {
        if (!(event.target instanceof Element)) {
            return;
        }

        const target = event.target.closest(interactiveSelector);

        if (!(target instanceof HTMLElement)) {
            return;
        }

        const rect = target.getBoundingClientRect();

        if (rect.width <= 0 || rect.height <= 0) {
            return;
        }

        const x = ((event.clientX - rect.left) / rect.width) * 100;
        const y = ((event.clientY - rect.top) / rect.height) * 100;

        target.style.setProperty('--glass-x', `${Math.max(0, Math.min(100, x)).toFixed(2)}%`);
        target.style.setProperty('--glass-y', `${Math.max(0, Math.min(100, y)).toFixed(2)}%`);
    }, { passive: true });
})();
