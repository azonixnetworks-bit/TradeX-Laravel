(() => {
    'use strict';

    const app = document.getElementById('screenerMonitorApp');

    if (!app) {
        return;
    }

    const endpoint = app.dataset.healthEndpoint;

    if (!endpoint) {
        return;
    }

    const overall = document.getElementById('screenerOverallStatus');
    const generatedAt = document.getElementById('screenerGeneratedAt');
    const rows = document.getElementById('screenerProviderRows');

    const text = (value, fallback = '—') => {
        if (value === null || value === undefined || value === '') {
            return fallback;
        }

        return String(value);
    };

    const escapeHtml = (value) => text(value, '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');

    const statusClass = (value) => {
        const normalised = text(value, 'UNCONFIGURED')
            .toLowerCase()
            .replace(/[^a-z]/g, '');

        return `status-${normalised || 'unconfigured'}`;
    };

    const renderProviders = (providers) => {
        if (!rows || !Array.isArray(providers)) {
            return;
        }

        rows.innerHTML = providers.map((provider) => {
            const error = provider.last_error_code
                ? `<small>${escapeHtml(provider.last_error_code)}</small>`
                : '';

            const displayRights = Object.prototype.hasOwnProperty.call(
                provider,
                'external_display_approved'
            )
                ? `<small>Display rights: ${provider.external_display_approved ? 'APPROVED' : 'NOT APPROVED'}</small>`
                : '';

            const latency = provider.last_latency_ms === null
                || provider.last_latency_ms === undefined
                ? '—'
                : `${escapeHtml(provider.last_latency_ms)} ms`;

            const cache = provider.cache || {};

            return `
                <tr data-market="${escapeHtml(provider.market)}">
                    <td>
                        <strong>${escapeHtml(provider.market_label)}</strong>
                        <small>${escapeHtml(provider.market_status)} · ${escapeHtml(provider.symbol_count)} symbols</small>
                    </td>
                    <td>${escapeHtml(provider.provider)}</td>
                    <td>
                        <span class="screener-status ${statusClass(provider.status)}">${escapeHtml(provider.status)}</span>
                        ${error}
                    </td>
                    <td>
                        ${provider.configured ? 'YES' : 'NO'}
                        ${displayRights}
                    </td>
                    <td>${escapeHtml(text(provider.last_success_at_utc, 'Never'))}</td>
                    <td>${latency}</td>
                    <td>
                        ${escapeHtml(text(cache.fresh_datasets_cached, '0'))} fresh
                        <small>${escapeHtml(text(cache.fallback_datasets_cached, '0'))} fallback</small>
                    </td>
                    <td>${escapeHtml(text(provider.base_host))}</td>
                </tr>
            `;
        }).join('');
    };

    const refresh = async () => {
        try {
            const response = await fetch(endpoint, {
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();
            const data = payload && payload.data;

            if (!data) {
                return;
            }

            if (overall) {
                overall.textContent = text(data.overall_status, 'UNCONFIGURED');
                overall.className = `screener-overall-badge ${statusClass(data.overall_status)}`;
            }

            if (generatedAt) {
                generatedAt.textContent = text(data.generated_at_utc);
            }

            renderProviders(data.providers);
        } catch (_) {
            // Keep the last rendered server snapshot when the refresh request fails.
        }
    };

    window.setInterval(refresh, 30000);
})();
