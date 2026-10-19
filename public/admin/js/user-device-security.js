(function () {
    'use strict';

    /*
    |--------------------------------------------------------------------------
    | User Device Revoke Modal
    |--------------------------------------------------------------------------
    |
    | The admin shell keeps this listener loaded for the lifetime of the page.
    | User-detail content can be replaced by in-place navigation, so binding
    | directly to the buttons on DOMContentLoaded would leave a new detail
    | screen without its revoke handler and could expose a GET navigation to a
    | PATCH-only endpoint. Event delegation keeps mobile and desktop actions
    | on the audited, CSRF-protected form path.
    |
    */

    function openModal(modal, focusElement) {
        if (!modal) {
            return;
        }

        modal.hidden = false;
        document.body.classList.add('modal-open');

        if (focusElement) {
            window.setTimeout(function () {
                focusElement.focus();
            }, 100);
        }
    }

    function closeModal(modal) {
        if (!modal) {
            return;
        }

        modal.hidden = true;

        const hasOpenModal = Array
            .from(document.querySelectorAll('.status-modal-overlay'))
            .some(function (candidate) {
                return !candidate.hidden;
            });

        if (!hasOpenModal) {
            document.body.classList.remove('modal-open');
        }
    }

    function openDeviceRevokeModal(trigger) {
        const modal = document.getElementById('deviceRevokeModal');
        const form = document.getElementById('deviceRevokeForm');
        const name = document.getElementById('deviceRevokeName');
        const reason = document.getElementById('deviceRevokeReason');
        const actionUrl = trigger.dataset.actionUrl;

        if (!modal || !form || !name || !actionUrl) {
            return;
        }

        form.reset();
        form.action = actionUrl;
        name.textContent = trigger.dataset.deviceName || 'this device';

        openModal(modal, reason);
    }

    document.addEventListener('click', function (event) {
        const revokeTrigger = event.target.closest('[data-device-revoke]');

        if (revokeTrigger) {
            event.preventDefault();
            openDeviceRevokeModal(revokeTrigger);
            return;
        }

        if (event.target.closest('#deviceRevokeClose, #deviceRevokeCancel')) {
            closeModal(document.getElementById('deviceRevokeModal'));
            return;
        }

        const modal = event.target.closest('.status-modal-overlay');

        if (modal && event.target === modal) {
            closeModal(modal);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeModal(document.getElementById('deviceRevokeModal'));
        }
    });
}());
