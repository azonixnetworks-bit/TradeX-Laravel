document.addEventListener(
    'DOMContentLoaded',
    function () {
        const sidebar =
            document.getElementById(
                'adminSidebar'
            );

        if (! sidebar) {
            return;
        }

        const nav =
            sidebar.querySelector(
                '.sidebar-nav'
            );

        const scrollStorageKey =
            'tradex.admin.sidebar.scroll.v1';

        const readStoredScroll =
            function () {
                try {
                    const stored =
                        window.sessionStorage.getItem(
                            scrollStorageKey
                        );

                    if (stored === null) {
                        return null;
                    }

                    const parsed =
                        Number.parseInt(
                            stored,
                            10
                        );

                    if (
                        ! Number.isFinite(parsed)
                        || parsed < 0
                    ) {
                        return null;
                    }

                    return parsed;
                } catch (error) {
                    return null;
                }
            };

        const persistScroll =
            function () {
                if (! nav) {
                    return;
                }

                try {
                    window.sessionStorage.setItem(
                        scrollStorageKey,
                        String(
                            Math.max(
                                0,
                                Math.round(
                                    nav.scrollTop
                                )
                            )
                        )
                    );
                } catch (error) {
                    // Storage can be unavailable in restricted browser modes.
                }
            };

        const activeLinkIsVisible =
            function (activeLink) {
                if (! nav || ! activeLink) {
                    return false;
                }

                const navRect =
                    nav.getBoundingClientRect();

                const linkRect =
                    activeLink.getBoundingClientRect();

                return linkRect.top >= navRect.top
                    && linkRect.bottom <= navRect.bottom;
            };

        const restoreSidebarScroll =
            function () {
                if (! nav) {
                    return;
                }

                const storedScroll =
                    readStoredScroll();

                const applyStoredScroll =
                    function () {
                        if (storedScroll === null) {
                            return false;
                        }

                        const maximumScroll =
                            Math.max(
                                0,
                                nav.scrollHeight
                                - nav.clientHeight
                            );

                        nav.scrollTop =
                            Math.min(
                                storedScroll,
                                maximumScroll
                            );

                        return true;
                    };

                if (applyStoredScroll()) {
                    window.requestAnimationFrame(
                        applyStoredScroll
                    );

                    return;
                }

                window.requestAnimationFrame(
                    function () {
                        const activeLink =
                            nav.querySelector(
                                '.nav-link.active'
                            );

                        if (
                            activeLink
                            && ! activeLinkIsVisible(
                                activeLink
                            )
                        ) {
                            activeLink.scrollIntoView({
                                block: 'nearest',
                                inline: 'nearest',
                            });
                        }
                    }
                );
            };

        restoreSidebarScroll();

        if (nav) {
            let scrollFrame = null;

            nav.addEventListener(
                'scroll',
                function () {
                    if (scrollFrame !== null) {
                        return;
                    }

                    scrollFrame =
                        window.requestAnimationFrame(
                            function () {
                                scrollFrame = null;
                                persistScroll();
                            }
                        );
                },
                {
                    passive: true,
                }
            );

            sidebar.addEventListener(
                'click',
                function (event) {
                    const target =
                        event.target instanceof Element
                            ? event.target
                            : null;

                    if (
                        target?.closest(
                            '.nav-link'
                        )
                    ) {
                        persistScroll();
                    }
                },
                true
            );

            window.addEventListener(
                'pagehide',
                persistScroll
            );

            document.addEventListener(
                'visibilitychange',
                function () {
                    if (
                        document.visibilityState
                        === 'hidden'
                    ) {
                        persistScroll();
                    }
                }
            );
        }

        const toggle =
            document.getElementById(
                'sidebarToggle'
            );

        const backdrop =
            document.getElementById(
                'sidebarBackdrop'
            );

        if (! toggle || ! backdrop) {
            return;
        }

        const mobileQuery =
            window.matchMedia(
                '(max-width: 1000px)'
            );

        let restoreFocus = null;

        const brand =
            sidebar.querySelector(
                '.sidebar-brand'
            );

        let closeButton =
            sidebar.querySelector(
                '.sidebar-close'
            );

        if (! closeButton && brand) {
            closeButton =
                document.createElement(
                    'button'
                );

            closeButton.type =
                'button';

            closeButton.className =
                'sidebar-close';

            closeButton.setAttribute(
                'aria-label',
                'Close navigation'
            );

            closeButton.innerHTML =
                '&times;';

            brand.appendChild(
                closeButton
            );
        }

        const setOpen =
            function (
                open,
                options = {}
            ) {
                const shouldOpen =
                    Boolean(open)
                    && mobileQuery.matches;

                sidebar.classList.toggle(
                    'open',
                    shouldOpen
                );

                document.body.classList.toggle(
                    'sidebar-open',
                    shouldOpen
                );

                toggle.setAttribute(
                    'aria-expanded',
                    shouldOpen
                        ? 'true'
                        : 'false'
                );

                toggle.setAttribute(
                    'aria-label',
                    shouldOpen
                        ? 'Close navigation'
                        : 'Open navigation'
                );

                backdrop.setAttribute(
                    'aria-hidden',
                    shouldOpen
                        ? 'false'
                        : 'true'
                );

                if (shouldOpen) {
                    restoreFocus =
                        document.activeElement;

                    window.setTimeout(
                        function () {
                            const activeLink =
                                sidebar.querySelector(
                                    '.nav-link.active'
                                );

                            const firstLink =
                                sidebar.querySelector(
                                    '.nav-link'
                                );

                            const focusTarget =
                                closeButton
                                || activeLink
                                || firstLink;

                            focusTarget?.focus();
                        },
                        30
                    );

                    return;
                }

                if (
                    options.restoreFocus !== false
                    && restoreFocus
                    && typeof restoreFocus.focus === 'function'
                ) {
                    restoreFocus.focus();
                }

                restoreFocus = null;
            };

        toggle.addEventListener(
            'click',
            function () {
                setOpen(
                    ! sidebar.classList.contains(
                        'open'
                    )
                );
            }
        );

        closeButton?.addEventListener(
            'click',
            function () {
                setOpen(false);
            }
        );

        backdrop.addEventListener(
            'click',
            function () {
                setOpen(false);
            }
        );

        sidebar.addEventListener(
            'click',
            function (event) {
                const target =
                    event.target instanceof Element
                        ? event.target
                        : null;

                const link =
                    target?.closest(
                        '.nav-link'
                    );

                if (
                    link
                    && mobileQuery.matches
                ) {
                    setOpen(
                        false,
                        {
                            restoreFocus: false,
                        }
                    );
                }
            }
        );

        document.addEventListener(
            'keydown',
            function (event) {
                if (
                    event.key === 'Escape'
                    && sidebar.classList.contains(
                        'open'
                    )
                ) {
                    setOpen(false);
                }
            }
        );

        const resetForViewport =
            function () {
                if (! mobileQuery.matches) {
                    setOpen(
                        false,
                        {
                            restoreFocus: false,
                        }
                    );
                }
            };

        if (
            typeof mobileQuery.addEventListener
            === 'function'
        ) {
            mobileQuery.addEventListener(
                'change',
                resetForViewport
            );
        } else if (
            typeof mobileQuery.addListener
            === 'function'
        ) {
            mobileQuery.addListener(
                resetForViewport
            );
        }

        resetForViewport();
    }
);
