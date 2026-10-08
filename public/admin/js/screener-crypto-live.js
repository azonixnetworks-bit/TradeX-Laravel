(function () {
    'use strict';

    var table = document.querySelector('[data-binance-live-table]');
    if (!table) return;

    var rows = Array.prototype.slice.call(table.querySelectorAll('[data-binance-symbol]'));
    var initialRows = rows.slice(0, 5);
    var deferredRows = rows.slice(5);
    var socket = null;
    var reconnectTimer = null;
    var deferredTickerTimer = null;
    var analysisUrl = String(table.dataset.analysisUrl || '');
    var analysisTimeframe = String(table.dataset.analysisTimeframe || 'H1');
    var cryptoSegment = String(table.dataset.cryptoSegment || 'SPOT').toUpperCase();
    var liveStreamUrl = String(table.dataset.liveStreamUrl || 'wss://stream.binance.com:9443/ws');
    var analysisQueue = [];
    var analysisActive = 0;
    var analysisLimit = 5;
    var initialBatchComplete = false;
    var watchlistUrl = String(table.dataset.watchlistUrl || '');
    var watchlistDestroyUrl = String(table.dataset.watchlistDestroyUrl || '');
    var catalogWarmUrl = String(table.dataset.catalogWarmUrl || '');
    var chartUrlTemplate = String(table.dataset.chartUrlTemplate || '');
    var watchlistPanel = document.querySelector('[data-binance-watchlist]');
    var watchlist = new Map();
    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function symbolFor(row) { return String(row.dataset.binanceSymbol || '').toUpperCase(); }
    function streamFor(row) { return symbolFor(row).toLowerCase() + '@ticker'; }

    function decimalsFor(price) {
        if (price >= 1000) return 2;
        if (price >= 1) return 4;
        if (price >= 0.01) return 6;
        return 8;
    }

    function formatPrice(value) {
        var price = Number(value);
        return Number.isFinite(price) ? price.toLocaleString(undefined, {
            minimumFractionDigits: 0,
            maximumFractionDigits: decimalsFor(price)
        }) : '—';
    }

    function updateTicker(payload) {
        var symbol = String(payload.s || '').toUpperCase();
        var row = table.querySelector('[data-binance-symbol="' + CSS.escape(symbol) + '"]');
        if (!row) return;

        var priceCell = row.querySelector('[data-live-price]');
        var changeCell = row.querySelector('[data-live-change]');
        var price = Number(payload.c);
        var change = Number(payload.P);

        if (priceCell && Number.isFinite(price)) {
            var previous = Number(priceCell.dataset.liveValue);
            priceCell.textContent = formatPrice(price);
            priceCell.dataset.liveValue = String(price);
            priceCell.classList.toggle('screener-live-up', Number.isFinite(previous) && price > previous);
            priceCell.classList.toggle('screener-live-down', Number.isFinite(previous) && price < previous);
        }

        if (changeCell && Number.isFinite(change)) {
            changeCell.textContent = change.toFixed(2) + '%';
            changeCell.classList.toggle('screener-live-up', change >= 0);
            changeCell.classList.toggle('screener-live-down', change < 0);
        }
    }

    function subscribeRows(items) {
        if (!socket || socket.readyState !== WebSocket.OPEN || !items.length) return;
        socket.send(JSON.stringify({method: 'SUBSCRIBE', params: items.map(streamFor), id: Date.now()}));
    }

    function scheduleDeferredTickerSubscriptions() {
        var remaining = deferredRows.slice();
        clearInterval(deferredTickerTimer);
        deferredTickerTimer = setInterval(function () {
            var next = remaining.shift();
            if (!next) {
                clearInterval(deferredTickerTimer);
                deferredTickerTimer = null;
                return;
            }
            subscribeRows([next]);
        }, 350);
    }

    function connect() {
        if (!initialRows.length || document.hidden) return;
        socket = new WebSocket(liveStreamUrl);
        socket.addEventListener('open', function () {
            subscribeRows(initialRows);
            scheduleDeferredTickerSubscriptions();
        });
        socket.addEventListener('message', function (event) {
            try {
                var message = JSON.parse(event.data);
                if (message && message.e === '24hrTicker') updateTicker(message);
            } catch (error) {
                // Ignore malformed third-party stream messages.
            }
        });
        socket.addEventListener('close', function () {
            socket = null;
            clearInterval(deferredTickerTimer);
            deferredTickerTimer = null;
            if (!document.hidden) {
                clearTimeout(reconnectTimer);
                reconnectTimer = setTimeout(connect, 3000);
            }
        });
    }

    function disconnect() {
        clearTimeout(reconnectTimer);
        clearInterval(deferredTickerTimer);
        deferredTickerTimer = null;
        if (socket) {
            socket.close();
            socket = null;
        }
    }

    function textOrDash(value) {
        return value === null || typeof value === 'undefined' || value === '' ? '—' : String(value);
    }

    function updateAnalysisRow(row, data) {
        var priceCell = row.querySelector('[data-live-price]');
        var changeCell = row.querySelector('[data-live-change]');
        var trendCell = row.querySelector('[data-analysis-trend]');
        var trend = String(data.trend || 'SIDEWAYS').toUpperCase();

        if (priceCell && !priceCell.dataset.liveValue && Number.isFinite(Number(data.price))) priceCell.textContent = formatPrice(data.price);
        if (changeCell && !changeCell.classList.contains('screener-live-up') && !changeCell.classList.contains('screener-live-down') && Number.isFinite(Number(data.change_percent))) changeCell.textContent = Number(data.change_percent).toFixed(2) + '%';
        if (trendCell) {
            trendCell.replaceChildren();
            var badge = document.createElement('span');
            badge.className = 'screener-status trend-' + trend.toLowerCase();
            badge.textContent = trend;
            trendCell.appendChild(badge);
        }

        var rsiCell = row.querySelector('[data-analysis-rsi]');
        var emaCell = row.querySelector('[data-analysis-ema]');
        var atrCell = row.querySelector('[data-analysis-atr]');
        var timeCell = row.querySelector('[data-analysis-time]');
        if (rsiCell) rsiCell.textContent = textOrDash(data.rsi_14);
        if (emaCell) emaCell.textContent = textOrDash(data.ema_alignment);
        if (atrCell) atrCell.textContent = Number.isFinite(Number(data.atr_percent)) ? Number(data.atr_percent).toFixed(2) + '%' : '—';
        if (timeCell) timeCell.textContent = textOrDash(data.candle_time_utc);
    }

    function queueAnalysis(row) {
        if (!analysisUrl || row.dataset.analysisState) return;
        row.dataset.analysisState = 'queued';
        analysisQueue.push(row);
    }

    function beginDeferredAnalysis() {
        if (initialBatchComplete) return;
        initialBatchComplete = true;
        analysisLimit = 1;
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (!entry.isIntersecting) return;
                    observer.unobserve(entry.target);
                    queueAnalysis(entry.target);
                    pumpAnalysisQueue();
                });
            }, {rootMargin: '160px 0px'});
            deferredRows.forEach(function (row) { observer.observe(row); });
            return;
        }
        deferredRows.forEach(queueAnalysis);
        pumpAnalysisQueue();
    }

    function pumpAnalysisQueue() {
        while (analysisActive < analysisLimit && analysisQueue.length) {
            var row = analysisQueue.shift();
            analysisActive += 1;
            row.classList.add('screener-analysis-loading');
            fetch(analysisUrl + '?' + new URLSearchParams({symbol: symbolFor(row), timeframe: analysisTimeframe, segment: cryptoSegment}).toString(), {
                credentials: 'same-origin',
                headers: {'Accept': 'application/json'},
                cache: 'no-store'
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('Analysis unavailable');
                    return response.json();
                })
                .then(function (payload) {
                    if (!payload || !payload.success || !payload.data) throw new Error('Analysis unavailable');
                    updateAnalysisRow(row, payload.data);
                    row.dataset.analysisState = 'loaded';
                })
                .catch(function () {
                    var trendCell = row.querySelector('[data-analysis-trend]');
                    if (trendCell) trendCell.innerHTML = '<span class="screener-lazy-value">Retry on reload</span>';
                    row.dataset.analysisState = 'failed';
                })
                .finally(function () {
                    row.classList.remove('screener-analysis-loading');
                    analysisActive -= 1;
                    if (!initialBatchComplete && analysisActive === 0 && analysisQueue.length === 0) beginDeferredAnalysis();
                    pumpAnalysisQueue();
                });
        }
    }

    function setNotice(message) {
        var notice = document.querySelector('[data-watchlist-notice]');
        if (notice) notice.textContent = message || '';
    }

    function chartUrlFor(item) {
        var row = table.querySelector('[data-binance-symbol="' + CSS.escape(item.symbol) + '"]');
        var link = row && row.querySelector('.screener-symbol-chart-link');
        return link ? link.href : chartUrlTemplate.replace('__SYMBOL__', encodeURIComponent(item.symbol));
    }

    function renderWatchlist() {
        if (!watchlistPanel) return;
        watchlistPanel.replaceChildren();
        watchlistPanel.classList.toggle('is-visible', watchlist.size > 0);
        if (!watchlist.size) return;

        var title = document.createElement('strong');
        title.textContent = 'Watchlist';
        var count = document.createElement('span');
        count.textContent = watchlist.size + ' / 30';
        watchlistPanel.append(title, count);

        Array.from(watchlist.values()).forEach(function (item) {
            var link = document.createElement(chartUrlFor(item) ? 'a' : 'span');
            link.className = 'screener-watchlist-item';
            link.textContent = '★ ' + item.symbol;
            if (link.tagName === 'A') link.href = chartUrlFor(item);
            watchlistPanel.appendChild(link);
        });

        rows.slice().sort(function (left, right) {
            return Number(watchlist.has(symbolFor(right))) - Number(watchlist.has(symbolFor(left)));
        }).forEach(function (row) {
            var symbol = symbolFor(row);
            var button = row.querySelector('[data-watchlist-toggle]');
            if (!button) return;
            var selected = watchlist.has(symbol);
            button.textContent = selected ? '★' : '☆';
            button.setAttribute('aria-pressed', selected ? 'true' : 'false');
            button.setAttribute('aria-label', (selected ? 'Remove ' : 'Add ') + symbol + (selected ? ' from' : ' to') + ' your watchlist');
            button.title = selected ? 'Remove from watchlist' : 'Add to watchlist';
            table.tBodies[0].appendChild(row);
        });
    }

    function requestWatchlist(url, options) {
        return fetch(url, Object.assign({
            credentials: 'same-origin',
            headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken}
        }, options || {})).then(function (response) {
            return response.json().then(function (payload) {
                if (!response.ok || !payload.success) {
                    throw new Error((payload.errors && payload.errors.symbol && payload.errors.symbol[0]) || payload.message || 'Watchlist could not be updated.');
                }
                return payload;
            });
        });
    }

    function loadWatchlist() {
        if (!watchlistUrl) return;
        requestWatchlist(watchlistUrl).then(function (payload) {
            (payload.data || []).forEach(function (item) { watchlist.set(String(item.symbol).toUpperCase(), item); });
            renderWatchlist();
        }).catch(function () { setNotice('Watchlist is temporarily unavailable.'); });
    }

    function warmCatalog() {
        if (!catalogWarmUrl) return;
        window.setTimeout(function () {
            fetch(catalogWarmUrl, {
                credentials: 'same-origin',
                headers: {'Accept': 'application/json'},
                cache: 'no-store'
            }).catch(function () {
                // The seed list remains usable if Binance is temporarily slow.
            });
        }, 1200);
    }

    table.addEventListener('click', function (event) {
        var button = event.target.closest('[data-watchlist-toggle]');
        if (!button) return;
        event.preventDefault();
        var row = button.closest('[data-binance-symbol]');
        var symbol = symbolFor(row);
        button.disabled = true;

        if (watchlist.has(symbol)) {
            requestWatchlist(watchlistDestroyUrl.replace('__SYMBOL__', encodeURIComponent(symbol)), {method: 'DELETE'})
                .then(function () { watchlist.delete(symbol); setNotice('Removed ' + symbol + ' from your watchlist.'); renderWatchlist(); })
                .catch(function (error) { setNotice(error.message); })
                .finally(function () { button.disabled = false; });
            return;
        }

        requestWatchlist(watchlistUrl, {
            method: 'POST',
            headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken},
            body: JSON.stringify({symbol: symbol, segment: cryptoSegment})
        })
            .then(function (payload) { watchlist.set(symbol, payload.data); setNotice('Added ' + symbol + ' to your watchlist.'); renderWatchlist(); })
            .catch(function (error) { setNotice(error.message); })
            .finally(function () { button.disabled = false; });
    });

    initialRows.forEach(queueAnalysis);
    pumpAnalysisQueue();
    loadWatchlist();
    warmCatalog();
    connect();

    document.addEventListener('visibilitychange', function () { if (document.hidden) disconnect(); else connect(); });
    window.addEventListener('beforeunload', disconnect);
}());
