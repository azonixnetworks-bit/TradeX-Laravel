(() => {
    'use strict';

    const root = document.getElementById('riskRewardCalculatorApp');
    const source = document.getElementById('riskRewardLeverage');
    const resetButton = document.getElementById('riskRewardResetButton');

    if (!root || !source || source.dataset.sliderEnhanced === 'true') {
        return;
    }

    const marketDataNode = document.getElementById('riskRewardMarketData');
    let marketData = {};

    try {
        marketData = JSON.parse(marketDataNode?.textContent || '{}');
    } catch (error) {
        marketData = {};
    }

    const settings = marketData.binance_futures?.leverage || {};
    const min = Number(settings.min || 1);
    const max = Number(settings.max || 125);
    const defaultValue = Number(settings.default || 10);
    const presets = [1, 5, 10, 20, 50, 100, 125].filter((value) => value >= min && value <= max);
    const field = source.closest('.risk-reward-field');

    if (!field) {
        return;
    }

    source.dataset.sliderEnhanced = 'true';
    source.classList.add('risk-reward-leverage-source');
    source.tabIndex = -1;
    source.setAttribute('aria-hidden', 'true');

    const label = field.querySelector('label[for="riskRewardLeverage"]');
    if (label) {
        label.setAttribute('for', 'riskRewardLeverageRange');
    }

    const control = document.createElement('div');
    control.className = 'risk-reward-leverage-control';
    control.innerHTML = `
        <div class="risk-reward-leverage-topline">
            <span>Adjust Leverage</span>
            <strong id="riskRewardLeverageValue" aria-live="polite">${defaultValue}x</strong>
        </div>
        <div class="risk-reward-leverage-row">
            <button type="button" class="risk-reward-leverage-step" id="riskRewardLeverageMinus" aria-label="Decrease leverage">−</button>
            <div class="risk-reward-leverage-track-wrap">
                <input
                    id="riskRewardLeverageRange"
                    class="risk-reward-leverage-range"
                    type="range"
                    min="${min}"
                    max="${max}"
                    step="1"
                    value="${defaultValue}"
                    aria-label="Leverage"
                >
                <div class="risk-reward-leverage-scale" aria-hidden="true">
                    <span>${min}x</span>
                    <span>${max}x</span>
                </div>
            </div>
            <button type="button" class="risk-reward-leverage-step" id="riskRewardLeveragePlus" aria-label="Increase leverage">+</button>
        </div>
        <div class="risk-reward-leverage-presets" id="riskRewardLeveragePresets" aria-label="Leverage presets"></div>
    `;

    source.insertAdjacentElement('afterend', control);

    const range = control.querySelector('#riskRewardLeverageRange');
    const valueNode = control.querySelector('#riskRewardLeverageValue');
    const minus = control.querySelector('#riskRewardLeverageMinus');
    const plus = control.querySelector('#riskRewardLeveragePlus');
    const presetContainer = control.querySelector('#riskRewardLeveragePresets');

    presets.forEach((value) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'risk-reward-leverage-preset';
        button.dataset.leveragePreset = String(value);
        button.textContent = `${value}x`;
        button.addEventListener('click', () => setLeverage(value, true));
        presetContainer.appendChild(button);
    });

    function clamp(value) {
        return Math.min(max, Math.max(min, Math.round(Number(value) || min)));
    }

    function updateVisuals(value) {
        const selected = clamp(value);
        const progress = max === min ? 0 : ((selected - min) / (max - min)) * 100;

        range.value = String(selected);
        range.style.setProperty('--leverage-progress', `${progress}%`);
        valueNode.textContent = `${selected}x`;
        minus.disabled = selected <= min;
        plus.disabled = selected >= max;

        presetContainer.querySelectorAll('[data-leverage-preset]').forEach((button) => {
            const active = Number(button.dataset.leveragePreset) === selected;
            button.classList.toggle('active', active);
            button.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function setLeverage(value, emitChange = false) {
        const selected = clamp(value);
        source.value = String(selected);
        updateVisuals(selected);

        if (emitChange) {
            source.dispatchEvent(new Event('change', { bubbles: true }));
        }
    }

    range.addEventListener('input', () => setLeverage(range.value, true));
    minus.addEventListener('click', () => setLeverage(Number(source.value || defaultValue) - 1, true));
    plus.addEventListener('click', () => setLeverage(Number(source.value || defaultValue) + 1, true));

    source.addEventListener('change', () => updateVisuals(source.value));

    range.addEventListener('keydown', (event) => {
        if (event.key === 'Home') {
            event.preventDefault();
            setLeverage(min, true);
        } else if (event.key === 'End') {
            event.preventDefault();
            setLeverage(max, true);
        }
    });

    resetButton?.addEventListener('click', () => {
        window.setTimeout(() => setLeverage(source.value || defaultValue, false), 0);
    });

    setLeverage(source.value || defaultValue, false);
})();
