(() => {
    'use strict';

    const root = document.getElementById('riskRewardCalculatorApp');
    const form = document.getElementById('riskRewardForm');
    const marketField = document.getElementById('riskRewardMarket');
    const marketDataNode = document.getElementById('riskRewardMarketData');

    if (!root || !form || !marketField || !marketDataNode) {
        return;
    }

    let markets = {};

    try {
        markets = JSON.parse(marketDataNode.textContent || '{}');
    } catch (error) {
        return;
    }

    if (!markets.binance_futures) {
        return;
    }

    const riskAmount = document.getElementById('riskRewardAmount');
    const riskField = riskAmount?.closest('.risk-reward-field');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const calculateButton = document.getElementById('riskRewardCalculateButton');
    const message = document.getElementById('riskRewardFormMessage');

    if (!riskField || !riskAmount) {
        return;
    }

    const ui = createFuturesFields();
    const originalLabels = captureResultLabels();

    function isBinanceMode() {
        return marketField.value === 'binance_futures';
    }

    function createFuturesFields() {
        const container = document.createElement('div');
        container.id = 'riskRewardBinanceFields';
        container.hidden = true;

        const modeField = document.createElement('fieldset');
        modeField.className = 'risk-reward-field risk-reward-side-field';
        modeField.innerHTML = `
            <legend>Calculation Mode</legend>
            <div class="risk-reward-side-toggle">
                <button type="button" class="risk-reward-side-button active" data-binance-mode="RISK_BASED" aria-pressed="true">RISK BASED</button>
                <button type="button" class="risk-reward-side-button" data-binance-mode="MARGIN_BASED" aria-pressed="false">MARGIN BASED</button>
            </div>
            <input type="hidden" name="calculation_mode" id="riskRewardCalculationMode" value="RISK_BASED">
            <small class="risk-reward-field-help" id="riskRewardModeHelp">Choose your maximum loss; leverage changes required margin, not the selected risk budget.</small>
            <div class="risk-reward-field-error" data-error-for="calculation_mode"></div>
        `;

        const leverageField = document.createElement('div');
        leverageField.className = 'risk-reward-field';
        leverageField.innerHTML = `
            <label for="riskRewardLeverage">Leverage</label>
            <select id="riskRewardLeverage" name="leverage" required></select>
            <small class="risk-reward-field-help">Planning selector only. Binance live maximum leverage depends on symbol and position-notional tier.</small>
            <div class="risk-reward-field-error" data-error-for="leverage"></div>
        `;

        const marginField = document.createElement('div');
        marginField.className = 'risk-reward-field';
        marginField.id = 'riskRewardMarginField';
        marginField.hidden = true;
        marginField.innerHTML = `
            <label for="riskRewardMarginAmount">Margin Amount</label>
            <div class="risk-reward-money-control">
                <span>$</span>
                <input id="riskRewardMarginAmount" name="margin_amount" type="number" min="0.01" step="0.01" inputmode="decimal" placeholder="100.00">
                <strong>USDT</strong>
            </div>
            <small class="risk-reward-field-help">Position notional is estimated as margin × selected leverage. Actual SL risk is calculated from that position size.</small>
            <div class="risk-reward-field-error" data-error-for="margin_amount"></div>
        `;

        container.append(modeField, leverageField, marginField);
        riskField.insertAdjacentElement('afterend', container);

        const leverageSelect = leverageField.querySelector('#riskRewardLeverage');
        const leverageSettings = markets.binance_futures.leverage || {};
        const min = Number(leverageSettings.min || 1);
        const max = Number(leverageSettings.max || 125);
        const defaultValue = Number(leverageSettings.default || 10);

        for (let leverage = min; leverage <= max; leverage += 1) {
            const option = document.createElement('option');
            option.value = String(leverage);
            option.textContent = `${leverage}x`;
            option.selected = leverage === defaultValue;
            leverageSelect.appendChild(option);
        }

        modeField.querySelectorAll('[data-binance-mode]').forEach((button) => {
            button.addEventListener('click', () => setCalculationMode(button.dataset.binanceMode || 'RISK_BASED'));
        });

        leverageSelect.addEventListener('change', resetDisplayedResult);
        marginField.querySelector('#riskRewardMarginAmount')?.addEventListener('input', resetDisplayedResult);

        return {
            container,
            modeField,
            modeInput: modeField.querySelector('#riskRewardCalculationMode'),
            modeHelp: modeField.querySelector('#riskRewardModeHelp'),
            leverage: leverageSelect,
            marginField,
            marginInput: marginField.querySelector('#riskRewardMarginAmount'),
        };
    }

    function captureResultLabels() {
        return {
            primary: document.querySelector('.risk-reward-primary-result > span')?.textContent || 'Recommended Lot Size',
            position: document.getElementById('riskRewardPositionUnits')?.parentElement?.querySelector('span')?.textContent || 'Position Units',
            utilization: document.getElementById('riskRewardRiskUtilization')?.parentElement?.querySelector('span')?.textContent || 'Risk Utilization',
            perLot: document.getElementById('riskRewardPerLot')?.parentElement?.querySelector('span')?.textContent || 'Risk Per 1 Lot',
        };
    }

    function setCalculationMode(mode) {
        if (!['RISK_BASED', 'MARGIN_BASED'].includes(mode)) {
            return;
        }

        ui.modeInput.value = mode;
        ui.modeField.querySelectorAll('[data-binance-mode]').forEach((button) => {
            const active = button.dataset.binanceMode === mode;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });

        const marginBased = mode === 'MARGIN_BASED';
        riskField.hidden = marginBased;
        ui.marginField.hidden = !marginBased;
        riskAmount.required = !marginBased;
        ui.marginInput.required = marginBased;

        if (marginBased) {
            riskAmount.value = '';
            ui.modeHelp.textContent = 'Choose margin capital and leverage; TradeX derives notional size and the actual loss at Stop Loss.';
        } else {
            ui.marginInput.value = '';
            ui.modeHelp.textContent = 'Choose your maximum loss; leverage changes required margin, not the selected risk budget.';
        }

        resetDisplayedResult();
    }

    function updateMarketUi() {
        const active = isBinanceMode();
        ui.container.hidden = !active;

        if (active) {
            setCalculationMode(ui.modeInput.value || 'RISK_BASED');
            applyBinanceLabels();
        } else {
            riskField.hidden = false;
            riskAmount.required = true;
            ui.marginInput.required = false;
            restoreLabels();
        }
    }

    function applyBinanceLabels() {
        setLabel('.risk-reward-primary-result > span', 'Position Quantity');
        setParentLabel('riskRewardPositionUnits', 'Position Notional');
        setParentLabel('riskRewardRiskUtilization', 'Required Initial Margin');
        setParentLabel('riskRewardPerLot', 'Selected Leverage');
    }

    function restoreLabels() {
        setLabel('.risk-reward-primary-result > span', originalLabels.primary);
        setParentLabel('riskRewardPositionUnits', originalLabels.position);
        setParentLabel('riskRewardRiskUtilization', originalLabels.utilization);
        setParentLabel('riskRewardPerLot', originalLabels.perLot);
    }

    function setLabel(selector, text) {
        const node = document.querySelector(selector);
        if (node) node.textContent = text;
    }

    function setParentLabel(id, text) {
        const node = document.getElementById(id)?.parentElement?.querySelector('span');
        if (node) node.textContent = text;
    }

    function clearErrors() {
        root.querySelectorAll('.is-invalid').forEach((field) => {
            field.classList.remove('is-invalid');
            field.removeAttribute('aria-invalid');
        });
        root.querySelectorAll('[data-error-for]').forEach((node) => { node.textContent = ''; });
    }

    function renderErrors(errors) {
        if (!errors || typeof errors !== 'object') return;

        Object.entries(errors).forEach(([fieldName, messages]) => {
            const input = form.elements.namedItem(fieldName);
            const errorNode = root.querySelector(`[data-error-for="${fieldName}"]`);
            const text = Array.isArray(messages) ? messages[0] : String(messages || '');
            if (input instanceof HTMLElement) {
                input.classList.add('is-invalid');
                input.setAttribute('aria-invalid', 'true');
            }
            if (errorNode) errorNode.textContent = text;
        });
    }

    function showMessage(text) {
        if (!message) return;
        message.textContent = text;
        message.hidden = false;
    }

    function hideMessage() {
        if (!message) return;
        message.textContent = '';
        message.hidden = true;
    }

    function resetDisplayedResult() {
        if (!isBinanceMode()) return;
        const values = [
            'riskRewardLotSize', 'riskRewardActualRisk', 'riskRewardPotentialProfit',
            'riskRewardPositionUnits', 'riskRewardRiskUtilization', 'riskRewardRatio',
            'riskRewardPerLot', 'riskRewardSlDistance', 'riskRewardTpDistance',
        ];
        values.forEach((id) => {
            const node = document.getElementById(id);
            if (node) node.textContent = '—';
        });
        const status = document.getElementById('riskRewardLotStatus');
        if (status) status.textContent = 'Complete the Binance Futures setup to calculate.';
        const warning = document.getElementById('riskRewardWarning');
        if (warning) {
            warning.hidden = true;
            warning.textContent = '';
        }
    }

    function number(value, digits = 8) {
        const parsed = Number(value);
        if (!Number.isFinite(parsed)) return '—';
        return new Intl.NumberFormat('en-US', { maximumFractionDigits: digits }).format(parsed);
    }

    function usdt(value) {
        const parsed = Number(value);
        if (!Number.isFinite(parsed)) return '—';
        return `${new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(parsed)} USDT`;
    }

    function renderResult(data) {
        const futures = data.futures || {};
        const spec = data.specification || {};
        const quantity = futures.position_quantity;

        document.getElementById('riskRewardLotSize').textContent = quantity == null ? 'Not Tradable' : `${number(quantity)} ${spec.base_currency || ''}`.trim();
        document.getElementById('riskRewardLotStatus').textContent = `${data.symbol} · ${data.side} · ${String(futures.calculation_mode || '').replace('_', ' ')} · ${futures.leverage}x`;
        document.getElementById('riskRewardActualRisk').textContent = usdt(data.actual_risk);
        document.getElementById('riskRewardPotentialProfit').textContent = usdt(data.potential_profit);
        document.getElementById('riskRewardPositionUnits').textContent = usdt(futures.position_notional);
        document.getElementById('riskRewardRiskUtilization').textContent = usdt(futures.required_initial_margin);
        document.getElementById('riskRewardRatio').textContent = `1 : ${Number(data.risk_reward_ratio || 0).toFixed(2)}`;
        document.getElementById('riskRewardPerLot').textContent = `${futures.leverage}x`;
        document.getElementById('riskRewardSlDistance').textContent = `${Number(futures.stop_loss_percent || 0).toFixed(2)}%`;
        document.getElementById('riskRewardTpDistance').textContent = `${Number(futures.take_profit_percent || 0).toFixed(2)}%`;

        document.getElementById('riskRewardSpecSymbol').textContent = data.symbol || '—';
        document.getElementById('riskRewardContractSize').textContent = '1 base asset';
        document.getElementById('riskRewardMinLot').textContent = 'Live exchangeInfo';
        document.getElementById('riskRewardMaxLot').textContent = 'Live exchangeInfo';
        document.getElementById('riskRewardLotStep').textContent = 'Live exchangeInfo';
        document.getElementById('riskRewardSpecProfile').textContent = spec.profile || 'Binance USDⓈ-M perpetual';
        document.getElementById('riskRewardSpecStatus').textContent = spec.status || 'PARTIALLY VERIFIED';
        document.getElementById('riskRewardSpecSource').textContent = spec.source || 'Binance official Futures documentation';
        document.getElementById('riskRewardSpecReviewed').textContent = spec.verified_at || 'Live broker check required';

        const warnings = Array.isArray(data.warnings) ? data.warnings.filter(Boolean) : [];
        const warning = document.getElementById('riskRewardWarning');
        warning.hidden = warnings.length === 0;
        warning.textContent = warnings.join(' ');
    }

    async function submitBinance(event) {
        if (!isBinanceMode()) return;

        event.preventDefault();
        event.stopImmediatePropagation();
        clearErrors();
        hideMessage();

        const mode = ui.modeInput.value || 'RISK_BASED';
        const payload = {
            market: 'binance_futures',
            symbol: String(document.getElementById('riskRewardInstrument')?.value || '').trim().toUpperCase(),
            side: String(document.getElementById('riskRewardSide')?.value || 'BUY').toUpperCase(),
            entry: String(document.getElementById('riskRewardEntry')?.value || ''),
            stop_loss: String(document.getElementById('riskRewardStopLoss')?.value || ''),
            take_profit: String(document.getElementById('riskRewardTakeProfit')?.value || ''),
            calculation_mode: mode,
            leverage: String(ui.leverage.value || '10'),
            risk_amount: mode === 'RISK_BASED' ? String(riskAmount.value || '') : null,
            margin_amount: mode === 'MARGIN_BASED' ? String(ui.marginInput.value || '') : null,
        };

        const originalText = calculateButton?.textContent || 'Calculate Position';
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
            try { body = await response.json(); } catch (error) { body = null; }

            if (!response.ok || !body?.success) {
                renderErrors(body?.errors || {});
                showMessage(body?.message || 'Unable to calculate this Binance Futures position.');
                return;
            }

            renderResult(body.data || {});
        } catch (error) {
            showMessage('The Binance Futures calculator request failed. Check your connection and try again.');
        } finally {
            if (calculateButton) {
                calculateButton.disabled = false;
                calculateButton.textContent = originalText.trim();
            }
        }
    }

    form.addEventListener('submit', submitBinance, true);

    root.querySelectorAll('[data-market-key]').forEach((button) => {
        button.addEventListener('click', () => window.setTimeout(updateMarketUi, 0));
    });

    document.getElementById('riskRewardResetButton')?.addEventListener('click', () => {
        window.setTimeout(() => {
            ui.leverage.value = String(markets.binance_futures.leverage?.default || 10);
            setCalculationMode('RISK_BASED');
            updateMarketUi();
        }, 0);
    });

    updateMarketUi();
})();
