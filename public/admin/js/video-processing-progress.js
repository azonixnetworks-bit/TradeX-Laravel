(() => {
    const cards = [...document.querySelectorAll('[data-video-processing]')];
    if (!cards.length) return;

    const render = (card, data) => {
        const progress = Math.max(0, Math.min(100, Number(data.progress) || 0));
        card.querySelector('[data-processing-fill]').style.width = `${progress}%`;
        card.querySelector('[data-processing-percent]').textContent = `${progress}%`;
        card.querySelector('[role="progressbar"]').setAttribute('aria-valuenow', progress);
        card.querySelector('[data-processing-stage]').textContent = data.stage || 'Processing status is being updated.';
        card.querySelector('[data-processing-label]').textContent = data.complete ? 'Successfully completed' : data.status;
        const error = card.querySelector('[data-processing-error]');
        error.textContent = data.error || '';
        error.hidden = !data.error;
        card.classList.remove('is-pending', 'is-processing', 'is-ready', 'is-failed');
        card.classList.add(`is-${String(data.status).toLowerCase()}`);
        card.dataset.terminal = data.complete || data.failed ? 'true' : 'false';
    };

    const poll = async (card) => {
        if (card.dataset.terminal === 'true') return;
        try {
            const response = await fetch(card.dataset.statusUrl, {headers: {'Accept': 'application/json'}, credentials: 'same-origin'});
            if (response.ok) render(card, await response.json());
        } catch (_) {}
    };

    cards.forEach(poll);
    const timer = window.setInterval(() => {
        const active = cards.filter(card => card.dataset.terminal !== 'true');
        active.forEach(poll);
        if (!active.length) window.clearInterval(timer);
    }, 3000);
})();
