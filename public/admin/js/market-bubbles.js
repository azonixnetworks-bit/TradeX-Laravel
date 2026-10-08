(function () {
    'use strict';

    var root = document.querySelector('[data-market-bubbles]');

    if (!root) {
        return;
    }

    var canvas = root.querySelector('[data-bubble-canvas]');
    var emptyState = root.querySelector('[data-bubble-empty]');
    var loadingState = root.querySelector('[data-bubble-loading]');
    var loadingText = root.querySelector('[data-bubble-loading-text]');
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
        targetAssets: [],
        nodes: [],
        nodeBySymbol: new Map(),
        currentRange: { min: 0, max: 1 },
        width: 0,
        height: 0,
        hoverSymbol: '',
        selectedSymbol: '',
        loadGeneration: 0,
        loadedCount: 0,
        animationFrameId: null,
        lastFrameAt: 0,
        lastSummaryAt: 0,
        active: true
    };

    var ctx = canvas.getContext('2d');
    var resizeTimer = null;
    var socket = null;
    var reconnectTimer = null;

    function clamp(value, min, max) {
        return Math.max(min, Math.min(max, value));
    }

    function lerp(current, target, amount) {
        return current + ((target - current) * amount);
    }

    function formatPrice(value) {
        var n = Number(value);

        if (!Number.isFinite(n)) {
            return '—';
        }

        var digits = n >= 1000 ? 2 : n >= 1 ? 4 : n >= 0.01 ? 6 : 8;

        return n.toLocaleString(undefined, {
            maximumFractionDigits: digits
        });
    }

    function formatCompact(value) {
        var n = Number(value);

        if (!Number.isFinite(n)) {
            return '—';
        }

        return Intl.NumberFormat(undefined, {
            notation: 'compact',
            maximumFractionDigits: 2
        }).format(n);
    }

    function formatPercent(value) {
        var n = Number(value);

        if (!Number.isFinite(n)) {
            return '—';
        }

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

    function sortedFilteredAssets() {
        var needle = state.search.trim().toUpperCase();
        var items = allAssets.filter(function (asset) {
            if (!needle) {
                return true;
            }

            return String(asset.symbol || '').toUpperCase().includes(needle)
                || String(asset.name || '').toUpperCase().includes(needle)
                || String(asset.base_currency || '').toUpperCase().includes(needle);
        });

        items = items.slice();

        items.sort(function (left, right) {
            if (state.sort === 'gainers') {
                return Number(right.change_percent) - Number(left.change_percent);
            }

            if (state.sort === 'losers') {
                return Number(left.change_percent) - Number(right.change_percent);
            }

            if (state.sort === 'symbol') {
                return String(left.symbol).localeCompare(String(right.symbol));
            }

            return Number(right.quote_volume) - Number(left.quote_volume);
        });

        return items.slice(0, state.count);
    }

    function radiusRange(items) {
        var values = items.map(function (asset) {
            if (state.size === 'change') {
                return Math.abs(Number(asset.change_percent) || 0);
            }

            if (state.size === 'equal') {
                return 1;
            }

            return Math.max(0, Number(asset.quote_volume) || 0);
        });

        return {
            min: Math.min.apply(null, values.length ? values : [0]),
            max: Math.max.apply(null, values.length ? values : [1])
        };
    }

    function radiusFor(asset, range) {
        var mobile = state.width < 700;
        var minRadius = mobile ? 21 : 27;
        var maxRadius = mobile ? 50 : 68;

        if (state.size === 'equal' || range.max === range.min) {
            return (minRadius + maxRadius) / 2;
        }

        var value = state.size === 'change'
            ? Math.abs(Number(asset.change_percent) || 0)
            : Math.max(0, Number(asset.quote_volume) || 0);

        var normalised = (value - range.min)
            / Math.max(0.000001, range.max - range.min);

        normalised = Math.sqrt(clamp(normalised, 0, 1));

        return minRadius + ((maxRadius - minRadius) * normalised);
    }

    function nodeSpawnPoint(index, total) {
        var angle = (index * 2.399963229728653) + (Math.random() * 0.35);
        var spread = Math.min(state.width, state.height) * 0.30;
        var distance = Math.sqrt(index / Math.max(1, total)) * spread;

        return {
            x: (state.width / 2) + (Math.cos(angle) * distance),
            y: (state.height / 2) + (Math.sin(angle) * distance)
        };
    }

    function createNode(asset, index, total) {
        var point = nodeSpawnPoint(index, total);
        var targetRadius = radiusFor(asset, state.currentRange);

        return {
            id: index + 1,
            symbol: String(asset.symbol || ''),
            asset: asset,
            x: point.x,
            y: point.y,
            vx: (Math.random() - 0.5) * 0.22,
            vy: (Math.random() - 0.5) * 0.22,
            radius: Math.max(4, targetRadius * 0.18),
            targetRadius: targetRadius,
            displayChange: Number(asset.change_percent) || 0,
            targetChange: Number(asset.change_percent) || 0,
            phase: Math.random() * Math.PI * 2,
            driftX: 0.55 + (Math.random() * 0.55),
            driftY: 0.55 + (Math.random() * 0.55)
        };
    }

    function updateLoadingProgress(loaded, total) {
        state.loadedCount = loaded;

        if (!loadingState) {
            return;
        }

        if (total <= 0 || loaded >= total) {
            loadingState.hidden = true;
            return;
        }

        loadingState.hidden = false;

        if (loadingText) {
            loadingText.textContent = 'Loading market map ' + loaded + ' / ' + total;
        }
    }

    function syncNodes(assets) {
        var wanted = new Set();
        var nextNodes = [];

        assets.forEach(function (asset, index) {
            var symbol = String(asset.symbol || '');
            wanted.add(symbol);

            var node = state.nodeBySymbol.get(symbol);

            if (!node) {
                node = createNode(asset, index, assets.length);
                state.nodeBySymbol.set(symbol, node);
            }

            node.asset = asset;
            node.targetChange = Number(asset.change_percent) || 0;
            node.targetRadius = radiusFor(asset, state.currentRange);
            nextNodes.push(node);
        });

        state.nodeBySymbol.forEach(function (node, symbol) {
            if (!wanted.has(symbol)) {
                state.nodeBySymbol.delete(symbol);
            }
        });

        state.nodes = nextNodes;
        emptyState.hidden = state.targetAssets.length > 0;
    }

    function scheduleIdle(callback, timeout) {
        if ('requestIdleCallback' in window) {
            window.requestIdleCallback(callback, { timeout: timeout });
            return;
        }

        window.setTimeout(callback, Math.min(timeout, 180));
    }

    function progressiveLoad() {
        state.loadGeneration += 1;
        var generation = state.loadGeneration;

        state.targetAssets = sortedFilteredAssets();
        state.currentRange = radiusRange(state.targetAssets);

        if (!state.targetAssets.length) {
            syncNodes([]);
            updateLoadingProgress(0, 0);
            return;
        }

        var total = state.targetAssets.length;
        var first = Math.min(20, total);
        var second = Math.min(50, total);

        syncNodes(state.targetAssets.slice(0, first));
        updateLoadingProgress(first, total);

        if (first >= total) {
            return;
        }

        scheduleIdle(function () {
            if (generation !== state.loadGeneration) {
                return;
            }

            syncNodes(state.targetAssets.slice(0, second));
            updateLoadingProgress(second, total);

            if (second >= total) {
                return;
            }

            scheduleIdle(function () {
                if (generation !== state.loadGeneration) {
                    return;
                }

                syncNodes(state.targetAssets);
                updateLoadingProgress(total, total);
            }, 420);
        }, 240);
    }

    function resizeCanvas() {
        var wrap = canvas.parentElement;
        var rect = wrap.getBoundingClientRect();
        var dpr = Math.min(2, window.devicePixelRatio || 1);

        state.width = Math.max(280, Math.floor(rect.width));
        state.height = Math.max(460, Math.floor(rect.height));

        canvas.width = Math.floor(state.width * dpr);
        canvas.height = Math.floor(state.height * dpr);
        canvas.style.width = state.width + 'px';
        canvas.style.height = state.height + 'px';

        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);

        state.nodes.forEach(function (node) {
            node.x = clamp(node.x, node.radius + 4, state.width - node.radius - 4);
            node.y = clamp(node.y, node.radius + 4, state.height - node.radius - 4);
        });

        progressiveLoad();
    }

    function buildSpatialGrid(cellSize) {
        var grid = new Map();

        state.nodes.forEach(function (node) {
            var cellX = Math.floor(node.x / cellSize);
            var cellY = Math.floor(node.y / cellSize);
            var key = cellX + ':' + cellY;

            if (!grid.has(key)) {
                grid.set(key, []);
            }

            grid.get(key).push(node);
        });

        return grid;
    }

    function resolveCollisions(dt) {
        if (state.nodes.length < 2) {
            return;
        }

        var maxRadius = 70;
        var cellSize = (maxRadius * 2) + 12;
        var grid = buildSpatialGrid(cellSize);
        var visited = new Set();

        state.nodes.forEach(function (node) {
            var cellX = Math.floor(node.x / cellSize);
            var cellY = Math.floor(node.y / cellSize);

            for (var offsetX = -1; offsetX <= 1; offsetX += 1) {
                for (var offsetY = -1; offsetY <= 1; offsetY += 1) {
                    var bucket = grid.get((cellX + offsetX) + ':' + (cellY + offsetY));

                    if (!bucket) {
                        continue;
                    }

                    bucket.forEach(function (other) {
                        if (node === other) {
                            return;
                        }

                        var pairKey = node.symbol < other.symbol
                            ? node.symbol + '|' + other.symbol
                            : other.symbol + '|' + node.symbol;

                        if (visited.has(pairKey)) {
                            return;
                        }

                        visited.add(pairKey);

                        var dx = other.x - node.x;
                        var dy = other.y - node.y;
                        var distanceSquared = (dx * dx) + (dy * dy);

                        if (distanceSquared === 0) {
                            dx = 0.01;
                            dy = 0.01;
                            distanceSquared = 0.0002;
                        }

                        var distance = Math.sqrt(distanceSquared);
                        var minimum = node.radius + other.radius + 4;

                        if (distance >= minimum) {
                            return;
                        }

                        var overlap = minimum - distance;
                        var nx = dx / distance;
                        var ny = dy / distance;
                        var push = overlap * 0.48;

                        node.x -= nx * push;
                        node.y -= ny * push;
                        other.x += nx * push;
                        other.y += ny * push;

                        node.vx -= nx * overlap * 0.0035 * dt;
                        node.vy -= ny * overlap * 0.0035 * dt;
                        other.vx += nx * overlap * 0.0035 * dt;
                        other.vy += ny * overlap * 0.0035 * dt;
                    });
                }
            }
        });
    }

    function stepPhysics(timestamp, dt) {
        var centreX = state.width / 2;
        var centreY = state.height / 2;
        var time = timestamp * 0.001;
        var damping = Math.pow(0.982, dt);

        state.nodes.forEach(function (node) {
            node.radius = lerp(
                node.radius,
                node.targetRadius,
                clamp(0.075 * dt, 0, 0.28)
            );

            node.displayChange = lerp(
                node.displayChange,
                node.targetChange,
                clamp(0.10 * dt, 0, 0.34)
            );

            var centreForceX = (centreX - node.x) * 0.00042;
            var centreForceY = (centreY - node.y) * 0.00042;

            var driftForceX = Math.sin((time * node.driftX) + node.phase) * 0.014;
            var driftForceY = Math.cos((time * node.driftY) + node.phase) * 0.014;

            node.vx += (centreForceX + driftForceX) * dt;
            node.vy += (centreForceY + driftForceY) * dt;

            if (state.hoverSymbol && node.symbol === state.hoverSymbol) {
                node.vx += Math.sin(time * 2.2) * 0.006 * dt;
                node.vy += Math.cos(time * 2.2) * 0.006 * dt;
            }

            node.vx *= damping;
            node.vy *= damping;

            node.x += node.vx * dt;
            node.y += node.vy * dt;
        });

        resolveCollisions(dt);

        state.nodes.forEach(function (node) {
            var padding = node.radius + 4;

            if (node.x < padding) {
                node.x = padding;
                node.vx = Math.abs(node.vx) * 0.72;
            } else if (node.x > state.width - padding) {
                node.x = state.width - padding;
                node.vx = -Math.abs(node.vx) * 0.72;
            }

            if (node.y < padding) {
                node.y = padding;
                node.vy = Math.abs(node.vy) * 0.72;
            } else if (node.y > state.height - padding) {
                node.y = state.height - padding;
                node.vy = -Math.abs(node.vy) * 0.72;
            }
        });
    }

    function drawNode(node) {
        var change = node.displayChange;
        var hovered = node.symbol === state.hoverSymbol;
        var selected = node.symbol === state.selectedSymbol;

        ctx.save();
        ctx.beginPath();
        ctx.arc(node.x, node.y, node.radius, 0, Math.PI * 2);

        ctx.fillStyle = bubbleFill(change, hovered || selected ? 0.38 : 0.23);
        ctx.fill();

        ctx.lineWidth = hovered || selected ? 2.6 : 1.35;
        ctx.strokeStyle = bubbleFill(change, hovered || selected ? 1 : 0.7);
        ctx.shadowBlur = hovered || selected ? 24 : 9;
        ctx.shadowColor = bubbleFill(change, 0.46);
        ctx.stroke();
        ctx.shadowBlur = 0;

        var symbolSize = clamp(node.radius * 0.30, 10, 18);
        var changeSize = clamp(node.radius * 0.24, 9, 15);

        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = '#f8fafc';
        ctx.font = '800 ' + symbolSize + 'px system-ui, sans-serif';
        ctx.fillText(
            String(node.asset.base_currency || node.asset.symbol || '').slice(0, 8),
            node.x,
            node.y - 6
        );

        ctx.font = '700 ' + changeSize + 'px system-ui, sans-serif';
        ctx.fillStyle = change >= 0 ? '#9ef0c5' : '#ffb4b4';
        ctx.fillText(formatPercent(change), node.x, node.y + 12);

        ctx.restore();
    }

    function render() {
        ctx.clearRect(0, 0, state.width, state.height);
        state.nodes.forEach(drawNode);
    }

    function animationLoop(timestamp) {
        if (!state.active || document.hidden) {
            state.animationFrameId = null;
            return;
        }

        if (!state.lastFrameAt) {
            state.lastFrameAt = timestamp;
        }

        var delta = Math.min(34, timestamp - state.lastFrameAt);
        var dt = Math.max(0.35, delta / 16.6667);

        state.lastFrameAt = timestamp;

        stepPhysics(timestamp, dt);
        render();

        state.animationFrameId = window.requestAnimationFrame(animationLoop);
    }

    function startAnimation() {
        if (state.animationFrameId || document.hidden) {
            return;
        }

        state.active = true;
        state.lastFrameAt = 0;
        state.animationFrameId = window.requestAnimationFrame(animationLoop);
    }

    function stopAnimation() {
        state.active = false;

        if (state.animationFrameId) {
            window.cancelAnimationFrame(state.animationFrameId);
            state.animationFrameId = null;
        }
    }

    function hitTest(x, y) {
        for (var index = state.nodes.length - 1; index >= 0; index -= 1) {
            var node = state.nodes[index];
            var dx = x - node.x;
            var dy = y - node.y;

            if ((dx * dx) + (dy * dy) <= node.radius * node.radius) {
                return node;
            }
        }

        return null;
    }

    function showDetail(asset) {
        if (!detail) {
            return;
        }

        state.selectedSymbol = String(asset.symbol || '');

        var setText = function (selector, value) {
            var element = detail.querySelector(selector);

            if (element) {
                element.textContent = value;
            }
        };

        setText('[data-detail-symbol]', asset.symbol || '—');
        setText('[data-detail-name]', asset.name || '—');
        setText(
            '[data-detail-initials]',
            String(asset.base_currency || asset.symbol || '--').slice(0, 2)
        );
        setText('[data-detail-price]', formatPrice(asset.price));
        setText('[data-detail-change]', formatPercent(asset.change_percent));
        setText('[data-detail-open]', formatPrice(asset.open));
        setText('[data-detail-high]', formatPrice(asset.high));
        setText('[data-detail-low]', formatPrice(asset.low));
        setText('[data-detail-volume]', formatCompact(asset.quote_volume) + ' USDT');
        setText(
            '[data-detail-base-volume]',
            formatCompact(asset.volume) + ' ' + String(asset.base_currency || '')
        );
        setText(
            '[data-detail-trades]',
            Number(asset.trade_count || 0).toLocaleString()
        );

        var changeElement = detail.querySelector('[data-detail-change]');

        if (changeElement) {
            changeElement.classList.toggle(
                'positive',
                Number(asset.change_percent) >= 0
            );
            changeElement.classList.toggle(
                'negative',
                Number(asset.change_percent) < 0
            );
        }

        var logo = detail.querySelector('[data-detail-logo]');

        if (logo) {
            logo.hidden = true;

            if (asset.logo_url) {
                logo.src = asset.logo_url;
                logo.onload = function () {
                    logo.hidden = false;
                };
                logo.onerror = function () {
                    logo.hidden = true;
                };
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
        if (!detail) {
            return;
        }

        detail.hidden = true;
        state.selectedSymbol = '';
        document.body.classList.remove('screener-chart-open');
    }

    canvas.addEventListener('pointermove', function (event) {
        var rect = canvas.getBoundingClientRect();
        var node = hitTest(
            event.clientX - rect.left,
            event.clientY - rect.top
        );

        state.hoverSymbol = node ? node.symbol : '';
        canvas.style.cursor = node ? 'pointer' : 'default';
    });

    canvas.addEventListener('pointerleave', function () {
        state.hoverSymbol = '';
        canvas.style.cursor = 'default';
    });

    canvas.addEventListener('click', function (event) {
        var rect = canvas.getBoundingClientRect();
        var node = hitTest(
            event.clientX - rect.left,
            event.clientY - rect.top
        );

        if (node) {
            showDetail(node.asset);
        }
    });

    document.querySelectorAll('[data-bubble-close]').forEach(function (button) {
        button.addEventListener('click', closeDetail);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeDetail();
        }
    });

    function onControlsChanged() {
        state.search = searchInput ? searchInput.value : '';
        state.sort = sortSelect ? sortSelect.value : 'volume';
        state.size = sizeSelect ? sizeSelect.value : 'volume';
        state.count = countSelect
            ? Number(countSelect.value) || 100
            : 100;

        progressiveLoad();
    }

    [searchInput, sortSelect, sizeSelect, countSelect].forEach(function (control) {
        if (!control) {
            return;
        }

        control.addEventListener(
            control === searchInput ? 'input' : 'change',
            onControlsChanged
        );
    });

    function setConnection(label, healthy) {
        if (!connectionBadge) {
            return;
        }

        connectionBadge.textContent = label;
        connectionBadge.classList.toggle('status-healthy', healthy);
        connectionBadge.classList.toggle('status-degraded', !healthy);
    }

    function refreshVisibleNodeTargets() {
        state.currentRange = radiusRange(state.targetAssets);

        state.nodes.forEach(function (node) {
            node.targetRadius = radiusFor(node.asset, state.currentRange);
            node.targetChange = Number(node.asset.change_percent) || 0;
        });
    }

    function updateSummary() {
        var now = Date.now();

        if (now - state.lastSummaryAt < 750) {
            return;
        }

        state.lastSummaryAt = now;

        var loadedAssets = state.targetAssets;

        if (!loadedAssets.length) {
            return;
        }

        var gainers = 0;
        var losers = 0;
        var totalChange = 0;

        loadedAssets.forEach(function (asset) {
            var change = Number(asset.change_percent) || 0;

            totalChange += change;

            if (change > 0) {
                gainers += 1;
            } else if (change < 0) {
                losers += 1;
            }
        });

        var totalElement = document.querySelector('[data-summary-total]');
        var gainersElement = document.querySelector('[data-summary-gainers]');
        var losersElement = document.querySelector('[data-summary-losers]');
        var averageElement = document.querySelector('[data-summary-average]');

        if (totalElement) totalElement.textContent = String(loadedAssets.length);
        if (gainersElement) gainersElement.textContent = String(gainers);
        if (losersElement) losersElement.textContent = String(losers);

        if (averageElement) {
            averageElement.textContent = (
                totalChange / Math.max(1, loadedAssets.length)
            ).toFixed(2) + '%';
        }
    }

    function updateAssetFromTicker(payload) {
        var symbol = String(payload.s || '').toUpperCase();
        var asset = allAssets.find(function (item) {
            return String(item.symbol || '').toUpperCase() === symbol;
        });

        if (!asset) {
            return;
        }

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

        var visibleNode = state.nodeBySymbol.get(symbol);

        if (visibleNode) {
            visibleNode.asset = asset;
            visibleNode.targetChange = Number(asset.change_percent) || 0;
            visibleNode.targetRadius = radiusFor(asset, state.currentRange);
        }

        if (updatedAt) {
            updatedAt.textContent = new Date().toISOString();
        }

        updateSummary();
    }

    function connect() {
        if (socket) {
            return;
        }

        clearTimeout(reconnectTimer);

        var symbols = allAssets.map(function (asset) {
            return String(asset.symbol || '').toLowerCase();
        }).filter(Boolean);

        if (!symbols.length || document.hidden) {
            return;
        }

        var streams = symbols.map(function (symbol) {
            return symbol + '@ticker';
        }).join('/');

        try {
            socket = new WebSocket(
                'wss://stream.binance.com:9443/stream?streams=' + streams
            );
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
                reconnectTimer = window.setTimeout(connect, 3000);
            }
        });

        socket.addEventListener('error', function () {
            setConnection('CONNECTION LOST', false);
        });
    }

    function disconnect() {
        clearTimeout(reconnectTimer);

        if (socket) {
            var currentSocket = socket;
            socket = null;

            try {
                currentSocket.close();
            } catch (error) {
                // Socket is already closing.
            }
        }
    }

    document.addEventListener('visibilitychange', function () {
        if (document.hidden) {
            stopAnimation();
            disconnect();
        } else {
            state.active = true;
            refreshVisibleNodeTargets();
            startAnimation();
            connect();
        }
    });

    window.addEventListener('resize', function () {
        clearTimeout(resizeTimer);
        resizeTimer = window.setTimeout(resizeCanvas, 120);
    });

    window.addEventListener('beforeunload', function () {
        stopAnimation();
        disconnect();
    });

    resizeCanvas();
    startAnimation();
    connect();
}());
