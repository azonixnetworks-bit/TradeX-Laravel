(function () {
    'use strict';

    var root = document.querySelector('[data-market-bubbles]');

    if (!root) {
        return;
    }

    var canvas = root.querySelector('[data-bubble-canvas]');
    var emptyState = root.querySelector('[data-bubble-empty]');
    var searchInput = document.querySelector('[data-bubble-search]');
    var sortSelect = document.querySelector('[data-bubble-sort]');
    var sizeSelect = document.querySelector('[data-bubble-size]');
    var countSelect = document.querySelector('[data-bubble-count]');
    var connectionBadge = document.querySelector('[data-bubble-connection-badge]');
    var updatedAt = document.querySelector('[data-bubble-updated-at]');
    var detail = document.querySelector('[data-bubble-detail]');
    var chartBase = String(root.dataset.chartUrl || '');

    var allAssets = [];
    var assetsPayload = root.querySelector('[data-bubble-assets]');
    try {
        allAssets = JSON.parse(assetsPayload ? assetsPayload.textContent : '[]');
    } catch (error) {
        allAssets = [];
    }

    var state = {
        search: '',
        sort: 'volume',
        size: 'volume',
        count: 100,
        assets: [],
        nodes: [],
        width: 0,
        height: 0,
        hoverIndex: -1
    };

    var ctx = canvas.getContext('2d');
    var resizeTimer = null;
    var socket = null;
    var reconnectTimer = null;
    var renderRequested = false;

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function formatPrice(value) {
        var n = Number(value);
        if (!Number.isFinite(n)) return '—';
        var digits = n >= 1000 ? 2 : n >= 1 ? 4 : n >= 0.01 ? 6 : 8;
        return n.toLocaleString(undefined, { maximumFractionDigits: digits });
    }

    function formatCompact(value) {
        var n = Number(value);
        if (!Number.isFinite(n)) return '—';
        return Intl.NumberFormat(undefined, {
            notation: 'compact',
            maximumFractionDigits: 2
        }).format(n);
    }

    function formatPercent(value) {
        var n = Number(value);
        if (!Number.isFinite(n)) return '—';
        return (n >= 0 ? '+' : '') + n.toFixed(2) + '%';
    }

    function bubbleFill(change, alpha) {
        if (change >= 10) return 'rgba(20, 220, 145,' + alpha + ')';
        if (change >= 5) return 'rgba(34, 197, 94,' + alpha + ')';
        if (change >= 1) return 'rgba(74, 222, 128,' + alpha + ')';
        if (change > 0) return 'rgba(134, 239, 172,' + alpha + ')';
        if (change <= -10) return 'rgba(239, 68, 68,' + alpha + ')';
        if (change <= -5) return 'rgba(248, 81, 73,' + alpha + ')';
        if (change <= -1) return 'rgba(248, 113, 113,' + alpha + ')';
        if (change < 0) return 'rgba(252, 165, 165,' + alpha + ')';
        return 'rgba(148, 163, 184,' + alpha + ')';
    }

    function sortAssets(items) {
        var copy = items.slice();

        copy.sort(function (a, b) {
            if (state.sort === 'gainers') return Number(b.change_percent) - Number(a.change_percent);
            if (state.sort === 'losers') return Number(a.change_percent) - Number(b.change_percent);
            if (state.sort === 'symbol') return String(a.symbol).localeCompare(String(b.symbol));
            return Number(b.quote_volume) - Number(a.quote_volume);
        });

        return copy;
    }

    function filteredAssets() {
        var needle = state.search.trim().toUpperCase();
        var items = allAssets.filter(function (asset) {
            if (!needle) return true;
            return String(asset.symbol || '').toUpperCase().includes(needle)
                || String(asset.name || '').toUpperCase().includes(needle)
                || String(asset.base_currency || '').toUpperCase().includes(needle);
        });

        return sortAssets(items).slice(0, state.count);
    }

    function radiusRange(items) {
        var values = items.map(function (asset) {
            if (state.size === 'change') return Math.abs(Number(asset.change_percent) || 0);
            if (state.size === 'equal') return 1;
            return Math.max(0, Number(asset.quote_volume) || 0);
        });

        var min = Math.min.apply(null, values.length ? values : [0]);
        var max = Math.max.apply(null, values.length ? values : [1]);

        return { min: min, max: max };
    }

    function radiusFor(asset, range) {
        var minRadius = state.width < 700 ? 22 : 28;
        var maxRadius = state.width < 700 ? 52 : 70;

        if (state.size === 'equal' || range.max === range.min) {
            return (minRadius + maxRadius) / 2;
        }

        var value = state.size === 'change'
            ? Math.abs(Number(asset.change_percent) || 0)
            : Math.max(0, Number(asset.quote_volume) || 0);

        var t = (value - range.min) / Math.max(0.000001, range.max - range.min);
        t = Math.sqrt(clamp(t, 0, 1));

        return minRadius + ((maxRadius - minRadius) * t);
    }

    function buildNodes() {
        state.assets = filteredAssets();
        emptyState.hidden = state.assets.length > 0;

        var range = radiusRange(state.assets);
        var cx = state.width / 2;
        var cy = state.height / 2;

        state.nodes = state.assets.map(function (asset, index) {
            var angle = index * 2.399963229728653;
            var spread = Math.min(state.width, state.height) * 0.36;
            var dist = Math.sqrt(index / Math.max(1, state.assets.length)) * spread;

            return {
                asset: asset,
                radius: radiusFor(asset, range),
                x: cx + Math.cos(angle) * dist,
                y: cy + Math.sin(angle) * dist,
                vx: 0,
                vy: 0
            };
        });

        settleNodes(120);
        requestRender();
    }

    function settleNodes(iterations) {
        var padding = 4;
        var cx = state.width / 2;
        var cy = state.height / 2;

        for (var step = 0; step < iterations; step += 1) {
            for (var i = 0; i < state.nodes.length; i += 1) {
                var node = state.nodes[i];

                node.vx += (cx - node.x) * 0.0009;
                node.vy += (cy - node.y) * 0.0009;

                for (var j = i + 1; j < state.nodes.length; j += 1) {
                    var other = state.nodes[j];
                    var dx = other.x - node.x;
                    var dy = other.y - node.y;
                    var distSq = (dx * dx) + (dy * dy);
                    var minDist = node.radius + other.radius + padding;

                    if (distSq === 0) {
                        dx = 0.01;
                        dy = 0.01;
                        distSq = 0.0002;
                    }

                    var dist = Math.sqrt(distSq);

                    if (dist < minDist) {
                        var overlap = (minDist - dist) / 2;
                        var ux = dx / dist;
                        var uy = dy / dist;

                        node.x -= ux * overlap;
                        node.y -= uy * overlap;
                        other.x += ux * overlap;
                        other.y += uy * overlap;
                    }
                }

                node.vx *= 0.88;
                node.vy *= 0.88;
                node.x += node.vx;
                node.y += node.vy;

                node.x = clamp(node.x, node.radius + 4, state.width - node.radius - 4);
                node.y = clamp(node.y, node.radius + 4, state.height - node.radius - 4);
            }
        }
    }

    function resizeCanvas() {
        var wrap = canvas.parentElement;
        var rect = wrap.getBoundingClientRect();
        var dpr = window.devicePixelRatio || 1;

        state.width = Math.max(280, Math.floor(rect.width));
        state.height = Math.max(460, Math.floor(rect.height));

        canvas.width = Math.floor(state.width * dpr);
        canvas.height = Math.floor(state.height * dpr);
        canvas.style.width = state.width + 'px';
        canvas.style.height = state.height + 'px';
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        buildNodes();
    }

    function drawNode(node, index) {
        var asset = node.asset;
        var change = Number(asset.change_percent) || 0;
        var hovered = index === state.hoverIndex;

        ctx.save();
        ctx.beginPath();
        ctx.arc(node.x, node.y, node.radius, 0, Math.PI * 2);
        ctx.fillStyle = bubbleFill(change, hovered ? 0.34 : 0.22);
        ctx.fill();
        ctx.lineWidth = hovered ? 2.5 : 1.4;
        ctx.strokeStyle = bubbleFill(change, hovered ? 0.95 : 0.68);
        ctx.shadowBlur = hovered ? 22 : 10;
        ctx.shadowColor = bubbleFill(change, 0.45);
        ctx.stroke();
        ctx.shadowBlur = 0;

        var symbolSize = clamp(node.radius * 0.30, 10, 18);
        var changeSize = clamp(node.radius * 0.24, 9, 15);

        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = '#f8fafc';
        ctx.font = '800 ' + symbolSize + 'px system-ui, sans-serif';
        ctx.fillText(String(asset.base_currency || asset.symbol || '').slice(0, 8), node.x, node.y - 6);

        ctx.font = '700 ' + changeSize + 'px system-ui, sans-serif';
        ctx.fillStyle = change >= 0 ? '#9ef0c5' : '#ffb4b4';
        ctx.fillText(formatPercent(change), node.x, node.y + 12);
        ctx.restore();
    }

    function render() {
        renderRequested = false;
        ctx.clearRect(0, 0, state.width, state.height);
        state.nodes.forEach(drawNode);
    }

    function requestRender() {
        if (renderRequested) return;
        renderRequested = true;
        window.requestAnimationFrame(render);
    }

    function hitTest(x, y) {
        for (var i = state.nodes.length - 1; i >= 0; i -= 1) {
            var node = state.nodes[i];
            var dx = x - node.x;
            var dy = y - node.y;
            if ((dx * dx) + (dy * dy) <= node.radius * node.radius) return i;
        }
        return -1;
    }

    function showDetail(asset) {
        if (!detail) return;

        var setText = function (selector, value) {
            var el = detail.querySelector(selector);
            if (el) el.textContent = value;
        };

        setText('[data-detail-symbol]', asset.symbol || '—');
        setText('[data-detail-name]', asset.name || '—');
        setText('[data-detail-initials]', String(asset.base_currency || asset.symbol || '--').slice(0, 2));
        setText('[data-detail-price]', formatPrice(asset.price));
        setText('[data-detail-change]', formatPercent(asset.change_percent));
        setText('[data-detail-open]', formatPrice(asset.open));
        setText('[data-detail-high]', formatPrice(asset.high));
        setText('[data-detail-low]', formatPrice(asset.low));
        setText('[data-detail-volume]', formatCompact(asset.quote_volume) + ' USDT');
        setText('[data-detail-base-volume]', formatCompact(asset.volume) + ' ' + String(asset.base_currency || ''));
        setText('[data-detail-trades]', Number(asset.trade_count || 0).toLocaleString());

        var changeEl = detail.querySelector('[data-detail-change]');
        if (changeEl) {
            changeEl.classList.toggle('positive', Number(asset.change_percent) >= 0);
            changeEl.classList.toggle('negative', Number(asset.change_percent) < 0);
        }

        var logo = detail.querySelector('[data-detail-logo]');
        if (logo) {
            logo.hidden = true;
            if (asset.logo_url) {
                logo.src = asset.logo_url;
                logo.onload = function () { logo.hidden = false; };
                logo.onerror = function () { logo.hidden = true; };
            }
        }

        var chart = detail.querySelector('[data-detail-chart]');
        if (chart) {
            chart.href = chartBase + '?' + new URLSearchParams({
                symbol: String(asset.symbol || ''),
                timeframe: 'H1'
            }).toString();
        }

        detail.hidden = false;
        document.body.classList.add('screener-chart-open');
    }

    function closeDetail() {
        if (!detail) return;
        detail.hidden = true;
        document.body.classList.remove('screener-chart-open');
    }

    canvas.addEventListener('pointermove', function (event) {
        var rect = canvas.getBoundingClientRect();
        var index = hitTest(event.clientX - rect.left, event.clientY - rect.top);
        if (index !== state.hoverIndex) {
            state.hoverIndex = index;
            canvas.style.cursor = index >= 0 ? 'pointer' : 'default';
            requestRender();
        }
    });

    canvas.addEventListener('pointerleave', function () {
        state.hoverIndex = -1;
        canvas.style.cursor = 'default';
        requestRender();
    });

    canvas.addEventListener('click', function (event) {
        var rect = canvas.getBoundingClientRect();
        var index = hitTest(event.clientX - rect.left, event.clientY - rect.top);
        if (index >= 0) showDetail(state.nodes[index].asset);
    });

    document.querySelectorAll('[data-bubble-close]').forEach(function (button) {
        button.addEventListener('click', closeDetail);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeDetail();
    });

    function onControlsChanged() {
        state.search = searchInput ? searchInput.value : '';
        state.sort = sortSelect ? sortSelect.value : 'volume';
        state.size = sizeSelect ? sizeSelect.value : 'volume';
        state.count = countSelect ? Number(countSelect.value) || 100 : 100;
        buildNodes();
    }

    [searchInput, sortSelect, sizeSelect, countSelect].forEach(function (control) {
        if (!control) return;
        control.addEventListener(control === searchInput ? 'input' : 'change', onControlsChanged);
    });

    function setConnection(label, healthy) {
        if (!connectionBadge) return;
        connectionBadge.textContent = label;
        connectionBadge.classList.toggle('status-healthy', healthy);
        connectionBadge.classList.toggle('status-degraded', !healthy);
    }

    function updateAssetFromTicker(payload) {
        var symbol = String(payload.s || '').toUpperCase();
        var asset = allAssets.find(function (item) {
            return String(item.symbol || '').toUpperCase() === symbol;
        });

        if (!asset) return;

        var price = Number(payload.c);
        var change = Number(payload.P);
        var volume = Number(payload.v);
        var quoteVolume = Number(payload.q);
        var high = Number(payload.h);
        var low = Number(payload.l);
        var open = Number(payload.o);

        if (Number.isFinite(price)) asset.price = price;
        if (Number.isFinite(change)) asset.change_percent = change;
        if (Number.isFinite(volume)) asset.volume = volume;
        if (Number.isFinite(quoteVolume)) asset.quote_volume = quoteVolume;
        if (Number.isFinite(high)) asset.high = high;
        if (Number.isFinite(low)) asset.low = low;
        if (Number.isFinite(open)) asset.open = open;

        var visibleNode = state.nodes.find(function (node) {
            return String(node.asset.symbol || '').toUpperCase() === symbol;
        });

        if (visibleNode) visibleNode.asset = asset;

        if (updatedAt) {
            updatedAt.textContent = new Date().toISOString();
        }

        requestRender();
    }

    function connect() {
        if (socket) {
            return;
        }

        clearTimeout(reconnectTimer);

        var symbols = allAssets.map(function (asset) {
            return String(asset.symbol || '').toLowerCase();
        }).filter(Boolean);

        if (!symbols.length || document.hidden) return;

        var streams = symbols.map(function (symbol) {
            return symbol + '@ticker';
        }).join('/');

        try {
            socket = new WebSocket('wss://stream.binance.com:9443/stream?streams=' + streams);
        } catch (error) {
            setConnection('CONNECTION LOST', false);
            return;
        }

        socket.addEventListener('open', function () {
            setConnection('LIVE STREAM', true);
        });

        socket.addEventListener('message', function (event) {
            try {
                var message = JSON.parse(event.data);
                updateAssetFromTicker(message.data || message);
            } catch (error) {
                // Ignore malformed provider frames.
            }
        });

        socket.addEventListener('close', function () {
            socket = null;
            setConnection('RECONNECTING', false);
            if (!document.hidden) {
                clearTimeout(reconnectTimer);
                reconnectTimer = setTimeout(connect, 3000);
            }
        });

        socket.addEventListener('error', function () {
            setConnection('CONNECTION LOST', false);
        });
    }

    function disconnect(closeSocket) {
        clearTimeout(reconnectTimer);
        if (closeSocket !== false && socket) {
            socket.close();
            socket = null;
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            disconnect(true);
        } else {
            connect();
        }
    });

    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(resizeCanvas, 120);
    });

    window.addEventListener('beforeunload', function () {
        disconnect(true);
    });

    resizeCanvas();
    connect();
}());
