(() => {
    'use strict';

    const clock = document.querySelector('[data-system-clock]');

    if (!clock) {
        return;
    }

    const start = Date.parse(clock.dataset.start || '');
    const timezone = clock.dataset.timezone || 'UTC';

    if (Number.isNaN(start)) {
        return;
    }

    const serverOffset = start - Date.now();

    const formatter = (() => {
        try {
            return new Intl.DateTimeFormat('en-GB', {
                timeZone: timezone,
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false,
            });
        } catch (_error) {
            return null;
        }
    })();

    const render = () => {
        const current = new Date(Date.now() + serverOffset);

        clock.textContent = formatter
            ? formatter.format(current).replace(',', '')
            : current.toISOString().replace('T', ' ').slice(0, 19);
    };

    render();
    window.setInterval(render, 1000);
})();
