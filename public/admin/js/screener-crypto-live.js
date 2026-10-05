(function () {
    'use strict';

    var table = document.querySelector('[data-binance-live-table]');

    if (!table) {
        return;
    }

    var rows = Array.prototype.slice.call(table.querySelectorAll('[data-binance-symbol]'));
    var symbols = rows.map(function (row) {
        return String(row.dataset.binanceSymbol || '').toLowerCase();
    }).filter(Boolean);
    var socket = null;
    var reconnectTimer = null;
    var analysisUrl = String(table.dataset.analysisUrl || '');
    var analysisTimeframe = String(table.dataset.analysisTimeframe || 'H1');
    var analysisQueue = [];
    var analysisActive = 0;
    var analysisLimit = 3;

    function decimalsFor(price) {
        if (price >= 1000) return 2;
        if (price >= 1) return 4;
        if (price >= 0.01) return 6;
        return 8;
    }

    function formatPrice(value) {
        var price = Number(value);
        return Number.isFinite(price)
            ? price.toLocaleString(undefined, {
                minimumFractionDigits: 0,
                maximumFractionDigits: decimalsFor(price)
            })
            : '—';
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

    function connect() {
        if (!symbols.length || document.hidden) return;

        var streams = symbols.map(function (symbol) {
            return symbol + '@ticker';
        }).join('/');

        socket = new WebSocket('wss://stream.binance.com:9443/stream?streams=' + streams);
        socket.addEventListener('message', function (event) {
            try {
                var message = JSON.parse(event.data);
                updateTicker(message.data || message);
            } catch (error) {
                // Ignore malformed third-party stream messages.
            }
        });
        socket.addEventListener('close', function () {
            socket = null;
            if (!document.hidden) {
                clearTimeout(reconnectTimer);
                reconnectTimer = setTimeout(connect, 3000);
            }
        });
    }

    function disconnect() {
        clearTimeout(reconnectTimer);
        if (socket) {
            socket.close();
            socket = null;
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) disconnect(); else connect();
    });

    function textOrDash(value) {
        return value === null || typeof value === 'undefined' || value === '' ? '—' : String(value);
    }

    function updateAnalysisRow(row, data) {
        var priceCell = row.querySelector('[data-live-price]');
        var changeCell = row.querySelector('[data-live-change]');
        var trendCell = row.querySelector('[data-analysis-trend]');
        var trend = String(data.trend || 'SIDEWAYS').toUpperCase();

        if (priceCell && !priceCell.dataset.liveValue && Number.isFinite(Number(data.price))) {
            priceCell.textContent = formatPrice(data.price);
        }
        if (changeCell && !changeCell.classList.contains('screener-live-up') && !changeCell.classList.contains('screener-live-down') && Number.isFinite(Number(data.change_percent))) {
            changeCell.textContent = Number(data.change_percent).toFixed(2) + '%';
        }
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

    function pumpAnalysisQueue() {
        while (analysisActive < analysisLimit && analysisQueue.length) {
            var row = analysisQueue.shift();
            var symbol = String(row.dataset.binanceSymbol || '');
            analysisActive += 1;
            row.classList.add('screener-analysis-loading');

            fetch(analysisUrl + '?' + new URLSearchParams({
                symbol: symbol,
                timeframe: analysisTimeframe
            }).toString(), {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' },
                cache: 'no-store'
            })
                .then(function (response) {
                    if (!response.ok) throw new Error('Analysis unavailable');
                    return response.json();
                })
                .then(function (payload) {
                    if (payload && payload.success && payload.data) {
                        updateAnalysisRow(row, payload.data);
                        row.dataset.analysisState = 'loaded';
                    } else {
                        throw new Error('Analysis unavailable');
                    }
                })
                .catch(function () {
                    var trendCell = row.querySelector('[data-analysis-trend]');
                    if (trendCell) trendCell.innerHTML = '<span class="screener-lazy-value">Retry on reload</span>';
                    row.dataset.analysisState = 'failed';
                })
                .finally(function () {
                    row.classList.remove('screener-analysis-loading');
                    analysisActive -= 1;
                    pumpAnalysisQueue();
                });
        }
    }

    function queueAnalysis(row) {
        if (!analysisUrl || row.dataset.analysisState) return;
        row.dataset.analysisState = 'queued';
        analysisQueue.push(row);
        pumpAnalysisQueue();
    }

    if ('IntersectionObserver' in window) {
        var analysisObserver = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    analysisObserver.unobserve(entry.target);
                    queueAnalysis(entry.target);
                }
            });
        }, { rootMargin: '280px 0px' });
        rows.forEach(function (row) { analysisObserver.observe(row); });
    } else {
        rows.forEach(queueAnalysis);
    }

    window.addEventListener('beforeunload', disconnect);
    connect();
}());
