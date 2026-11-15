(() => {
    const input = document.getElementById('symbol');
    const category = document.getElementById('category');
    const suggestions = document.getElementById('trade-idea-symbol-suggestions');
    const catalogNode = document.getElementById('trade-idea-symbol-catalog');

    if (! input || ! category || ! suggestions || ! catalogNode) {
        return;
    }

    let catalog = {};
    let matches = [];
    let activeIndex = -1;
    let previousCategory = category.value;
    let autoName = null;

    try {
        catalog = JSON.parse(catalogNode.textContent || '{}');
    } catch (_) {
        return;
    }

    const instrumentName = document.getElementById('instrument_name');
    const marketRegion = document.getElementById('market_region');

    const hide = () => {
        suggestions.hidden = true;
        suggestions.replaceChildren();
        input.setAttribute('aria-expanded', 'false');
        activeIndex = -1;
        matches = [];
    };

    const select = (item) => {
        input.value = item.symbol;

        if (instrumentName) {
            instrumentName.value = item.name || item.symbol;
            autoName = instrumentName.value;
        }

        if (category.value === 'STOCKS' && marketRegion && item.region) {
            marketRegion.value = item.region;
            marketRegion.dispatchEvent(new Event('change', { bubbles: true }));
        }

        hide();
        input.focus();
    };

    const render = () => {
        const query = input.value.trim().toUpperCase();

        if (! query) {
            hide();
            return;
        }

        const region = category.value === 'STOCKS'
            ? (marketRegion?.value || '')
            : '';

        matches = (catalog[category.value] || [])
            .filter((item) => ! region || ! item.region || item.region === region)
            .map((item) => {
                const symbol = item.symbol.toUpperCase();
                const name = (item.name || '').toUpperCase();
                const provider = (item.provider_symbol || '').toUpperCase();
                const exchange = (item.exchange || '').toUpperCase();
                let score = 99;

                if (symbol.startsWith(query)) score = 0;
                else if (name.startsWith(query)) score = 1;
                else if (provider.startsWith(query)) score = 2;
                else if (symbol.includes(query)) score = 3;
                else if (name.includes(query) || provider.includes(query) || exchange.includes(query)) score = 4;

                return { item, score };
            })
            .filter((result) => result.score < 99)
            .sort((left, right) => left.score - right.score || left.item.symbol.localeCompare(right.item.symbol))
            .slice(0, 8)
            .map((result) => result.item);

        suggestions.replaceChildren();
        activeIndex = -1;

        if (! matches.length) {
            const message = document.createElement('div');
            message.className = 'ti-symbol-empty';
            message.setAttribute('role', 'status');
            message.textContent = 'No catalog match. Verify the broker symbol or enter it manually.';
            suggestions.append(message);
            suggestions.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            return;
        }

        matches.forEach((item, index) => {
            const button = document.createElement('button');
            const symbol = document.createElement('strong');
            const description = document.createElement('span');
            const meta = [item.name, item.exchange, item.region]
                .filter(Boolean)
                .join(' · ');

            button.type = 'button';
            button.className = 'ti-symbol-suggestion';
            button.setAttribute('role', 'option');
            button.setAttribute('data-index', String(index));
            symbol.textContent = item.symbol;
            description.textContent = meta || item.provider_symbol || item.symbol;
            button.append(symbol, description);
            button.addEventListener('mousedown', (event) => event.preventDefault());
            button.addEventListener('click', () => select(item));
            suggestions.append(button);
        });

        suggestions.hidden = false;
        input.setAttribute('aria-expanded', 'true');
    };

    const activate = (index) => {
        const options = [...suggestions.querySelectorAll('.ti-symbol-suggestion')];

        if (! options.length) return;

        activeIndex = (index + options.length) % options.length;
        options.forEach((option, optionIndex) => {
            option.classList.toggle('is-active', optionIndex === activeIndex);
        });
        options[activeIndex].scrollIntoView({ block: 'nearest' });
    };

    input.addEventListener('input', () => {
        input.value = input.value.toUpperCase();
        if (instrumentName && autoName && instrumentName.value === autoName) instrumentName.value = '';
        autoName = null;
        const exact = (catalog[category.value] || []).find(item =>
            item.symbol.toUpperCase() === input.value.trim()
            && (category.value !== 'STOCKS' || !marketRegion?.value || !item.region || item.region === marketRegion.value)
        );
        if (exact && instrumentName && !instrumentName.value.trim()) {
            instrumentName.value = exact.name || exact.symbol;
            autoName = instrumentName.value;
        }
        render();
    });
    input.addEventListener('focus', render);
    input.addEventListener('keydown', (event) => {
        if (suggestions.hidden) return;

        if (event.key === 'ArrowDown') {
            event.preventDefault();
            activate(activeIndex + 1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            activate(activeIndex - 1);
        } else if (event.key === 'Enter' && matches.length) {
            event.preventDefault();
            select(matches[activeIndex >= 0 ? activeIndex : 0]);
        } else if (event.key === 'Escape') {
            hide();
        }
    });

    category.addEventListener('change', () => {
        if (category.value !== previousCategory) {
            input.value = '';
            if (instrumentName) instrumentName.value = '';
            autoName = null;
            previousCategory = category.value;
        }
        hide();
        input.placeholder = {
            FOREX: 'Type EURUSD, GBPUSD…',
            CRYPTO: 'Type BTCUSD, BTCUSDT…',
            DERIV: 'Type Volatility, Boom, Crash…',
            COMMODITY: 'Type XAUUSD, XAGUSD, WTI…',
            STOCKS: 'Type AAPL, RELIANCE, JKH…',
        }[category.value] || 'Type a symbol';
    });
    marketRegion?.addEventListener('change', render);
    document.addEventListener('click', (event) => {
        if (! event.target.closest('.ti-symbol-autocomplete')) hide();
    });
    category.dispatchEvent(new Event('change'));
})();
