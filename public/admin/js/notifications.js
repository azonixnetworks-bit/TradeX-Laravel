(() => {
    'use strict';

    function dismiss(toast) {
        if (! toast || toast.classList.contains('is-leaving')) {
            return;
        }

        toast.classList.add('is-leaving');

        window.setTimeout(() => {
            toast.remove();
        }, 240);
    }

    function wireToast(toast) {
        const closeButton = toast.querySelector('[data-admin-toast-close]');
        const timeout = Number.parseInt(
            toast.dataset.timeout || '0',
            10
        );

        if (Number.isFinite(timeout) && timeout > 0) {
            toast.style.setProperty(
                '--admin-toast-timeout',
                `${timeout}ms`
            );

            window.setTimeout(() => {
                dismiss(toast);
            }, timeout);
        }

        closeButton?.addEventListener('click', () => {
            dismiss(toast);
        });
    }

    document.addEventListener('DOMContentLoaded', () => {
        document
            .querySelectorAll('[data-admin-toast]')
            .forEach(wireToast);
    });
})();
