(() => {
    'use strict';

    const hideLoader = () => {
        const loader = document.getElementById('tradexLoader');

        if (! loader) {
            return;
        }

        loader.classList.add('is-hidden');
        loader.setAttribute('aria-hidden', 'true');

        window.setTimeout(() => {
            loader.remove();
        }, 320);
    };

    if (document.readyState === 'complete') {
        hideLoader();
        return;
    }

    window.addEventListener('load', hideLoader, {
        once: true,
    });

    // Fail-safe: branding must never block admin access if a resource stalls.
    window.setTimeout(hideLoader, 2200);
})();
