@php
    $editing = isset($dailyTradeIdea);

    $targetValues = old('targets');

    if ($targetValues === null) {
        $targetValues = $editing
            ? $dailyTradeIdea->targets
                ->map(fn ($target) => ['price' => $target->price])
                ->values()
                ->all()
            : [];
    }

    $selectedCategory = old(
        'category',
        $editing ? $dailyTradeIdea->category : 'FOREX'
    );
@endphp

<div class="ti-section">
    <h2>Daily Idea Identity</h2>
    <p>Short-horizon TradeX market idea managed separately from Long-Term Trade Ideas.</p>

    <div class="ti-grid">
        <div class="ti-field full">
            <label for="title">TITLE</label>
            <input
                id="title"
                name="title"
                maxlength="180"
                value="{{ old('title', $editing ? $dailyTradeIdea->title : '') }}"
                required
            >
            @error('title')<div class="ti-error">{{ $message }}</div>@enderror
        </div>

        <div class="ti-field">
            <label for="category">CATEGORY</label>
            <select id="category" name="category" required>
                @foreach([
                    'FOREX' => 'Forex',
                    'CRYPTO' => 'Crypto',
                    'DERIV' => 'Deriv',
                    'STOCKS' => 'Stocks',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected($selectedCategory === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            @error('category')<div class="ti-error">{{ $message }}</div>@enderror
        </div>

        <div
            class="ti-field {{ $selectedCategory === 'STOCKS' ? '' : 'region-hidden' }}"
            id="daily-market-region-field"
        >
            <label for="market_region">STOCK MARKET REGION</label>
            <select id="market_region" name="market_region">
                <option value="">Select region</option>
                @foreach([
                    'SRI_LANKA' => 'Sri Lanka',
                    'INDIA' => 'India',
                    'INTERNATIONAL' => 'International',
                ] as $value => $label)
                    <option
                        value="{{ $value }}"
                        @selected(old('market_region', $editing ? $dailyTradeIdea->market_region : '') === $value)
                    >
                        {{ $label }}
                    </option>
                @endforeach
            </select>
            @error('market_region')<div class="ti-error">{{ $message }}</div>@enderror
        </div>

        <div class="ti-field">
            <label for="symbol">SYMBOL</label>
            <input
                id="symbol"
                name="symbol"
                maxlength="40"
                value="{{ old('symbol', $editing ? $dailyTradeIdea->symbol : '') }}"
                placeholder="EURUSD / BTCUSD / XAUUSD"
                required
            >
            @error('symbol')<div class="ti-error">{{ $message }}</div>@enderror
        </div>

        <div class="ti-field">
            <label for="instrument_name">INSTRUMENT NAME · OPTIONAL</label>
            <input
                id="instrument_name"
                name="instrument_name"
                maxlength="120"
                value="{{ old('instrument_name', $editing ? $dailyTradeIdea->instrument_name : '') }}"
                placeholder="Optional display name"
            >
            @error('instrument_name')<div class="ti-error">{{ $message }}</div>@enderror
        </div>

        <div class="ti-field">
            <label for="direction">DIRECTION</label>
            <select id="direction" name="direction" required>
                <option value="BUY" @selected(old('direction', $editing ? $dailyTradeIdea->direction : 'BUY') === 'BUY')>BUY</option>
                <option value="SELL" @selected(old('direction', $editing ? $dailyTradeIdea->direction : '') === 'SELL')>SELL</option>
            </select>
            @error('direction')<div class="ti-error">{{ $message }}</div>@enderror
        </div>

        <div class="ti-field">
            <label for="timeframe">TIMEFRAME</label>
            <select id="timeframe" name="timeframe" required>
                @foreach(['M1','M5','M15','M30','H1','H4','D1','W1','MN1'] as $timeframe)
                    <option
                        value="{{ $timeframe }}"
                        @selected(old('timeframe', $editing ? $dailyTradeIdea->timeframe : 'H1') === $timeframe)
                    >
                        {{ $timeframe }}
                    </option>
                @endforeach
            </select>
            @error('timeframe')<div class="ti-error">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="ti-section">
    <h2>Chart & TradingView · Optional</h2>
    <p>Add a TradingView share link, chart screenshot, both, or neither.</p>

    <div class="ti-grid">
        <div class="ti-field full">
            <label for="tradingview_url">TRADINGVIEW CHART LINK · OPTIONAL</label>
            <input
                id="tradingview_url"
                name="tradingview_url"
                maxlength="2048"
                value="{{ old('tradingview_url', $editing ? $dailyTradeIdea->tradingview_url : '') }}"
                placeholder="https://www.tradingview.com/chart/..."
            >
            @error('tradingview_url')<div class="ti-error">{{ $message }}</div>@enderror
        </div>

        <div class="ti-field full">
            <label for="chart_image">CHART SCREENSHOT / IDEA IMAGE · OPTIONAL</label>

            @if($editing && $dailyTradeIdea->hasChartImage())
                <div style="margin-bottom:10px">
                    <img
                        src="{{ route('admin.daily_trade_ideas.chart_image', ['dailyTradeIdea' => $dailyTradeIdea]) }}"
                        alt="Current Daily Trade Idea chart"
                        style="display:block;max-width:100%;max-height:420px;border-radius:12px;border:1px solid rgba(255,255,255,.08)"
                    >
                </div>
            @endif

            <input
                id="chart_image"
                type="file"
                name="chart_image"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
            >
            <div class="ti-hint">JPG, PNG or WEBP. Maximum 10 MB. Stored privately.</div>
            @error('chart_image')<div class="ti-error">{{ $message }}</div>@enderror

            @if($editing && $dailyTradeIdea->hasChartImage())
                <label style="display:flex;align-items:center;gap:8px;margin-top:10px">
                    <input type="hidden" name="remove_chart_image" value="0">
                    <input type="checkbox" name="remove_chart_image" value="1" style="width:auto">
                    <span>Remove current chart image</span>
                </label>
            @endif
        </div>
    </div>
</div>

<div class="ti-section">
    <h2>Trade Structure · Optional</h2>
    <p>Entry zone, Stop Loss and up to five targets are optional and validated by the existing TradeX structure rules.</p>

    <div class="ti-grid">
        <div class="ti-field">
            <label for="entry_price_low">ENTRY LOW · OPTIONAL</label>
            <input
                id="entry_price_low"
                name="entry_price_low"
                inputmode="decimal"
                value="{{ old('entry_price_low', $editing ? $dailyTradeIdea->entry_price_low : '') }}"
            >
            @error('entry_price_low')<div class="ti-error">{{ $message }}</div>@enderror
        </div>

        <div class="ti-field">
            <label for="entry_price_high">ENTRY HIGH · OPTIONAL</label>
            <input
                id="entry_price_high"
                name="entry_price_high"
                inputmode="decimal"
                value="{{ old('entry_price_high', $editing ? $dailyTradeIdea->entry_price_high : '') }}"
            >
            @error('entry_price_high')<div class="ti-error">{{ $message }}</div>@enderror
        </div>

        <div class="ti-field">
            <label for="stop_loss">STOP LOSS · OPTIONAL</label>
            <input
                id="stop_loss"
                name="stop_loss"
                inputmode="decimal"
                value="{{ old('stop_loss', $editing ? $dailyTradeIdea->stop_loss : '') }}"
            >
            @error('stop_loss')<div class="ti-error">{{ $message }}</div>@enderror
        </div>

        <div class="ti-field full">
            <label>TAKE PROFIT TARGETS · OPTIONAL</label>
            <div class="target-list" id="daily-target-list">
                @foreach($targetValues as $index => $target)
                    <div class="target-row">
                        <span class="target-label">TP{{ $index + 1 }}</span>
                        <input
                            name="targets[{{ $index }}][price]"
                            inputmode="decimal"
                            value="{{ $target['price'] ?? '' }}"
                            required
                        >
                        <button class="target-remove" type="button">Remove</button>
                    </div>
                    @error("targets.$index.price")<div class="ti-error">{{ $message }}</div>@enderror
                @endforeach
            </div>

            <button class="target-add" id="daily-target-add" type="button">
                + Add Optional Target
            </button>
            <div class="ti-hint">Zero to five targets.</div>
            @error('targets')<div class="ti-error">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="ti-section">
    <h2>Context · Optional</h2>
    <div class="ti-grid">
        <div class="ti-field full">
            <label for="summary">SHORT SUMMARY · OPTIONAL</label>
            <textarea id="summary" name="summary" maxlength="500">{{ old('summary', $editing ? $dailyTradeIdea->summary : '') }}</textarea>
            @error('summary')<div class="ti-error">{{ $message }}</div>@enderror
        </div>

        <div class="ti-field full">
            <label for="analysis">ANALYSIS / RATIONALE · OPTIONAL</label>
            <textarea id="analysis" name="analysis" maxlength="30000">{{ old('analysis', $editing ? $dailyTradeIdea->analysis : '') }}</textarea>
            @error('analysis')<div class="ti-error">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

@push('scripts')
<script>
(() => {
    'use strict';

    const category = document.getElementById('category');
    const regionField = document.getElementById('daily-market-region-field');
    const region = document.getElementById('market_region');
    const targetList = document.getElementById('daily-target-list');
    const targetAdd = document.getElementById('daily-target-add');

    const syncRegion = () => {
        const isStocks = category && category.value === 'STOCKS';
        regionField?.classList.toggle('region-hidden', !isStocks);

        if (region) {
            region.disabled = !isStocks;
            if (!isStocks) region.value = '';
        }
    };

    const renumberTargets = () => {
        targetList?.querySelectorAll('.target-row').forEach((row, index) => {
            const label = row.querySelector('.target-label');
            const input = row.querySelector('input');
            if (label) label.textContent = 'TP' + (index + 1);
            if (input) input.name = `targets[${index}][price]`;
        });
    };

    const wireRemoveButtons = () => {
        targetList?.querySelectorAll('.target-remove').forEach((button) => {
            button.onclick = () => {
                button.closest('.target-row')?.remove();
                renumberTargets();
            };
        });
    };

    category?.addEventListener('change', syncRegion);

    targetAdd?.addEventListener('click', () => {
        if (!targetList) return;
        const count = targetList.querySelectorAll('.target-row').length;
        if (count >= 5) return;

        const row = document.createElement('div');
        row.className = 'target-row';
        row.innerHTML = `
            <span class="target-label">TP${count + 1}</span>
            <input name="targets[${count}][price]" inputmode="decimal" required>
            <button class="target-remove" type="button">Remove</button>
        `;
        targetList.appendChild(row);
        wireRemoveButtons();
    });

    syncRegion();
    wireRemoveButtons();
})();
</script>
@endpush
