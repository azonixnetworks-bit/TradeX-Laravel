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

        if (!sidebar || !toggle) {
            return;
        }

        toggle.addEventListener(
            'click',
            function () {

                sidebar.classList.toggle(
                    'open'
                );

            }
        );

    }
);