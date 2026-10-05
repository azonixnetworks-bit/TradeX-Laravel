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

    var modal = document.querySelector('[data-screener-chart-modal]');
    var chart = modal && modal.querySelector('[data-tradingview-chart]');
    var title = modal && modal.querySelector('[data-chart-title]');
    var previousFocus = null;

    function closeChart() {
        if (!modal) return;
        modal.hidden = true;
        document.body.classList.remove('screener-chart-open');
        if (chart) chart.replaceChildren();
        if (previousFocus) previousFocus.focus();
    }

    function openChart(button) {
        if (!modal || !chart) return;

        var symbol = String(button.dataset.chartSymbol || '').replace(/[^A-Z0-9]/g, '');
        if (!symbol) return;

        previousFocus = button;
        title.textContent = symbol + ' · ' + String(button.dataset.chartName || 'Binance');
        chart.replaceChildren();

        var container = document.createElement('div');
        container.className = 'tradingview-widget-container';
        var widget = document.createElement('div');
        widget.className = 'tradingview-widget-container__widget';
        var script = document.createElement('script');
        script.src = 'https://s3.tradingview.com/external-embedding/embed-widget-advanced-chart.js';
        script.async = true;
        script.textContent = JSON.stringify({
            autosize: true,
            symbol: 'BINANCE:' + symbol,
            interval: '60',
            timezone: 'Etc/UTC',
            theme: 'dark',
            style: '1',
            locale: 'en',
            allow_symbol_change: false,
            hide_side_toolbar: true,
            hide_top_toolbar: false,
            hide_legend: false,
            hide_volume: true,
            studies: [],
            withdateranges: true,
            save_image: false,
            calendar: false,
            support_host: 'https://www.tradingview.com'
        });
        container.appendChild(widget);
        container.appendChild(script);
        chart.appendChild(container);
        modal.hidden = false;
        document.body.classList.add('screener-chart-open');
        modal.querySelector('[data-chart-close]').focus();
    }

    document.addEventListener('click', function (event) {
        var chartButton = event.target.closest('[data-chart-symbol]');
        var closeButton = event.target.closest('[data-chart-close]');
        if (chartButton) openChart(chartButton);
        if (closeButton) closeChart();
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && modal && !modal.hidden) closeChart();
    });

    window.addEventListener('beforeunload', disconnect);
    connect();
}());
