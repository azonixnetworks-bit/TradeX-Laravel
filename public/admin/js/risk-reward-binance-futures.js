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
    const advancedResults = createAdvancedResults();
    const originalLabels = captureResultLabels();

    function isBinanceMode() {
        return marketField.value === 'binance_futures';
    }

    function numericDefault(value, fallback = 0) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed : fallback;
    }

    function createFuturesFields() {
        const settings = markets.binance_futures;
        const costDefaults = settings.cost_assumptions || {};
        const liquidationDefaults = settings.liquidation || {};

        const container = document.createElement('div');
        container.id = 'riskRewardBinanceFields';
        container.className = 'risk-reward-binance-fields';
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
            <small class="risk-reward-field-help" id="riskRewardModeHelp">Choose your maximum all-in stop risk; fee/slippage/funding assumptions are included in sizing.</small>
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
            <small class="risk-reward-field-help">Position notional is estimated as margin × selected leverage. All-in SL risk is shown after costs.</small>
            <div class="risk-reward-field-error" data-error-for="margin_amount"></div>
        `;

        const marginModeField = document.createElement('div');
        marginModeField.className = 'risk-reward-field';
        marginModeField.innerHTML = `
            <label for="riskRewardMarginMode">Margin Mode</label>
            <select id="riskRewardMarginMode" name="margin_mode">
                <option value="ISOLATED">Isolated</option>
                <option value="CROSS">Cross</option>
            </select>
            <small class="risk-reward-field-help">Exact Cross liquidation requires account-wide wallet, other positions and unrealized PnL, so TradeX will not fake a Cross liquidation price.</small>
            <div class="risk-reward-field-error" data-error-for="margin_mode"></div>
        `;

        const costPanel = document.createElement('div');
        costPanel.className = 'risk-reward-binance-subpanel';
        costPanel.innerHTML = `
            <div class="risk-reward-binance-subpanel-heading">
                <strong>Cost Assumptions</strong>
                <span>Editable · confirm live Binance account rates</span>
            </div>
            <div class="risk-reward-binance-input-grid">
                <div class="risk-reward-field">
                    <label for="riskRewardEntryFeeRate">Entry Fee %</label>
                    <input id="riskRewardEntryFeeRate" name="entry_fee_rate_percent" type="number" min="0" max="100" step="0.0001" inputmode="decimal">
                    <small class="risk-reward-field-help">Reference regular-user taker example: ${numericDefault(costDefaults.reference_taker_fee_percent, 0.05)}%.</small>
                    <div class="risk-reward-field-error" data-error-for="entry_fee_rate_percent"></div>
                </div>
                <div class="risk-reward-field">
                    <label for="riskRewardExitFeeRate">Exit Fee %</label>
                    <input id="riskRewardExitFeeRate" name="exit_fee_rate_percent" type="number" min="0" max="100" step="0.0001" inputmode="decimal">
                    <small class="risk-reward-field-help">Use your expected maker/taker/VIP fee for the exit order.</small>
                    <div class="risk-reward-field-error" data-error-for="exit_fee_rate_percent"></div>
                </div>
                <div class="risk-reward-field">
                    <label for="riskRewardSlippage">Adverse Slippage %</label>
                    <input id="riskRewardSlippage" name="slippage_percent" type="number" min="0" max="100" step="0.0001" inputmode="decimal">
                    <small class="risk-reward-field-help">Optional planning buffer; this is not a Binance-guaranteed value.</small>
                    <div class="risk-reward-field-error" data-error-for="slippage_percent"></div>
                </div>
                <div class="risk-reward-field">
                    <label for="riskRewardFundingRate">Funding Rate % / Interval</label>
                    <input id="riskRewardFundingRate" name="funding_rate_percent" type="number" min="-100" max="100" step="0.0001" inputmode="decimal">
                    <small class="risk-reward-field-help">Positive rate: longs pay / shorts receive. Negative rate reverses the direction.</small>
                    <div class="risk-reward-field-error" data-error-for="funding_rate_percent"></div>
                </div>
                <div class="risk-reward-field">
                    <label for="riskRewardFundingIntervals">Funding Intervals Held</label>
                    <input id="riskRewardFundingIntervals" name="funding_intervals" type="number" min="0" max="1000" step="1" inputmode="numeric">
                    <small class="risk-reward-field-help">Enter 0 for a trade expected to close before any funding settlement.</small>
                    <div class="risk-reward-field-error" data-error-for="funding_intervals"></div>
                </div>
            </div>
        `;

        const liquidationPanel = document.createElement('div');
        liquidationPanel.className = 'risk-reward-binance-subpanel';
        liquidationPanel.id = 'riskRewardLiquidationInputs';
        liquidationPanel.innerHTML = `
            <div class="risk-reward-binance-subpanel-heading">
                <strong>Isolated Liquidation Inputs</strong>
                <span>Use current Binance leverage/margin tier</span>
            </div>
            <div class="risk-reward-binance-input-grid">
                <div class="risk-reward-field">
                    <label for="riskRewardMaintenanceMarginRate">Maintenance Margin Rate %</label>
                    <input id="riskRewardMaintenanceMarginRate" name="maintenance_margin_rate_percent" type="number" min="0.000001" max="99.999999" step="0.0001" inputmode="decimal" placeholder="Current tier MMR">
                    <div class="risk-reward-field-error" data-error-for="maintenance_margin_rate_percent"></div>
                </div>
                <div class="risk-reward-field">
                    <label for="riskRewardMaintenanceAmount">Maintenance Amount</label>
                    <div class="risk-reward-money-control">
                        <span>$</span>
                        <input id="riskRewardMaintenanceAmount" name="maintenance_amount" type="number" min="0" step="0.01" inputmode="decimal" placeholder="0.00">
                        <strong>USDT</strong>
                    </div>
                    <div class="risk-reward-field-error" data-error-for="maintenance_amount"></div>
                </div>
                <div class="risk-reward-field risk-reward-field-full">
                    <label for="riskRewardLiquidationWallet">Isolated Wallet Balance Override (optional)</label>
                    <div class="risk-reward-money-control">
                        <span>$</span>
                        <input id="riskRewardLiquidationWallet" name="liquidation_wallet_balance" type="number" min="0.01" step="0.01" inputmode="decimal" placeholder="Auto: selected/required isolated margin">
                        <strong>USDT</strong>
                    </div>
                    <small class="risk-reward-field-help">Useful when you add extra isolated margin. Leave blank to use the calculator margin.</small>
                    <div class="risk-reward-field-error" data-error-for="liquidation_wallet_balance"></div>
                </div>
            </div>
            <small class="risk-reward-field-help">Liquidation is triggered using Mark Price and live tier data. Treat this as an estimate, not an execution guarantee.</small>
        `;

        container.append(modeField, leverageField, marginField, marginModeField, costPanel, liquidationPanel);
        riskField.insertAdjacentElement('afterend', container);

        const leverageSelect = leverageField.querySelector('#riskRewardLeverage');
        const leverageSettings = settings.leverage || {};
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

        const marginMode = marginModeField.querySelector('#riskRewardMarginMode');
        marginMode.value = String(liquidationDefaults.default_margin_mode || 'ISOLATED');

        const fields = {
            entryFee: costPanel.querySelector('#riskRewardEntryFeeRate'),
            exitFee: costPanel.querySelector('#riskRewardExitFeeRate'),
            slippage: costPanel.querySelector('#riskRewardSlippage'),
            fundingRate: costPanel.querySelector('#riskRewardFundingRate'),
            fundingIntervals: costPanel.querySelector('#riskRewardFundingIntervals'),
            maintenanceRate: liquidationPanel.querySelector('#riskRewardMaintenanceMarginRate'),
            maintenanceAmount: liquidationPanel.querySelector('#riskRewardMaintenanceAmount'),
            liquidationWallet: liquidationPanel.querySelector('#riskRewardLiquidationWallet'),
        };

        fields.entryFee.value = String(numericDefault(costDefaults.default_entry_fee_percent, 0));
        fields.exitFee.value = String(numericDefault(costDefaults.default_exit_fee_percent, 0));
        fields.slippage.value = String(numericDefault(costDefaults.default_slippage_percent, 0));
        fields.fundingRate.value = String(numericDefault(costDefaults.default_funding_rate_percent, 0));
        fields.fundingIntervals.value = String(numericDefault(costDefaults.default_funding_intervals, 0));

        modeField.querySelectorAll('[data-binance-mode]').forEach((button) => {
            button.addEventListener('click', () => setCalculationMode(button.dataset.binanceMode || 'RISK_BASED'));
        });

        leverageSelect.addEventListener('change', resetDisplayedResult);
        marginField.querySelector('#riskRewardMarginAmount')?.addEventListener('input', resetDisplayedResult);
        marginMode.addEventListener('change', () => {
            updateLiquidationInputs();
            resetDisplayedResult();
        });
        Object.values(fields).forEach((field) => field?.addEventListener('input', resetDisplayedResult));

        return {
            container,
            modeField,
            modeInput: modeField.querySelector('#riskRewardCalculationMode'),
            modeHelp: modeField.querySelector('#riskRewardModeHelp'),
            leverage: leverageSelect,
            marginField,
            marginInput: marginField.querySelector('#riskRewardMarginAmount'),
            marginMode,
            liquidationPanel,
            ...fields,
        };
    }

    function createAdvancedResults() {
        const panel = document.createElement('div');
        panel.className = 'risk-reward-binance-results';
        panel.id = 'riskRewardBinanceAdvancedResults';
        panel.hidden = true;
        panel.innerHTML = `
            <div class="risk-reward-binance-subpanel-heading">
                <strong>Binance Futures All-in Estimate</strong>
                <span>Costs + liquidation planning</span>
            </div>
            <div class="risk-reward-result-grid">
                <div class="risk-reward-result-card is-highlight"><span>All-in Risk @ SL</span><strong id="riskRewardAllInStopRisk">—</strong></div>
                <div class="risk-reward-result-card is-highlight"><span>Net Profit @ TP</span><strong id="riskRewardNetTargetProfit">—</strong></div>
                <div class="risk-reward-result-card"><span>Entry Fee</span><strong id="riskRewardEstimatedEntryFee">—</strong></div>
                <div class="risk-reward-result-card"><span>Exit Fee @ SL</span><strong id="riskRewardEstimatedExitFee">—</strong></div>
                <div class="risk-reward-result-card"><span>Slippage @ SL</span><strong id="riskRewardEstimatedSlippage">—</strong></div>
                <div class="risk-reward-result-card"><span>Funding Estimate</span><strong id="riskRewardEstimatedFunding">—</strong></div>
                <div class="risk-reward-result-card"><span>Est. Liquidation</span><strong id="riskRewardEstimatedLiquidation">—</strong></div>
                <div class="risk-reward-result-card"><span>SL ↔ Liquidation Buffer</span><strong id="riskRewardLiquidationBuffer">—</strong></div>
            </div>
        `;

        const warning = document.getElementById('riskRewardWarning');
        if (warning) {
            warning.insertAdjacentElement('beforebegin', panel);
        }

        return panel;
    }

    function captureResultLabels() {
        return {
            primary: document.querySelector('.risk-reward-primary-result > span')?.textContent || 'Recommended Lot Size',
            actualRisk: document.getElementById('riskRewardActualRisk')?.parentElement?.querySelector('span')?.textContent || 'Actual Risk',
            potentialProfit: document.getElementById('riskRewardPotentialProfit')?.parentElement?.querySelector('span')?.textContent || 'Potential Profit',
            position: document.getElementById('riskRewardPositionUnits')?.parentElement?.querySelector('span')?.textContent || 'Position Units',
            utilization: document.getElementById('riskRewardRiskUtilization')?.parentElement?.querySelector('span')?.textContent || 'Risk Utilization',
            ratio: document.getElementById('riskRewardRatio')?.parentElement?.querySelector('span')?.textContent || 'Risk : Reward',
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
            ui.modeHelp.textContent = 'Choose margin capital and leverage; TradeX derives notional size and calculates all-in loss at Stop Loss after costs.';
        } else {
            ui.marginInput.value = '';
            ui.modeHelp.textContent = 'Choose your maximum all-in stop risk; fee/slippage/funding assumptions are included when sizing quantity.';
        }

        resetDisplayedResult();
    }

    function updateLiquidationInputs() {
        const isolated = ui.marginMode.value === 'ISOLATED';
        ui.liquidationPanel.classList.toggle('is-disabled', !isolated);
        [ui.maintenanceRate, ui.maintenanceAmount, ui.liquidationWallet].forEach((field) => {
            if (field) field.disabled = !isolated;
        });
    }

    function updateMarketUi() {
        const active = isBinanceMode();
        ui.container.hidden = !active;
        advancedResults.hidden = !active;

        if (active) {
            setCalculationMode(ui.modeInput.value || 'RISK_BASED');
            updateLiquidationInputs();
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
        setParentLabel('riskRewardActualRisk', 'All-in SL Risk');
        setParentLabel('riskRewardPotentialProfit', 'Net TP Profit');
        setParentLabel('riskRewardPositionUnits', 'Position Notional');
        setParentLabel('riskRewardRiskUtilization', 'Required Initial Margin');
        setParentLabel('riskRewardRatio', 'Gross Risk : Reward');
        setParentLabel('riskRewardPerLot', 'Selected Leverage');
    }

    function restoreLabels() {
        setLabel('.risk-reward-primary-result > span', originalLabels.primary);
        setParentLabel('riskRewardActualRisk', originalLabels.actualRisk);
        setParentLabel('riskRewardPotentialProfit', originalLabels.potentialProfit);
        setParentLabel('riskRewardPositionUnits', originalLabels.position);
        setParentLabel('riskRewardRiskUtilization', originalLabels.utilization);
        setParentLabel('riskRewardRatio', originalLabels.ratio);
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
            'riskRewardAllInStopRisk', 'riskRewardNetTargetProfit', 'riskRewardEstimatedEntryFee',
            'riskRewardEstimatedExitFee', 'riskRewardEstimatedSlippage', 'riskRewardEstimatedFunding',
            'riskRewardEstimatedLiquidation', 'riskRewardLiquidationBuffer',
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
        if (value === null || value === undefined || value === '') return '—';
        const parsed = Number(value);
        if (!Number.isFinite(parsed)) return '—';
        return `${new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(parsed)} USDT`;
    }

    function optionalValue(field) {
        const value = String(field?.value || '').trim();
        return value === '' ? null : value;
    }

    function renderResult(data) {
        const futures = data.futures || {};
        const costs = futures.costs || {};
        const liquidation = futures.liquidation || {};
        const spec = data.specification || {};
        const quantity = futures.position_quantity;

        document.getElementById('riskRewardLotSize').textContent = quantity == null ? 'Not Tradable' : `${number(quantity)} ${spec.base_currency || ''}`.trim();
        document.getElementById('riskRewardLotStatus').textContent = `${data.symbol} · ${data.side} · ${String(futures.calculation_mode || '').replace('_', ' ')} · ${futures.margin_mode || 'ISOLATED'} · ${futures.leverage}x`;
        document.getElementById('riskRewardActualRisk').textContent = usdt(futures.all_in_stop_risk ?? data.actual_risk);
        document.getElementById('riskRewardPotentialProfit').textContent = usdt(futures.net_target_profit ?? data.potential_profit);
        document.getElementById('riskRewardPositionUnits').textContent = usdt(futures.position_notional);
        document.getElementById('riskRewardRiskUtilization').textContent = usdt(futures.required_initial_margin);
        document.getElementById('riskRewardRatio').textContent = `1 : ${Number(data.risk_reward_ratio || 0).toFixed(2)}`;
        document.getElementById('riskRewardPerLot').textContent = `${futures.leverage}x`;
        document.getElementById('riskRewardSlDistance').textContent = `${Number(futures.stop_loss_percent || 0).toFixed(2)}%`;
        document.getElementById('riskRewardTpDistance').textContent = `${Number(futures.take_profit_percent || 0).toFixed(2)}%`;

        document.getElementById('riskRewardAllInStopRisk').textContent = usdt(futures.all_in_stop_risk);
        document.getElementById('riskRewardNetTargetProfit').textContent = usdt(futures.net_target_profit);
        document.getElementById('riskRewardEstimatedEntryFee').textContent = usdt(costs.entry_fee);
        document.getElementById('riskRewardEstimatedExitFee').textContent = usdt(costs.stop_exit_fee);
        document.getElementById('riskRewardEstimatedSlippage').textContent = usdt(costs.stop_slippage);
        document.getElementById('riskRewardEstimatedFunding').textContent = usdt(costs.funding_estimate);
        document.getElementById('riskRewardEstimatedLiquidation').textContent = liquidation.available
            ? number(liquidation.estimated_price, 8)
            : 'Tier inputs required';
        document.getElementById('riskRewardLiquidationBuffer').textContent = liquidation.available
            ? `${Number(liquidation.stop_buffer_percent || 0).toFixed(2)}%`
            : '—';

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
            margin_mode: String(ui.marginMode.value || 'ISOLATED'),
            leverage: String(ui.leverage.value || '10'),
            risk_amount: mode === 'RISK_BASED' ? String(riskAmount.value || '') : null,
            margin_amount: mode === 'MARGIN_BASED' ? String(ui.marginInput.value || '') : null,
            entry_fee_rate_percent: String(ui.entryFee.value || '0'),
            exit_fee_rate_percent: String(ui.exitFee.value || '0'),
            slippage_percent: String(ui.slippage.value || '0'),
            funding_rate_percent: String(ui.fundingRate.value || '0'),
            funding_intervals: String(ui.fundingIntervals.value || '0'),
            maintenance_margin_rate_percent: ui.marginMode.value === 'ISOLATED' ? optionalValue(ui.maintenanceRate) : null,
            maintenance_amount: ui.marginMode.value === 'ISOLATED' ? optionalValue(ui.maintenanceAmount) : null,
            liquidation_wallet_balance: ui.marginMode.value === 'ISOLATED' ? optionalValue(ui.liquidationWallet) : null,
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
            const settings = markets.binance_futures;
            const costs = settings.cost_assumptions || {};
            ui.leverage.value = String(settings.leverage?.default || 10);
            ui.marginMode.value = String(settings.liquidation?.default_margin_mode || 'ISOLATED');
            ui.entryFee.value = String(numericDefault(costs.default_entry_fee_percent, 0));
            ui.exitFee.value = String(numericDefault(costs.default_exit_fee_percent, 0));
            ui.slippage.value = String(numericDefault(costs.default_slippage_percent, 0));
            ui.fundingRate.value = String(numericDefault(costs.default_funding_rate_percent, 0));
            ui.fundingIntervals.value = String(numericDefault(costs.default_funding_intervals, 0));
            ui.maintenanceRate.value = '';
            ui.maintenanceAmount.value = '';
            ui.liquidationWallet.value = '';
            setCalculationMode('RISK_BASED');
            updateLiquidationInputs();
            updateMarketUi();
        }, 0);
    });

    updateLiquidationInputs();
    updateMarketUi();
})();
