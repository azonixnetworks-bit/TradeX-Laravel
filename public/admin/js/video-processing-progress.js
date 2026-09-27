(() => {
    const cards = [...document.querySelectorAll('[data-video-processing]')];
    if (!cards.length) return;

    const formatDuration = (seconds) => {
        const value = Number(seconds);
        if (!Number.isFinite(value) || value < 0) return '—';

        const total = Math.floor(value);
        const hours = Math.floor(total / 3600);
        const minutes = Math.floor((total % 3600) / 60);
        const secs = total % 60;

        if (hours > 0) {
            return `${hours}h ${String(minutes).padStart(2, '0')}m ${String(secs).padStart(2, '0')}s`;
        }

        return `${minutes}m ${String(secs).padStart(2, '0')}s`;
    };

    const formatPosition = (position, duration) => {
        const current = Number(position);
        const total = Number(duration);

        if (!Number.isFinite(current)) return '—';

        const currentLabel = formatDuration(current);

        return Number.isFinite(total) && total > 0
            ? `${currentLabel} / ${formatDuration(total)}`
            : currentLabel;
    };

    const setText = (card, selector, value) => {
        const element = card.querySelector(selector);
        if (element) element.textContent = value ?? '—';
    };

    const render = (card, data) => {
        const progress = Math.max(0, Math.min(100, Number(data.progress) || 0));
        const fill = card.querySelector('[data-processing-fill]');
        const percent = card.querySelector('[data-processing-percent]');
        const bar = card.querySelector('[role="progressbar"]');

        if (fill) fill.style.width = `${progress}%`;
        if (percent) percent.textContent = `${progress}%`;
        if (bar) bar.setAttribute('aria-valuenow', progress);

        setText(
            card,
            '[data-processing-stage]',
            data.stage || 'Processing status is being updated.'
        );

        setText(
            card,
            '[data-processing-label]',
            data.repair_required
                ? 'HLS repair required'
                : (data.complete ? 'Successfully completed' : data.status)
        );

        const detail = data.detail && typeof data.detail === 'object'
            ? data.detail
            : {};

        setText(card, '[data-processing-file]', detail.source_file || '—');

        const renditionParts = [];
        if (detail.rendition) renditionParts.push(String(detail.rendition));
        if (detail.rendition_index && detail.rendition_total) {
            renditionParts.push(`${detail.rendition_index}/${detail.rendition_total}`);
        }

        setText(
            card,
            '[data-processing-rendition]',
            renditionParts.length ? renditionParts.join(' · ') : '—'
        );

        setText(
            card,
            '[data-processing-rendition-percent]',
            detail.rendition_percent !== null
                && detail.rendition_percent !== undefined
                    ? `${detail.rendition_percent}%`
                    : '—'
        );

        setText(
            card,
            '[data-processing-position]',
            formatPosition(
                detail.video_position_seconds,
                detail.duration_seconds
            )
        );

        setText(
            card,
            '[data-processing-speed]',
            detail.speed || '—'
        );

        setText(
            card,
            '[data-processing-elapsed]',
            formatDuration(detail.elapsed_seconds)
        );

        setText(
            card,
            '[data-processing-remaining]',
            formatDuration(detail.estimated_remaining_seconds)
        );

        let heartbeatLabel = 'Waiting to start';

        if (data.heartbeat_at) {
            const heartbeat = new Date(data.heartbeat_at);
            if (!Number.isNaN(heartbeat.getTime())) {
                const secondsAgo = Math.max(
                    0,
                    Math.floor((Date.now() - heartbeat.getTime()) / 1000)
                );

                heartbeatLabel = secondsAgo < 5
                    ? 'Just now'
                    : `${secondsAgo}s ago`;
            }
        }

        setText(
            card,
            '[data-processing-heartbeat]',
            heartbeatLabel
        );

        const stalled = card.querySelector('[data-processing-stalled]');
        if (stalled) stalled.hidden = !data.stalled;

        const error = card.querySelector('[data-processing-error]');
        if (error) {
            error.textContent = data.error || '';
            error.hidden = !data.error;
        }

        card.classList.remove(
            'is-pending',
            'is-processing',
            'is-ready',
            'is-failed',
            'is-stalled'
        );

        card.classList.add(
            data.repair_required
                ? 'is-failed'
                : `is-${String(data.status).toLowerCase()}`
        );

        if (data.stalled) {
            card.classList.add('is-stalled');
        }

        card.dataset.terminal =
            data.terminal
            || data.complete
            || data.failed
            || data.repair_required
                ? 'true'
                : 'false';
    };

    const poll = async (card) => {
        if (card.dataset.terminal === 'true') return;

        try {
            const response = await fetch(
                card.dataset.statusUrl,
                {
                    headers: {
                        'Accept': 'application/json',
                        'Cache-Control': 'no-cache',
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                }
            );

            if (response.ok) {
                render(card, await response.json());
            }
        } catch (_) {
            // Keep the previous telemetry visible during a temporary poll failure.
        }
    };

    cards.forEach(poll);

    const timer = window.setInterval(() => {
        const active = cards.filter(
            card => card.dataset.terminal !== 'true'
        );

        active.forEach(poll);

        if (!active.length) {
            window.clearInterval(timer);
        }
    }, 2500);
})();
