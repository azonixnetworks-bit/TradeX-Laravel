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

        if (! sidebar || ! toggle) {
            return;
        }

        const mobileQuery =
            window.matchMedia(
                '(max-width: 1000px)'
            );

        const desktopStorageKey =
            'tradex.admin.sidebar.desktop.collapsed.v1';

        const sectionStoragePrefix =
            'tradex.admin.sidebar.section.v1.';

        const safeGet =
            function (
                storage,
                key
            ) {
                try {
                    return storage.getItem(
                        key
                    );
                } catch (error) {
                    return null;
                }
            };

        const safeSet =
            function (
                storage,
                key,
                value
            ) {
                try {
                    storage.setItem(
                        key,
                        value
                    );
                } catch (error) {
                    // Storage may be unavailable in restricted browser modes.
                }
            };

        const updateDesktopToggle =
            function () {
                if (mobileQuery.matches) {
                    return;
                }

                const collapsed =
                    document.body.classList.contains(
                        'sidebar-collapsed'
                    );

                toggle.setAttribute(
                    'aria-expanded',
                    collapsed
                        ? 'false'
                        : 'true'
                );

                toggle.setAttribute(
                    'aria-label',
                    collapsed
                        ? 'Show navigation'
                        : 'Hide navigation'
                );

                toggle.setAttribute(
                    'title',
                    collapsed
                        ? 'Show navigation'
                        : 'Hide navigation'
                );
            };

        const setDesktopCollapsed =
            function (
                collapsed,
                persist = true
            ) {
                if (mobileQuery.matches) {
                    document.body.classList.remove(
                        'sidebar-collapsed'
                    );

                    return;
                }

                document.body.classList.toggle(
                    'sidebar-collapsed',
                    Boolean(collapsed)
                );

                if (persist) {
                    safeSet(
                        window.localStorage,
                        desktopStorageKey,
                        collapsed
                            ? '1'
                            : '0'
                    );
                }

                updateDesktopToggle();
            };

        const restoreDesktopState =
            function () {
                if (mobileQuery.matches) {
                    document.body.classList.remove(
                        'sidebar-collapsed'
                    );

                    return;
                }

                setDesktopCollapsed(
                    safeGet(
                        window.localStorage,
                        desktopStorageKey
                    ) === '1',
                    false
                );
            };

        toggle.addEventListener(
            'click',
            function () {
                if (mobileQuery.matches) {
                    return;
                }

                setDesktopCollapsed(
                    ! document.body.classList.contains(
                        'sidebar-collapsed'
                    )
                );
            }
        );

        const nav =
            sidebar.querySelector(
                '.sidebar-nav'
            );

        if (nav) {
            const titles =
                Array.from(
                    nav.querySelectorAll(
                        ':scope > .nav-section-title'
                    )
                );

            titles.forEach(
                function (
                    title,
                    index
                ) {
                    const rawKey =
                        String(
                            title.textContent || ''
                        )
                            .trim()
                            .toLowerCase()
                            .replace(
                                /[^a-z0-9]+/g,
                                '-'
                            )
                            .replace(
                                /^-+|-+$/g,
                                ''
                            );

                    const key =
                        rawKey !== ''
                            ? rawKey
                            : `section-${index + 1}`;

                    const items = [];

                    let cursor =
                        title.nextElementSibling;

                    while (
                        cursor
                        && ! cursor.classList.contains(
                            'nav-section-title'
                        )
                    ) {
                        items.push(
                            cursor
                        );

                        cursor =
                            cursor.nextElementSibling;
                    }

                    if (items.length === 0) {
                        return;
                    }

                    title.classList.add(
                        'sidebar-section-toggle'
                    );

                    title.setAttribute(
                        'role',
                        'button'
                    );

                    title.setAttribute(
                        'tabindex',
                        '0'
                    );

                    const chevron =
                        document.createElement(
                            'span'
                        );

                    chevron.className =
                        'sidebar-section-chevron';

                    chevron.setAttribute(
                        'aria-hidden',
                        'true'
                    );

                    chevron.textContent =
                        '⌄';

                    title.appendChild(
                        chevron
                    );

                    const containsActive =
                        items.some(
                            function (item) {
                                return item.matches(
                                    '.nav-link.active'
                                ) || Boolean(
                                    item.querySelector?.(
                                        '.nav-link.active'
                                    )
                                );
                            }
                        );

                    const stored =
                        safeGet(
                            window.localStorage,
                            sectionStoragePrefix
                            + key
                        );

                    const initiallyExpanded =
                        containsActive
                        || stored === '1';

                    const setExpanded =
                        function (
                            expanded,
                            persist = true
                        ) {
                            const shouldExpand =
                                Boolean(
                                    expanded
                                );

                            title.setAttribute(
                                'aria-expanded',
                                shouldExpand
                                    ? 'true'
                                    : 'false'
                            );

                            items.forEach(
                                function (item) {
                                    item.hidden =
                                        ! shouldExpand;
                                }
                            );

                            if (persist) {
                                safeSet(
                                    window.localStorage,
                                    sectionStoragePrefix
                                    + key,
                                    shouldExpand
                                        ? '1'
                                        : '0'
                                );
                            }
                        };

                    const toggleSection =
                        function () {
                            setExpanded(
                                title.getAttribute(
                                    'aria-expanded'
                                ) !== 'true'
                            );
                        };

                    setExpanded(
                        initiallyExpanded,
                        false
                    );

                    title.addEventListener(
                        'click',
                        toggleSection
                    );

                    title.addEventListener(
                        'keydown',
                        function (event) {
                            if (
                                event.key === 'Enter'
                                || event.key === ' '
                            ) {
                                event.preventDefault();
                                toggleSection();
                            }
                        }
                    );
                }
            );

            window.requestAnimationFrame(
                function () {
                    const active =
                        nav.querySelector(
                            '.nav-link.active'
                        );

                    active?.scrollIntoView({
                        block: 'nearest',
                        inline: 'nearest',
                    });
                }
            );
        }

        const handleViewportChange =
            function () {
                restoreDesktopState();

                if (! mobileQuery.matches) {
                    updateDesktopToggle();
                }
            };

        if (
            typeof mobileQuery.addEventListener
            === 'function'
        ) {
            mobileQuery.addEventListener(
                'change',
                handleViewportChange
            );
        } else if (
            typeof mobileQuery.addListener
            === 'function'
        ) {
            mobileQuery.addListener(
                handleViewportChange
            );
        }

        restoreDesktopState();
        updateDesktopToggle();
    }
);
