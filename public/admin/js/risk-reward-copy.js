(() => {
    'use strict';

    const root = document.getElementById('riskRewardCalculatorApp');
    const button = document.getElementById('riskRewardCopyButton');
    const status = document.getElementById('riskRewardCopyStatus');
    const lotNode = document.getElementById('riskRewardLotSize');

    if (!root || !button || !status || !lotNode) {
        return;
    }

    function text(id) {
        return document.getElementById(id)?.textContent?.trim() || '—';
    }

    function value(id) {
        return document.getElementById(id)?.value?.trim() || '—';
    }

    function activeMarketLabel() {
        return root.querySelector('[data-market-key].active')?.textContent?.trim() || '—';
    }

    function summary() {
        return [
            'TradeX Risk Reward Calculator',
            `Market: ${activeMarketLabel()}`,
            `Symbol: ${value('riskRewardInstrument')}`,
            `Side: ${value('riskRewardSide')}`,
            `Entry: ${value('riskRewardEntry')}`,
            `Stop Loss: ${value('riskRewardStopLoss')}`,
            `Take Profit: ${value('riskRewardTakeProfit')}`,
            `Requested Risk: $${value('riskRewardAmount')} USD`,
            `Recommended Lot: ${text('riskRewardLotSize')}`,
            `Position Units: ${text('riskRewardPositionUnits')}`,
            `Actual Risk: ${text('riskRewardActualRisk')}`,
            `Potential Profit: ${text('riskRewardPotentialProfit')}`,
            `Risk : Reward: ${text('riskRewardRatio')}`,
            `Risk Utilization: ${text('riskRewardRiskUtilization')}`,
            `SL Distance: ${text('riskRewardSlDistance')}`,
            `TP Distance: ${text('riskRewardTpDistance')}`,
            `Specification: ${text('riskRewardSpecStatus')} · ${text('riskRewardSpecProfile')}`,
            '',
            'Reference position sizing only. Confirm the live broker/platform specification before trading.',
        ].join('\n');
    }

    async function copyText(content) {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(content);
            return;
        }

        const textarea = document.createElement('textarea');
        textarea.value = content;
        textarea.setAttribute('readonly', '');
        textarea.style.position = 'fixed';
        textarea.style.opacity = '0';
        document.body.appendChild(textarea);
        textarea.select();

        const copied = document.execCommand('copy');
        textarea.remove();

        if (!copied) {
            throw new Error('Copy command failed.');
        }
    }

    function refreshState() {
        const result = lotNode.textContent.trim();
        button.disabled = result === '' || result === '—';

        if (button.disabled) {
            status.textContent = 'Calculate a position first.';
        } else {
            status.textContent = 'Copy the current calculation as plain text.';
        }
    }

    button.addEventListener('click', async () => {
        if (button.disabled) {
            return;
        }

        const original = button.textContent;
        button.disabled = true;
        button.textContent = 'Copying...';

        try {
            await copyText(summary());
            button.textContent = 'Copied';
            status.textContent = 'Trade summary copied to clipboard.';
        } catch (error) {
            button.textContent = 'Copy Failed';
            status.textContent = 'Clipboard access was unavailable. Select and copy the values manually.';
        }

        window.setTimeout(() => {
            button.textContent = original;
            refreshState();
        }, 1600);
    });

    new MutationObserver(refreshState).observe(lotNode, {
        childList: true,
        characterData: true,
        subtree: true,
    });

    refreshState();
})();

(() => {
    'use strict';

    const root = document.getElementById('riskRewardCalculatorApp');
    const marketField = document.getElementById('riskRewardMarket');
    const instrumentInput = document.getElementById('riskRewardInstrument');
    const marketDataNode = document.getElementById('riskRewardMarketData');
    const wrapper = document.getElementById('riskRewardConversionField');
    const input = document.getElementById('riskRewardQuoteToUsdRate');
    const label = document.getElementById('riskRewardConversionLabel');
    const help = document.getElementById('riskRewardConversionHelp');

    if (!root || !marketField || !instrumentInput || !marketDataNode || !wrapper || !input) {
        return;
    }

    let markets = {};

    try {
        markets = JSON.parse(marketDataNode.textContent || '{}');
    } catch (error) {
        return;
    }

    function refreshCrossMarketConversion() {
        const market = markets[marketField.value] || null;
        const symbol = String(instrumentInput.value || '').trim().toUpperCase();
        const instrument = market?.instruments?.[symbol] || null;

        if (!instrument) {
            return;
        }

        const base = String(instrument.base_currency || '').toUpperCase();
        const quote = String(instrument.quote_currency || '').toUpperCase();
        const needsConversion = base !== '' && quote !== '' && base !== 'USD' && quote !== 'USD';

        if (!needsConversion) {
            return;
        }

        wrapper.hidden = false;

        if (label) {
            label.textContent = `${quote} → USD Conversion Rate (optional)`;
        }

        if (help) {
            help.textContent = `Leave blank for automatic ${quote}→USD conversion from TradeX market data. Enter the current rate only if the live provider is unavailable.`;
        }
    }

    instrumentInput.addEventListener('input', () => {
        window.setTimeout(refreshCrossMarketConversion, 0);
    });

    root.querySelectorAll('[data-market-key]').forEach((button) => {
        button.addEventListener('click', () => {
            window.setTimeout(refreshCrossMarketConversion, 0);
        });
    });

    refreshCrossMarketConversion();
})();
