(() => {
    'use strict';

    const root = document.getElementById('liveScreenerApp');
    const configElement = document.getElementById('liveScreenerConfig');

    if (!root || !configElement) {
        return;
    }

    let config;

    try {
        config = JSON.parse(configElement.textContent || '{}');
    } catch (_error) {
        return;
    }

    const endpoint = root.dataset.endpoint;

    if (!endpoint) {
        return;
    }

    const elements = {
        marketTabs: document.getElementById('liveScreenerMarketTabs'),
        timeframes: document.getElementById('liveScreenerTimeframes'),
        regionField: document.getElementById('liveScreenerRegionField'),
        region: document.getElementById('liveScreenerRegion'),
        exchangeField: document.getElementById('liveScreenerExchangeField'),
        exchange: document.getElementById('liveScreenerExchange'),
        trend: document.getElementById('liveScreenerTrend'),
        alignment: document.getElementById('liveScreenerAlignment'),
        sort: document.getElementById('liveScreenerSort'),
        order: document.getElementById('liveScreenerOrder'),
        search: document.getElementById('liveScreenerSearch'),
        rsiMin: document.getElementById('liveScreenerRsiMin'),
        rsiMax: document.getElementById('liveScreenerRsiMax'),
        autoRefresh: document.getElementById('liveScreenerAutoRefresh'),
        refreshButton: document.getElementById('liveScreenerRefreshButton'),
        connectionBadge: document.getElementById('liveScreenerConnectionBadge'),
        provider: document.getElementById('liveScreenerProvider'),
        providerStatus: document.getElementById('liveScreenerProviderStatus'),
        count: document.getElementById('liveScreenerCount'),
        marketLabel: document.getElementById('liveScreenerMarketLabel'),
        cacheState: document.getElementById('liveScreenerCacheState'),
        generatedAt: document.getElementById('liveScreenerGeneratedAt'),
        refreshState: document.getElementById('liveScreenerRefreshState'),
        alert: document.getElementById('liveScreenerAlert'),
        loading: document.getElementById('liveScreenerLoading'),
        rows: document.getElementById('liveScreenerRows'),
        tableTitle: document.getElementById('liveScreenerTableTitle'),
    };

    const markets = Array.isArray(config.markets) ? config.markets : [];
    const timeframes = Array.isArray(config.timeframes) ? config.timeframes : [];
    const stockRegions = Array.isArray(config.stock_regions) ? config.stock_regions : [];

    const firstEnabledMarket = markets.find((market) => market.enabled) || markets[0];
    const firstTimeframe = timeframes.find((timeframe) => timeframe.key === 'H1') || timeframes[0];

    const state = {
        market: firstEnabledMarket?.key || 'FOREX',
        timeframe: firstTimeframe?.key || 'H1',
        region: 'INTERNATIONAL',
        exchange: '',
        rows: [],
        request: null,
        timer: null,
    };

    const text = (value, fallback = '—') => {
        if (value === null || value === undefined || value === '') {
            return fallback;
        }

        return String(value);
    };

    const number = (value, digits = 6) => {
        if (value === null || value === undefined || value === '' || Number.isNaN(Number(value))) {
            return '—';
        }

        return Number(value).toLocaleString(undefined, {
            maximumFractionDigits: digits,
        });
    };

    const percent = (value, digits = 2) => {
        if (value === null || value === undefined || Number.isNaN(Number(value))) {
            return '—';
        }

        return `${Number(value).toFixed(digits)}%`;
    };

    const setStatus = (label, type = '') => {
        if (!elements.connectionBadge) {
            return;
        }

        elements.connectionBadge.textContent = label;
        elements.connectionBadge.classList.toggle('is-live', type === 'live');
        elements.connectionBadge.classList.toggle('is-error', type === 'error');
    };

    const clearAlert = () => {
        if (!elements.alert) {
            return;
        }

        elements.alert.hidden = true;
        elements.alert.textContent = '';
    };

    const showAlert = (message) => {
        if (!elements.alert) {
            return;
        }

        elements.alert.textContent = message;
        elements.alert.hidden = false;
    };

    const selectedRegion = () => stockRegions.find(
        (region) => region.key === state.region
    ) || null;

    const updateStockControls = () => {
        const stocks = state.market === 'STOCKS';

        if (elements.regionField) {
            elements.regionField.hidden = !stocks;
        }

        if (elements.exchangeField) {
            elements.exchangeField.hidden = !stocks;
        }

        if (!stocks || !elements.region || !elements.exchange) {
            return;
        }

        elements.region.replaceChildren();

        stockRegions.forEach((region) => {
            const option = document.createElement('option');
            option.value = region.key;
            option.textContent = region.enabled
                ? `${region.label} · ${region.data_mode || region.status}`
                : `${region.label} · ${region.status}`;
            option.disabled = !region.enabled;
            option.selected = region.key === state.region;
            elements.region.append(option);
        });

        const region = selectedRegion();

        if (!region || !region.enabled) {
            const fallback = stockRegions.find((item) => item.enabled);
            state.region = fallback?.key || '';
        }

        const activeRegion = selectedRegion();
        const supported = Array.isArray(activeRegion?.supported_timeframes)
            ? activeRegion.supported_timeframes
            : [];

        if (supported.length > 0 && !supported.includes(state.timeframe)) {
            state.timeframe = supported[0];
        }

        elements.region.value = state.region;
        elements.exchange.replaceChildren();

        const all = document.createElement('option');
        all.value = '';
        all.textContent = 'All Exchanges';
        elements.exchange.append(all);

        (activeRegion?.exchanges || []).forEach((exchange) => {
            const option = document.createElement('option');
            option.value = exchange.key;
            option.textContent = exchange.label;
            option.disabled = !exchange.enabled;
            elements.exchange.append(option);
        });

        const validExchange = (activeRegion?.exchanges || []).some(
            (exchange) => exchange.key === state.exchange && exchange.enabled
        );

        if (!validExchange) {
            state.exchange = '';
        }

        elements.exchange.value = state.exchange;
    };

    const renderMarkets = () => {
        if (!elements.marketTabs) {
            return;
        }

        elements.marketTabs.replaceChildren();

        markets.forEach((market) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'live-screener-market-tab';
            button.textContent = market.label;
            button.disabled = !market.enabled;
            button.setAttribute('role', 'tab');
            button.setAttribute('aria-selected', market.key === state.market ? 'true' : 'false');

            if (market.key === state.market) {
                button.classList.add('active');
            }

            button.addEventListener('click', () => {
                if (state.market === market.key) {
                    return;
                }

                state.market = market.key;
                state.exchange = '';

                if (state.market === 'STOCKS') {
                    state.region = 'INTERNATIONAL';
                }

                updateStockControls();
                renderMarkets();
                renderTimeframes();
                loadData();
            });

            elements.marketTabs.append(button);
        });
    };

    const renderTimeframes = () => {
        if (!elements.timeframes) {
            return;
        }

        const region = state.market === 'STOCKS' ? selectedRegion() : null;
        const supported = Array.isArray(region?.supported_timeframes)
            ? region.supported_timeframes
            : [];

        elements.timeframes.replaceChildren();

        timeframes.forEach((timeframe) => {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'live-screener-timeframe';
            button.textContent = timeframe.key;
            button.title = timeframe.label;
            button.disabled = supported.length > 0 && !supported.includes(timeframe.key);

            if (timeframe.key === state.timeframe) {
                button.classList.add('active');
            }

            button.addEventListener('click', () => {
                if (button.disabled || state.timeframe === timeframe.key) {
                    return;
                }

                state.timeframe = timeframe.key;
                renderTimeframes();
                loadData();
            });

            elements.timeframes.append(button);
        });
    };

    const query = () => {
        const params = new URLSearchParams();
        params.set('market', state.market);
        params.set('timeframe', state.timeframe);
        params.set('sort', elements.sort?.value || 'symbol');
        params.set('order', elements.order?.value || 'asc');

        if (state.market === 'STOCKS') {
            params.set('region', state.region);

            if (state.exchange) {
                params.set('exchange', state.exchange);
            }
        }

        if (elements.trend?.value) {
            params.set('trend', elements.trend.value);
        }

        if (elements.alignment?.value) {
            params.set('ema_alignment', elements.alignment.value);
        }

        if (elements.rsiMin?.value !== '') {
            params.set('rsi_min', elements.rsiMin.value);
        }

        if (elements.rsiMax?.value !== '') {
            params.set('rsi_max', elements.rsiMax.value);
        }

        return params;
    };

    const createCell = (content, className = '') => {
        const cell = document.createElement('td');
        cell.textContent = content;

        if (className) {
            cell.className = className;
        }

        return cell;
    };

    const renderRows = () => {
        if (!elements.rows) {
            return;
        }

        const search = (elements.search?.value || '').trim().toUpperCase();
        const rows = state.rows.filter((row) => {
            if (!search) {
                return true;
            }

            return String(row.symbol || '').toUpperCase().includes(search)
                || String(row.name || '').toUpperCase().includes(search);
        });

        elements.rows.replaceChildren();

        if (rows.length === 0) {
            const tr = document.createElement('tr');
            const td = document.createElement('td');
            td.colSpan = 13;
            td.className = 'live-screener-empty';
            td.textContent = search
                ? 'No screener rows match this search.'
                : 'No screener data is available for this selection.';
            tr.append(td);
            elements.rows.append(tr);
            return;
        }

        rows.forEach((row) => {
            const tr = document.createElement('tr');

            const symbolCell = document.createElement('td');
            const symbolWrap = document.createElement('div');
            symbolWrap.className = 'live-screener-symbol';
            const symbol = document.createElement('strong');
            symbol.textContent = text(row.symbol);
            const name = document.createElement('small');
            const marketMeta = [row.region, row.exchange].filter(Boolean).join(' · ');
            name.textContent = marketMeta
                ? `${text(row.name, '')} · ${marketMeta}`
                : text(row.name, '');
            symbolWrap.append(symbol, name);
            symbolCell.append(symbolWrap);
            tr.append(symbolCell);

            tr.append(createCell(number(row.price, 8)));

            const change = createCell(percent(row.change_percent));
            change.className = 'live-screener-change';

            if (Number(row.change_percent) > 0) {
                change.classList.add('positive');
            } else if (Number(row.change_percent) < 0) {
                change.classList.add('negative');
            }

            tr.append(change);

            const trendCell = document.createElement('td');
            const trend = document.createElement('span');
            const trendValue = text(row.trend, 'SIDEWAYS').toLowerCase();
            trend.className = `live-screener-trend ${trendValue}`;
            trend.textContent = text(row.trend);
            trendCell.append(trend);
            tr.append(trendCell);

            tr.append(createCell(text(row.ema_alignment)));
            tr.append(createCell(number(row.ema_20, 8)));
            tr.append(createCell(number(row.ema_50, 8)));
            tr.append(createCell(number(row.ema_200, 8)));
            tr.append(createCell(number(row.rsi_14, 2)));
            tr.append(createCell(number(row.macd_histogram, 8)));
            tr.append(createCell(percent(row.atr_percent, 3)));

            const volatilityCell = document.createElement('td');
            const volatility = document.createElement('span');
            volatility.className = 'live-screener-volatility';
            volatility.textContent = text(row.volatility);
            volatilityCell.append(volatility);
            tr.append(volatilityCell);

            tr.append(createCell(text(row.candle_time_utc), 'live-screener-cell-small'));
            elements.rows.append(tr);
        });
    };

    const updateSummary = (data) => {
        const market = markets.find((item) => item.key === data.market);
        const cache = data.cache || {};
        const providerStatus = data.provider_health?.status || 'UNKNOWN';

        if (elements.provider) {
            elements.provider.textContent = text(data.provider);
        }

        if (elements.providerStatus) {
            elements.providerStatus.textContent = `Provider status: ${providerStatus}`;
        }

        if (elements.count) {
            elements.count.textContent = String(data.count ?? 0);
        }

        if (elements.marketLabel) {
            elements.marketLabel.textContent = `${market?.label || data.market} · ${data.timeframe}`;
        }

        if (elements.cacheState) {
            elements.cacheState.textContent = cache.stale
                ? 'STALE FALLBACK'
                : (cache.hit ? 'CACHED' : 'FRESH');
        }

        if (elements.generatedAt) {
            elements.generatedAt.textContent = text(data.generated_at_utc, 'Not generated');
        }

        if (elements.tableTitle) {
            const stockSuffix = data.market === 'STOCKS'
                ? ` · ${state.region}${state.exchange ? ` / ${state.exchange}` : ''}`
                : '';
            elements.tableTitle.textContent = `${market?.label || data.market} · ${data.timeframe}${stockSuffix}`;
        }

        if (cache.stale) {
            showAlert('Provider is temporarily unavailable. TradeX is displaying a bounded stale fallback dataset.');
        }
    };

    const extractError = (payload, fallback) => {
        if (payload?.error?.message) {
            return payload.error.message;
        }

        if (payload?.errors && typeof payload.errors === 'object') {
            const first = Object.values(payload.errors).flat()[0];

            if (first) {
                return String(first);
            }
        }

        if (payload?.message) {
            return String(payload.message);
        }

        return fallback;
    };

    const loadData = async () => {
        clearAlert();
        setStatus('LOADING');

        if (elements.loading) {
            elements.loading.hidden = false;
        }

        if (state.request) {
            state.request.abort();
        }

        state.request = new AbortController();

        try {
            const response = await fetch(`${endpoint}?${query().toString()}`, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
                credentials: 'same-origin',
                signal: state.request.signal,
            });

            const payload = await response.json().catch(() => null);

            if (!response.ok || !payload?.success) {
                throw new Error(extractError(payload, `Screener request failed (${response.status}).`));
            }

            const data = payload.data || {};
            state.rows = Array.isArray(data.results) ? data.results : [];
            updateSummary(data);
            renderRows();
            setStatus(data.cache?.stale ? 'STALE' : 'LIVE', 'live');
        } catch (error) {
            if (error?.name === 'AbortError') {
                return;
            }

            state.rows = [];
            renderRows();
            setStatus('ERROR', 'error');
            showAlert(error instanceof Error ? error.message : 'Unable to load screener data.');
        } finally {
            if (elements.loading) {
                elements.loading.hidden = true;
            }
        }
    };

    const restartTimer = () => {
        if (state.timer) {
            window.clearInterval(state.timer);
            state.timer = null;
        }

        const enabled = Boolean(elements.autoRefresh?.checked);

        if (elements.refreshState) {
            elements.refreshState.textContent = enabled ? '30s' : 'MANUAL';
        }

        if (!enabled) {
            return;
        }

        state.timer = window.setInterval(loadData, 30000);
    };

    elements.region?.addEventListener('change', () => {
        state.region = elements.region.value;
        state.exchange = '';
        updateStockControls();
        renderTimeframes();
        loadData();
    });

    elements.exchange?.addEventListener('change', () => {
        state.exchange = elements.exchange.value;
        loadData();
    });

    [elements.trend, elements.alignment, elements.sort, elements.order].forEach((element) => {
        element?.addEventListener('change', loadData);
    });

    [elements.rsiMin, elements.rsiMax].forEach((element) => {
        element?.addEventListener('change', loadData);
    });

    elements.search?.addEventListener('input', renderRows);
    elements.refreshButton?.addEventListener('click', loadData);
    elements.autoRefresh?.addEventListener('change', restartTimer);

    updateStockControls();
    renderMarkets();
    renderTimeframes();
    restartTimer();
    loadData();
})();
