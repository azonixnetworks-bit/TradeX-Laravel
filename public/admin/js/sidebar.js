document.addEventListener(
    'DOMContentLoaded',
    function () {
        const sidebar =
            document.getElementById(
                'adminSidebar'
            );

        const toggle =
            document.getElementById(
                'sidebarToggle'
            );

        const backdrop =
            document.getElementById(
                'sidebarBackdrop'
            );

        if (! sidebar || ! toggle || ! backdrop) {
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
                const link =
                    event.target.closest(
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
