(() => {
    'use strict';

    const app = document.getElementById('marketClockApp');
    const initialNode = document.getElementById('marketClockInitialData');

    if (!app || !initialNode) {
        return;
    }

    const endpoint = app.dataset.endpoint;
    const timezoneSelect = document.getElementById('marketClockTimezone');
    const deviceTimezoneButton = document.getElementById('marketClockUseDeviceTimezone');
    const svg = document.getElementById('marketClockDial');
    const storageKey = 'tradex.marketClock.timezone.v1';
    const svgNs = 'http://www.w3.org/2000/svg';

    let snapshot = JSON.parse(initialNode.textContent || '{}');
    let selectedTimezone = snapshot.display_timezone || 'UTC';
    let refreshTimer = null;
    let secondTimer = null;
    let serverEpochAtSync = Date.parse(snapshot.server_time_utc || '') || Date.now();
    let clientEpochAtSync = Date.now();

    const node = (id) => document.getElementById(id);

    const el = (tag, attrs = {}, text = null) => {
        const item = document.createElementNS(svgNs, tag);

        Object.entries(attrs).forEach(([key, value]) => {
            item.setAttribute(key, String(value));
        });

        if (text !== null) {
            item.textContent = text;
        }

        return item;
    };

    const escapeText = (value) => String(value ?? '');

    const polar = (radius, minute) => {
        const angle = ((minute / 1440) * 360 - 90) * (Math.PI / 180);

        return {
            x: 350 + radius * Math.cos(angle),
            y: 350 + radius * Math.sin(angle),
        };
    };

    const arcPath = (radius, startMinute, endMinute) => {
        const span = Math.max(0.01, endMinute - startMinute);
        const start = polar(radius, startMinute);
        const end = polar(radius, endMinute % 1440);
        const largeArc = span > 720 ? 1 : 0;

        return [
            'M', start.x.toFixed(3), start.y.toFixed(3),
            'A', radius, radius, 0, largeArc, 1,
            end.x.toFixed(3), end.y.toFixed(3),
        ].join(' ');
    };

    const addCircle = (parent, radius, attrs = {}) => {
        parent.appendChild(el('circle', {
            cx: 350,
            cy: 350,
            r: radius,
            ...attrs,
        }));
    };

    const renderDial = () => {
        if (!svg || !Array.isArray(snapshot.sessions)) {
            return;
        }

        svg.replaceChildren();

        const defs = el('defs');
        const glow = el('filter', {
            id: 'marketActiveGlow',
            x: '-40%',
            y: '-40%',
            width: '180%',
            height: '180%',
        });
        glow.appendChild(el('feGaussianBlur', {
            stdDeviation: 4,
            result: 'blur',
        }));
        const merge = el('feMerge');
        merge.appendChild(el('feMergeNode', {in: 'blur'}));
        merge.appendChild(el('feMergeNode', {in: 'SourceGraphic'}));
        glow.appendChild(merge);
        defs.appendChild(glow);
        svg.appendChild(defs);

        addCircle(svg, 322, {
            fill: '#06182a',
            stroke: 'rgba(255,255,255,.08)',
            'stroke-width': 2,
        });
        addCircle(svg, 292, {
            fill: 'var(--surface)',
            stroke: 'var(--border)',
            'stroke-width': 1,
        });
        addCircle(svg, 105, {
            fill: 'rgba(32,199,161,.025)',
            stroke: 'rgba(32,199,161,.18)',
            'stroke-width': 1,
        });

        for (let hour = 0; hour < 24; hour += 1) {
            const minute = hour * 60;
            const inner = polar(hour % 6 === 0 ? 286 : 294, minute);
            const outer = polar(317, minute);
            const label = polar(305, minute);
            const major = hour % 6 === 0;

            svg.appendChild(el('line', {
                x1: inner.x,
                y1: inner.y,
                x2: outer.x,
                y2: outer.y,
                stroke: major ? '#ff7a00' : 'rgba(255,255,255,.22)',
                'stroke-width': major ? 2.1 : 1,
            }));

            svg.appendChild(el('text', {
                x: label.x,
                y: label.y + 4,
                'text-anchor': 'middle',
                fill: major ? '#ff8a1c' : '#d9e4ef',
                'font-size': major ? 16 : 13,
                'font-weight': major ? 850 : 600,
            }, hour === 0 ? '24' : String(hour).padStart(2, '0')));
        }

        for (let minute = 0; minute < 1440; minute += 15) {
            if (minute % 60 === 0) {
                continue;
            }

            const inner = polar(310, minute);
            const outer = polar(317, minute);
            svg.appendChild(el('line', {
                x1: inner.x,
                y1: inner.y,
                x2: outer.x,
                y2: outer.y,
                stroke: 'rgba(255,255,255,.13)',
                'stroke-width': .8,
            }));
        }

        snapshot.sessions.forEach((session, index) => {
            const radius = 270 - (index * 14.2);

            svg.appendChild(el('circle', {
                cx: 350,
                cy: 350,
                r: radius,
                fill: 'none',
                stroke: 'rgba(148,163,184,.12)',
                'stroke-width': 9,
            }));

            const segments = Array.isArray(session.dial_segments)
                ? session.dial_segments
                : [];

            segments.forEach((segment, segmentIndex) => {
                if (session.status === 'open') {
                    svg.appendChild(el('path', {
                        d: arcPath(radius, segment.start_minute, segment.end_minute),
                        fill: 'none',
                        stroke: '#20c7a1',
                        'stroke-width': 14,
                        'stroke-linecap': 'round',
                        opacity: .42,
                        filter: 'url(#marketActiveGlow)',
                    }));
                }

                svg.appendChild(el('path', {
                    d: arcPath(radius, segment.start_minute, segment.end_minute),
                    fill: 'none',
                    stroke: '#ff7a00',
                    'stroke-width': 9,
                    'stroke-linecap': 'round',
                }));

                if (segmentIndex === 0) {
                    const midpoint = segment.start_minute
                        + ((segment.end_minute - segment.start_minute) / 2);
                    const labelPoint = polar(radius, midpoint);
                    let rotation = ((midpoint / 1440) * 360);

                    if (rotation > 90 && rotation < 270) {
                        rotation += 180;
                    }

                    svg.appendChild(el('text', {
                        x: labelPoint.x,
                        y: labelPoint.y + 3.5,
                        'text-anchor': 'middle',
                        fill: '#071421',
                        'font-size': 9.2,
                        'font-weight': 900,
                        'letter-spacing': .45,
                        transform: `rotate(${rotation} ${labelPoint.x} ${labelPoint.y})`,
                    }, escapeText(session.name).toUpperCase()));
                }

                const closeMarker = polar(radius, segment.end_minute % 1440);
                svg.appendChild(el('circle', {
                    cx: closeMarker.x,
                    cy: closeMarker.y,
                    r: session.status === 'open' ? 4.3 : 3.2,
                    fill: session.status === 'open' ? '#20c7a1' : '#8ba1b6',
                    stroke: 'var(--surface)',
                    'stroke-width': 1.5,
                }));
            });
        });

        [90, 70].forEach((radius) => addCircle(svg, radius, {
            fill: 'none',
            stroke: 'rgba(32,199,161,.14)',
            'stroke-width': 1,
        }));

        [0, 360, 720, 1080].forEach((minute) => {
            const p = polar(285, minute);
            svg.appendChild(el('line', {
                x1: 350,
                y1: 350,
                x2: p.x,
                y2: p.y,
                stroke: 'rgba(148,163,184,.10)',
                'stroke-width': 1,
            }));
        });

        const liveLayer = el('g', {id: 'marketClockLiveLayer'});
        liveLayer.appendChild(el('path', {
            id: 'marketClockCurrentWedge',
            fill: 'rgba(32,199,161,.08)',
            stroke: 'none',
        }));
        liveLayer.appendChild(el('line', {
            id: 'marketClockTimePointer',
            x1: 350,
            y1: 350,
            x2: 350,
            y2: 70,
            stroke: '#20c7a1',
            'stroke-width': 2.2,
            'stroke-linecap': 'round',
        }));
        liveLayer.appendChild(el('line', {
            id: 'marketClockHourHand',
            x1: 350,
            y1: 350,
            x2: 350,
            y2: 225,
            stroke: '#d9e4ef',
            'stroke-width': 8,
            'stroke-linecap': 'round',
        }));
        liveLayer.appendChild(el('line', {
            id: 'marketClockMinuteHand',
            x1: 350,
            y1: 350,
            x2: 350,
            y2: 185,
            stroke: '#8fa5ba',
            'stroke-width': 5,
            'stroke-linecap': 'round',
        }));
        liveLayer.appendChild(el('line', {
            id: 'marketClockSecondHand',
            x1: 350,
            y1: 350,
            x2: 350,
            y2: 160,
            stroke: '#ff7a00',
            'stroke-width': 2,
            'stroke-linecap': 'round',
        }));
        liveLayer.appendChild(el('circle', {
            cx: 350,
            cy: 350,
            r: 13,
            fill: '#0b2236',
            stroke: '#ff7a00',
            'stroke-width': 2.2,
        }));
        liveLayer.appendChild(el('circle', {
            cx: 350,
            cy: 350,
            r: 4,
            fill: '#dbe7f1',
        }));
        svg.appendChild(liveLayer);

        updateLiveClock();
    };

    const zonedParts = (date, timezone) => {
        const formatter = new Intl.DateTimeFormat('en-GB', {
            timeZone: timezone,
            hourCycle: 'h23',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            weekday: 'short',
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        });
        const parts = formatter.formatToParts(date);
        const values = {};

        parts.forEach((part) => {
            if (part.type !== 'literal') {
                values[part.type] = part.value;
            }
        });

        return values;
    };

    const rotateHand = (id, degrees) => {
        const hand = node(id);

        if (hand) {
            hand.setAttribute('transform', `rotate(${degrees} 350 350)`);
        }
    };

    const currentServerDate = () => new Date(
        serverEpochAtSync + (Date.now() - clientEpochAtSync)
    );

    const syncServerClock = () => {
        const parsed = Date.parse(snapshot.server_time_utc || '');

        if (Number.isFinite(parsed)) {
            serverEpochAtSync = parsed;
            clientEpochAtSync = Date.now();
        }
    };

    const updateLiveClock = () => {
        const now = currentServerDate();
        let parts;

        try {
            parts = zonedParts(now, selectedTimezone);
        } catch (error) {
            return;
        }

        const hours = Number(parts.hour || 0);
        const minutes = Number(parts.minute || 0);
        const seconds = Number(parts.second || 0);
        const minuteOfDay = (hours * 60) + minutes + (seconds / 60);

        rotateHand('marketClockHourHand', ((hours % 12) * 30) + (minutes / 2));
        rotateHand('marketClockMinuteHand', (minutes * 6) + (seconds / 10));
        rotateHand('marketClockSecondHand', seconds * 6);
        rotateHand('marketClockTimePointer', (minuteOfDay / 1440) * 360);

        const pointerMinute = minuteOfDay;
        const wedgeStart = Math.max(0, pointerMinute - 18);
        const wedgeEnd = Math.min(1439.9, pointerMinute + 18);
        const start = polar(250, wedgeStart);
        const end = polar(250, wedgeEnd);
        const wedge = node('marketClockCurrentWedge');

        if (wedge) {
            wedge.setAttribute(
                'd',
                `M 350 350 L ${start.x} ${start.y} A 250 250 0 0 1 ${end.x} ${end.y} Z`
            );
        }

        const currentTime = node('marketCurrentTime');
        const currentDate = node('marketCurrentDate');

        if (currentTime) {
            currentTime.textContent = `${parts.hour}:${parts.minute}:${parts.second}`;
        }

        if (currentDate) {
            currentDate.textContent = `${parts.weekday}, ${parts.day} ${parts.month} ${parts.year} · ${selectedTimezone}`;
        }

        updateCountdown();
    };

    const formatDuration = (seconds) => {
        if (!Number.isFinite(seconds) || seconds < 0) {
            return 'Opening soon';
        }

        const totalMinutes = Math.max(0, Math.floor(seconds / 60));
        const days = Math.floor(totalMinutes / 1440);
        const hours = Math.floor((totalMinutes % 1440) / 60);
        const minutes = totalMinutes % 60;

        if (days > 0) {
            return `in ${days}d ${hours}h ${minutes}m`;
        }

        if (hours > 0) {
            return `in ${hours}h ${minutes}m`;
        }

        return `in ${minutes}m`;
    };

    const updateCountdown = () => {
        const target = snapshot.next_session?.opens_at_utc;
        const countdown = node('marketNextCountdown');

        if (!target || !countdown) {
            if (countdown) {
                countdown.textContent = '—';
            }
            return;
        }

        const seconds = Math.floor(
            (new Date(target).getTime() - currentServerDate().getTime()) / 1000
        );
        const viewTime = snapshot.next_session?.opens_at_display || '';
        countdown.textContent = `${formatDuration(seconds)}${viewTime ? ` · ${viewTime}` : ''}`;
    };

    const renderSessionRows = () => {
        const tbody = node('marketSessionRows');

        if (!tbody) {
            return;
        }

        tbody.replaceChildren();

        snapshot.sessions.forEach((session) => {
            const row = document.createElement('tr');

            const sessionCell = document.createElement('td');
            const name = document.createElement('strong');
            const meta = document.createElement('small');
            name.textContent = session.name;
            meta.textContent = `${session.region} · ${session.timezone}`;
            sessionCell.append(name, meta);

            const statusCell = document.createElement('td');
            const badge = document.createElement('span');
            badge.className = `market-status-badge ${session.status}`;
            badge.textContent = session.status.toUpperCase();
            statusCell.appendChild(badge);

            const localCell = document.createElement('td');
            localCell.append(document.createTextNode(session.local_time));
            const offset = document.createElement('small');
            offset.textContent = `UTC${session.local_offset}`;
            localCell.appendChild(offset);

            const hoursCell = document.createElement('td');
            hoursCell.textContent = `${session.configured_open_local}–${session.configured_close_local}`;

            const nextCell = document.createElement('td');
            nextCell.textContent = session.next_open_at_display;

            row.append(sessionCell, statusCell, localCell, hoursCell, nextCell);
            tbody.appendChild(row);
        });
    };

    const renderSummary = () => {
        const summary = snapshot.summary || {};
        const active = Array.isArray(snapshot.active_sessions)
            ? snapshot.active_sessions
            : [];
        const activeNames = active.map((session) => session.name);
        const isOpen = snapshot.market_status === 'open';
        const livePill = node('marketLivePill');

        node('marketActiveCount').textContent = summary.open_now ?? 0;
        node('marketActiveNames').textContent = activeNames.length
            ? activeNames.join(', ')
            : 'Between sessions';
        node('marketNextName').textContent = snapshot.next_session?.name || '—';
        node('marketOverlap').textContent = summary.current_overlap || 'None';
        node('marketStatusText').textContent = isOpen
            ? 'Major trading centres are active'
            : 'Between configured sessions';
        node('marketInsightText').textContent = summary.insight || '';
        node('marketTotalSessions').textContent = summary.total_sessions ?? 0;
        node('marketOpenNow').textContent = summary.open_now ?? 0;
        node('marketClosedNow').textContent = summary.closed_now ?? 0;
        node('marketDisplayTimezone').textContent = snapshot.display_timezone || selectedTimezone;
        node('marketTimezoneChip').textContent = snapshot.display_timezone || selectedTimezone;
        node('marketDonutValue').textContent = `${summary.open_now ?? 0}/${summary.total_sessions ?? 0}`;

        if (livePill) {
            livePill.classList.toggle('is-quiet', !isOpen);
        }

        const label = node('marketLiveLabel');
        if (label) {
            label.textContent = isOpen ? 'LIVE' : 'QUIET';
        }

        const donut = node('marketSummaryDonut');
        if (donut) {
            const total = Math.max(1, Number(summary.total_sessions || 0));
            const open = Math.max(0, Number(summary.open_now || 0));
            donut.style.setProperty('--open-angle', `${(open / total) * 360}deg`);
        }

        updateCountdown();
    };

    const renderAll = () => {
        selectedTimezone = snapshot.display_timezone || selectedTimezone;
        syncServerClock();
        renderDial();
        renderSessionRows();
        renderSummary();
        updateLiveClock();
    };

    const fetchSnapshot = async (timezone) => {
        if (!endpoint || !timezone) {
            return;
        }

        app.classList.add('is-loading');

        try {
            const response = await fetch(
                `${endpoint}?timezone=${encodeURIComponent(timezone)}`,
                {
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    cache: 'no-store',
                }
            );

            if (!response.ok) {
                throw new Error(`Market clock request failed with ${response.status}.`);
            }

            const payload = await response.json();

            if (!payload?.success || !payload?.data) {
                throw new Error('Market clock response was invalid.');
            }

            snapshot = payload.data;
            selectedTimezone = snapshot.display_timezone;
            renderAll();
        } catch (error) {
            console.error('[TradeX Market Clock]', error);
        } finally {
            app.classList.remove('is-loading');
        }
    };

    const optionExists = (timezone) => {
        if (!timezoneSelect || !timezone) {
            return false;
        }

        return Array.from(timezoneSelect.options)
            .some((option) => option.value === timezone);
    };

    const resolveInitialTimezone = () => {
        const saved = window.localStorage.getItem(storageKey);

        if (saved && optionExists(saved)) {
            return saved;
        }

        try {
            const device = Intl.DateTimeFormat().resolvedOptions().timeZone;

            if (device && optionExists(device)) {
                return device;
            }
        } catch (error) {
            // Keep the safe UTC/server default when browser timezone lookup fails.
        }

        return selectedTimezone;
    };

    if (timezoneSelect) {
        timezoneSelect.addEventListener('change', () => {
            const timezone = timezoneSelect.value;
            window.localStorage.setItem(storageKey, timezone);
            fetchSnapshot(timezone);
        });
    }

    if (deviceTimezoneButton) {
        deviceTimezoneButton.addEventListener('click', () => {
            try {
                const device = Intl.DateTimeFormat().resolvedOptions().timeZone;

                if (device && optionExists(device)) {
                    timezoneSelect.value = device;
                    window.localStorage.setItem(storageKey, device);
                    fetchSnapshot(device);
                }
            } catch (error) {
                console.error('[TradeX Market Clock] Device timezone unavailable.', error);
            }
        });
    }

    renderAll();

    const initialTimezone = resolveInitialTimezone();
    if (initialTimezone && initialTimezone !== selectedTimezone && optionExists(initialTimezone)) {
        timezoneSelect.value = initialTimezone;
        fetchSnapshot(initialTimezone);
    }

    secondTimer = window.setInterval(updateLiveClock, 1000);
    refreshTimer = window.setInterval(() => {
        fetchSnapshot(selectedTimezone);
    }, 60000);

    window.addEventListener('beforeunload', () => {
        if (secondTimer) {
            window.clearInterval(secondTimer);
        }
        if (refreshTimer) {
            window.clearInterval(refreshTimer);
        }
    }, {once: true});
})();
