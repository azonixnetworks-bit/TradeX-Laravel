(function () {
    'use strict';

    var table = document.querySelector('[data-forex-live-table]');
    if (!table) return;

    var wsUrl = String(table.dataset.liveStreamUrl || 'ws://127.0.0.1:8001/ws/forex');
    var pollUrl = String(table.dataset.livePollUrl || 'http://127.0.0.1:8001/api/forex/prices');

    var socket = null;
    var pollTimer = null;
    var reconnectTimer = null;
    var previousPrices = new Map();
    var openPrices = new Map();

    // Map each row on the current page by normalized symbol
    function getRowsMap() {
        var map = new Map();
        var rows = table.querySelectorAll('[data-forex-symbol]');
        for (var i = 0; i < rows.length; i++) {
            var row = rows[i];
            var sym = String(row.dataset.forexSymbol || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (sym) {
                map.set(sym, row);
            }
        }
        return map;
    }

    function formatNumber(num, digits) {
        if (num === null || num === undefined || isNaN(num)) return '—';
        return Number(num).toFixed(digits !== undefined ? digits : 5);
    }

    function updateRow(row, item) {
        if (!row || !item) return;

        var priceCell = row.querySelector('[data-live-price]');
        var changeCell = row.querySelector('[data-live-change]');

        var currentPrice = item.price !== null && item.price !== undefined ? Number(item.price) : Number(item.ask || item.bid);
        if (!isFinite(currentPrice)) return;

        var sym = String(item.symbol || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
        var prevPrice = previousPrices.get(sym);

        var digits = item.digits !== undefined ? item.digits : (currentPrice < 5 ? 5 : (currentPrice < 500 ? 3 : 2));

        if (priceCell) {
            priceCell.textContent = formatNumber(currentPrice, digits);

            if (prevPrice !== undefined && prevPrice !== null) {
                if (currentPrice > prevPrice) {
                    priceCell.classList.remove('screener-live-down');
                    priceCell.classList.add('screener-live-up');
                    setTimeout(function () {
                        priceCell.classList.remove('screener-live-up');
                    }, 800);
                } else if (currentPrice < prevPrice) {
                    priceCell.classList.remove('screener-live-up');
                    priceCell.classList.add('screener-live-down');
                    setTimeout(function () {
                        priceCell.classList.remove('screener-live-down');
                    }, 800);
                }
            }

            previousPrices.set(sym, currentPrice);
        }

        // Calculate and update % change if available
        if (changeCell) {
            var initialPrice = openPrices.get(sym);
            if (!initialPrice) {
                var initialText = priceCell ? priceCell.getAttribute('data-initial-price') : null;
                initialPrice = initialText ? Number(initialText) : currentPrice;
                openPrices.set(sym, initialPrice);
            }

            if (initialPrice > 0) {
                var changePct = ((currentPrice - initialPrice) / initialPrice) * 100;
                var sign = changePct > 0 ? '+' : '';
                changeCell.textContent = sign + changePct.toFixed(2) + '%';

                if (changePct >= 0) {
                    changeCell.classList.remove('screener-live-down');
                    changeCell.classList.add('screener-live-up');
                } else {
                    changeCell.classList.remove('screener-live-up');
                    changeCell.classList.add('screener-live-down');
                }
            }
        }
    }

    function processItems(items) {
        if (!Array.isArray(items)) return;
        var rowsMap = getRowsMap();

        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            var sym = String(item.symbol || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
            var row = rowsMap.get(sym);
            if (row) {
                updateRow(row, item);
            }
        }
    }

    // Initialize initial price data attribute on load
    function initInitialPrices() {
        var rows = table.querySelectorAll('[data-forex-symbol]');
        for (var i = 0; i < rows.length; i++) {
            var cell = rows[i].querySelector('[data-live-price]');
            if (cell && cell.textContent.trim() !== '—') {
                var val = Number(cell.textContent.trim().replace(/,/g, ''));
                if (isFinite(val) && val > 0) {
                    cell.setAttribute('data-initial-price', String(val));
                    var sym = String(rows[i].dataset.forexSymbol || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
                    openPrices.set(sym, val);
                    previousPrices.set(sym, val);
                }
            }
        }
    }

    // HTTP polling fallback
    function pollPrices() {
        fetch(pollUrl)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                if (data && data.data) {
                    processItems(data.data);
                }
            })
            .catch(function () {});
    }

    function startPolling() {
        if (pollTimer) return;
        pollPrices();
        pollTimer = setInterval(pollPrices, 1500);
    }

    function stopPolling() {
        if (pollTimer) {
            clearInterval(pollTimer);
            pollTimer = null;
        }
    }

    // WebSocket connection
    function connectWebSocket() {
        try {
            socket = new WebSocket(wsUrl);

            socket.addEventListener('open', function () {
                stopPolling();
                showLiveBadge(true);
            });

            socket.addEventListener('message', function (event) {
                try {
                    var payload = JSON.parse(event.data);
                    if (payload && payload.data) {
                        processItems(payload.data);
                    }
                } catch (e) {}
            });

            socket.addEventListener('close', function () {
                startPolling();
                scheduleReconnect();
            });

            socket.addEventListener('error', function () {
                startPolling();
                socket.close();
            });

        } catch (e) {
            startPolling();
            scheduleReconnect();
        }
    }

    function scheduleReconnect() {
        if (reconnectTimer) return;
        reconnectTimer = setTimeout(function () {
            reconnectTimer = null;
            connectWebSocket();
        }, 5000);
    }

    function showLiveBadge(isWs) {
        var actions = document.querySelector('.screener-monitor-actions');
        if (!actions || document.querySelector('.screener-forex-ticker-pill')) return;

        var pill = document.createElement('span');
        pill.className = 'screener-forex-ticker-pill';
        pill.style.cssText = 'background: rgba(36, 198, 143, 0.15); color: #24c68f; padding: 4px 10px; border-radius: 999px; font-size: 0.72rem; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; border: 1px solid rgba(36, 198, 143, 0.3);';
        pill.innerHTML = '<span style="width: 7px; height: 7px; border-radius: 50%; background: #24c68f; display: inline-block; animation: pulse 1.5s infinite;"></span> FOREX LIVE TICKER';
        actions.appendChild(pill);
    }

    // Start
    initInitialPrices();
    connectWebSocket();
    showLiveBadge(false);

})();
