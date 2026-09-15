(() => {
    'use strict';

    const root = document.getElementById('riskRewardCalculatorApp');

    if (!root || root.dataset.initialized === 'true') {
        return;
    }

    root.dataset.initialized = 'true';

    const form = document.getElementById('riskRewardForm');
    const marketDataNode = document.getElementById('riskRewardMarketData');
    const marketField = document.getElementById('riskRewardMarket');
    const instrumentInput = document.getElementById('riskRewardInstrument');
    const instrumentList = document.getElementById('riskRewardInstrumentList');
    const instrumentHelp = document.getElementById('riskRewardInstrumentHelp');
    const sideField = document.getElementById('riskRewardSide');
    const message = document.getElementById('riskRewardFormMessage');
    const calculateButton = document.getElementById('riskRewardCalculateButton');
    const resetButton = document.getElementById('riskRewardResetButton');
    const marketDescription = document.getElementById('riskRewardMarketDescription');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    if (!form || !marketDataNode || !marketField || !instrumentInput || !instrumentList) {
        return;
    }

    let markets = {};

    try {
        markets = JSON.parse(marketDataNode.textContent || '{}');
    } catch (error) {
        showMessage('Calculator instrument data could not be loaded. Refresh the page and try again.');
        return;
    }

    const defaultMarket = root.dataset.defaultMarket || Object.keys(markets)[0] || 'forex';

    const resultNodes = {
        lotSize: document.getElementById('riskRewardLotSize'),
        lotStatus: document.getElementById('riskRewardLotStatus'),
        actualRisk: document.getElementById('riskRewardActualRisk'),
        potentialProfit: document.getElementById('riskRewardPotentialProfit'),
        positionUnits: document.getElementById('riskRewardPositionUnits'),
        riskUtilization: document.getElementById('riskRewardRiskUtilization'),
        ratio: document.getElementById('riskRewardRatio'),
        perLot: document.getElementById('riskRewardPerLot'),
        slDistance: document.getElementById('riskRewardSlDistance'),
        tpDistance: document.getElementById('riskRewardTpDistance'),
        warning: document.getElementById('riskRewardWarning'),
        specSymbol: document.getElementById('riskRewardSpecSymbol'),
        contractSize: document.getElementById('riskRewardContractSize'),
        minLot: document.getElementById('riskRewardMinLot'),
        maxLot: document.getElementById('riskRewardMaxLot'),
        lotStep: document.getElementById('riskRewardLotStep'),
        specProfile: document.getElementById('riskRewardSpecProfile'),
        specStatus: document.getElementById('riskRewardSpecStatus'),
        specSource: document.getElementById('riskRewardSpecSource'),
        specReviewed: document.getElementById('riskRewardSpecReviewed'),
    };

    const moneyFormatter = new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });

    const quantityFormatter = new Intl.NumberFormat('en-US', {
        maximumFractionDigits: 8,
    });

    function currentMarket() {
        return markets[marketField.value] || null;
    }

    function normalizeSymbol(value) {
        return String(value || '').trim().toUpperCase();
    }

    function currentInstrument() {
        const market = currentMarket();
        const symbol = normalizeSymbol(instrumentInput.value);

        if (!market || !market.instruments || !symbol) {
            return null;
        }

        return market.instruments[symbol] || null;
    }

    function populateInstrumentList() {
        const market = currentMarket();
        instrumentList.replaceChildren();

        if (!market || !market.instruments) {
            return;
        }

        Object.entries(market.instruments).forEach(([symbol, instrument]) => {
            const option = document.createElement('option');
            option.value = symbol;
            option.label = instrument?.name ? `${symbol} — ${instrument.name}` : symbol;
            instrumentList.appendChild(option);
        });
    }

    function updateMarketDescription() {
        const market = currentMarket();
        marketDescription.textContent = market?.description || '';
    }

    function updateInstrumentPreview() {
        const market = currentMarket();
        const instrument = currentInstrument();
        const symbol = normalizeSymbol(instrumentInput.value);

        if (!instrument) {
            instrumentHelp.textContent = symbol
                ? 'Select an exact symbol from the available list.'
                : 'Start typing a symbol, for example EURUSD.';
            clearSpecification();
            return;
        }

        const status = instrument.spec_status || market?.spec_status || 'REFERENCE';
        instrumentHelp.textContent = `${instrument.name} · ${instrument.spec_profile || 'TradeX reference profile'} · ${status}`;
        renderSpecification(symbol, instrument, market);
    }

    function renderSpecification(symbol, instrument, market = null) {
        resultNodes.specSymbol.textContent = symbol || '—';
        resultNodes.contractSize.textContent = formatNumber(instrument.contract_size);
        resultNodes.minLot.textContent = formatNumber(instrument.min_lot);
        resultNodes.maxLot.textContent = formatNumber(instrument.max_lot);
        resultNodes.lotStep.textContent = formatNumber(instrument.lot_step);
        resultNodes.specProfile.textContent = instrument.spec_profile || 'TradeX reference';
        resultNodes.specStatus.textContent = instrument.spec_status || market?.spec_status || 'REFERENCE';
        resultNodes.specSource.textContent = instrument.spec_source || market?.spec_source || 'TradeX reference profile';
        resultNodes.specReviewed.textContent = instrument.spec_verified_at || market?.spec_verified_at || 'Broker confirmation required';
    }

    function clearSpecification() {
        resultNodes.specSymbol.textContent = '—';
        resultNodes.contractSize.textContent = '—';
        resultNodes.minLot.textContent = '—';
        resultNodes.maxLot.textContent = '—';
        resultNodes.lotStep.textContent = '—';
        resultNodes.specProfile.textContent = '—';
        resultNodes.specStatus.textContent = '—';
        resultNodes.specSource.textContent = '—';
        resultNodes.specReviewed.textContent = '—';
    }

    function selectMarket(marketKey, clearInputs = true) {
        if (!Object.prototype.hasOwnProperty.call(markets, marketKey)) {
            return;
        }

        marketField.value = marketKey;

        root.querySelectorAll('[data-market-key]').forEach((button) => {
            const active = button.dataset.marketKey === marketKey;
            button.classList.toggle('active', active);
            button.setAttribute('aria-selected', active ? 'true' : 'false');
        });

        populateInstrumentList();
        updateMarketDescription();

        if (clearInputs) {
            instrumentInput.value = '';
            clearTradePrices();
            clearErrors();
            hideMessage();
            resetResults();
            updateInstrumentPreview();
        }
    }

    function setSide(side) {
        if (!['BUY', 'SELL'].includes(side)) {
            return;
        }

        sideField.value = side;

        root.querySelectorAll('[data-side]').forEach((button) => {
            const active = button.dataset.side === side;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function clearTradePrices() {
        ['riskRewardEntry', 'riskRewardStopLoss', 'riskRewardTakeProfit'].forEach((id) => {
            const field = document.getElementById(id);
            if (field) {
                field.value = '';
            }
        });
    }

    function resetResults() {
        resultNodes.lotSize.textContent = '—';
        resultNodes.lotStatus.textContent = 'Complete the trade setup to calculate.';
        resultNodes.actualRisk.textContent = '—';
        resultNodes.potentialProfit.textContent = '—';
        resultNodes.positionUnits.textContent = '—';
        resultNodes.riskUtilization.textContent = '—';
        resultNodes.ratio.textContent = '—';
        resultNodes.perLot.textContent = '—';
        resultNodes.slDistance.textContent = '—';
        resultNodes.tpDistance.textContent = '—';
        resultNodes.warning.hidden = true;
        resultNodes.warning.textContent = '';
    }

    function renderResult(data) {
        const specification = data.specification || {};
        const recommendedLot = data.recommended_lot;

        if (recommendedLot === null || recommendedLot === undefined) {
            resultNodes.lotSize.textContent = 'Not Tradable';
            resultNodes.lotStatus.textContent = 'The configured minimum volume would exceed the requested risk.';
        } else {
            resultNodes.lotSize.textContent = `${formatNumber(recommendedLot)} lot`;
            resultNodes.lotStatus.textContent = `${data.symbol} · ${data.side} · requested risk ${money(data.risk_amount)}`;
        }

        resultNodes.actualRisk.textContent = nullableMoney(data.actual_risk);
        resultNodes.potentialProfit.textContent = nullableMoney(data.potential_profit);
        resultNodes.positionUnits.textContent = data.position_units === null || data.position_units === undefined
            ? '—'
            : formatNumber(data.position_units);
        resultNodes.riskUtilization.textContent = data.risk_utilization_percent === null || data.risk_utilization_percent === undefined
            ? '—'
            : `${formatFixed(data.risk_utilization_percent, 2)}%`;
        resultNodes.ratio.textContent = `1 : ${formatFixed(data.risk_reward_ratio, 2)}`;
        resultNodes.perLot.textContent = money(data.risk_per_lot);
        resultNodes.slDistance.textContent = `${formatFixed(data.stop_loss_distance, 2)} ${data.distance_label || 'points'}`;
        resultNodes.tpDistance.textContent = `${formatFixed(data.take_profit_distance, 2)} ${data.distance_label || 'points'}`;

        resultNodes.specSymbol.textContent = data.symbol || '—';
        resultNodes.contractSize.textContent = formatNumber(specification.contract_size);
        resultNodes.minLot.textContent = formatNumber(specification.min_lot);
        resultNodes.maxLot.textContent = formatNumber(specification.max_lot);
        resultNodes.lotStep.textContent = formatNumber(specification.lot_step);
        resultNodes.specProfile.textContent = specification.profile || 'TradeX reference';
        resultNodes.specStatus.textContent = specification.status || 'REFERENCE';
        resultNodes.specSource.textContent = specification.source || 'TradeX reference profile';
        resultNodes.specReviewed.textContent = specification.verified_at || 'Broker confirmation required';

        const warnings = Array.isArray(data.warnings) ? data.warnings.filter(Boolean) : [];
        resultNodes.warning.hidden = warnings.length === 0;
        resultNodes.warning.textContent = warnings.join(' ');
    }

    function money(value) {
        const number = Number(value);
        return Number.isFinite(number) ? moneyFormatter.format(number) : '—';
    }

    function nullableMoney(value) {
        return value === null || value === undefined ? '—' : money(value);
    }

    function formatNumber(value) {
        const number = Number(value);
        return Number.isFinite(number) ? quantityFormatter.format(number) : '—';
    }

    function formatFixed(value, digits) {
        const number = Number(value);
        return Number.isFinite(number) ? number.toFixed(digits) : '—';
    }

    function clearErrors() {
        root.querySelectorAll('.is-invalid').forEach((field) => {
            field.classList.remove('is-invalid');
            field.removeAttribute('aria-invalid');
        });

        root.querySelectorAll('[data-error-for]').forEach((node) => {
            node.textContent = '';
        });
    }

    function renderErrors(errors) {
        if (!errors || typeof errors !== 'object') {
            return;
        }

        Object.entries(errors).forEach(([fieldName, messages]) => {
            const input = form.elements.namedItem(fieldName);
            const errorNode = root.querySelector(`[data-error-for="${fieldName}"]`);
            const text = Array.isArray(messages) ? messages[0] : String(messages || '');

            if (input instanceof HTMLElement) {
                input.classList.add('is-invalid');
                input.setAttribute('aria-invalid', 'true');
            }

            if (errorNode) {
                errorNode.textContent = text;
            }
        });
    }

    function showMessage(text) {
        if (!message) {
            return;
        }

        message.textContent = text;
        message.hidden = false;
    }

    function hideMessage() {
        if (!message) {
            return;
        }

        message.textContent = '';
        message.hidden = true;
    }

    function payloadFromForm() {
        const data = new FormData(form);

        return {
            market: String(data.get('market') || ''),
            symbol: normalizeSymbol(data.get('symbol')),
            side: String(data.get('side') || '').toUpperCase(),
            entry: String(data.get('entry') || ''),
            stop_loss: String(data.get('stop_loss') || ''),
            take_profit: String(data.get('take_profit') || ''),
            risk_amount: String(data.get('risk_amount') || ''),
        };
    }

    async function submitCalculator(event) {
        event.preventDefault();
        clearErrors();
        hideMessage();

        const payload = payloadFromForm();
        const instrument = currentInstrument();

        if (!instrument) {
            const symbolError = root.querySelector('[data-error-for="symbol"]');
            instrumentInput.classList.add('is-invalid');
            instrumentInput.setAttribute('aria-invalid', 'true');
            if (symbolError) {
                symbolError.textContent = 'Select a valid instrument from the current market.';
            }
            return;
        }

        const originalButtonText = calculateButton?.textContent || 'Calculate Position';

        if (calculateButton) {
            calculateButton.disabled = true;
            calculateButton.textContent = 'Calculating...';
        }

        try {
            const response = await fetch(root.dataset.calculateEndpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify(payload),
            });

            let body = null;

            try {
                body = await response.json();
            } catch (error) {
                body = null;
            }

            if (!response.ok || !body?.success) {
                renderErrors(body?.errors || {});
                showMessage(body?.message || 'Unable to calculate this position. Check the inputs and try again.');
                return;
            }

            renderResult(body.data || {});
        } catch (error) {
            showMessage('The calculator request failed. Check your connection and try again.');
        } finally {
            if (calculateButton) {
                calculateButton.disabled = false;
                calculateButton.textContent = originalButtonText.trim();
            }
        }
    }

    function resetCalculator() {
        form.reset();
        setSide('BUY');
        selectMarket(defaultMarket, false);
        instrumentInput.value = '';
        clearTradePrices();

        const riskAmount = document.getElementById('riskRewardAmount');
        if (riskAmount) {
            riskAmount.value = '';
        }

        clearErrors();
        hideMessage();
        resetResults();
        updateInstrumentPreview();
    }

    root.querySelectorAll('[data-market-key]').forEach((button) => {
        button.addEventListener('click', () => {
            selectMarket(button.dataset.marketKey || defaultMarket);
        });
    });

    root.querySelectorAll('[data-side]').forEach((button) => {
        button.addEventListener('click', () => {
            setSide(button.dataset.side || 'BUY');
            clearErrors();
            hideMessage();
            resetResults();
        });
    });

    instrumentInput.addEventListener('input', () => {
        instrumentInput.value = instrumentInput.value.toUpperCase();
        clearErrors();
        hideMessage();
        resetResults();
        updateInstrumentPreview();
    });

    form.addEventListener('submit', submitCalculator);
    resetButton?.addEventListener('click', resetCalculator);

    selectMarket(defaultMarket, false);
    setSide('BUY');
    resetResults();
    updateInstrumentPreview();
})();
