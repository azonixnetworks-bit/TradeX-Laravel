(function () {
    'use strict';

    let activeRequest = null;

    function contentRoot() {
        return document.querySelector('main.admin-content');
    }

    function isAdminUrl(url) {
        return url.origin === window.location.origin
            && url.pathname.startsWith('/admin');
    }

    function isFilterForm(form) {
        return form instanceof HTMLFormElement
            && form.method.toLowerCase() === 'get'
            && !form.target
            && !form.hasAttribute('data-no-in-place-navigation')
            && !form.matches('[data-trade-idea-filter-form]')
            && !!form.closest('main.admin-content');
    }

    function isInPlaceLink(link) {
        if (!(link instanceof HTMLAnchorElement)
            || link.target
            || link.hasAttribute('download')
            || link.closest('.trade-market-filter-panel')) {
            return false;
        }

        return link.matches(
            '.admin-pagination a, .pagination a, nav[role="navigation"] a, '
            + '.dashboard-activity-tabs a, [data-admin-in-place-link]'
        );
    }

    async function refreshContent(url, pushState) {
        const current = contentRoot();

        if (!current || !isAdminUrl(url)) {
            window.location.assign(url);
            return;
        }

        if (activeRequest) {
            activeRequest.abort();
        }

        const request = new AbortController();
        const scroll = {x: window.scrollX, y: window.scrollY};
        activeRequest = request;
        current.classList.add('is-in-place-loading');
        current.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url, {
                credentials: 'same-origin',
                headers: {'X-Requested-With': 'XMLHttpRequest'},
                signal: request.signal,
            });

            if (!response.ok) {
                throw new Error('Unable to load the requested admin content.');
            }

            const page = new DOMParser().parseFromString(
                await response.text(),
                'text/html'
            );
            const replacement = page.querySelector('main.admin-content');

            if (!replacement) {
                throw new Error('The updated admin content was unavailable.');
            }

            current.innerHTML = replacement.innerHTML;
            document.title = page.title || document.title;

            if (pushState) {
                window.history.pushState({}, '', url);
            }

            window.scrollTo(scroll.x, scroll.y);
        } catch (error) {
            if (error.name !== 'AbortError') {
                window.location.assign(url);
            }
        } finally {
            if (activeRequest === request) {
                activeRequest = null;
                const root = contentRoot();
                if (root) {
                    root.classList.remove('is-in-place-loading');
                    root.removeAttribute('aria-busy');
                }
            }
        }
    }

    document.addEventListener('submit', function (event) {
        const form = event.target;

        if (!isFilterForm(form)) {
            return;
        }

        event.preventDefault();
        const url = new URL(form.action || window.location.href, window.location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        refreshContent(url.toString(), true);
    });

    document.addEventListener('click', function (event) {
        const link = event.target.closest('a');

        if (!isInPlaceLink(link)
            || event.button !== 0
            || event.metaKey
            || event.ctrlKey
            || event.shiftKey
            || event.altKey) {
            return;
        }

        event.preventDefault();
        refreshContent(link.href, true);
    });

    window.addEventListener('popstate', function () {
        refreshContent(window.location.href, false);
    });
}());
