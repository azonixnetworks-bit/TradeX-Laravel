@php
    $marketButtons = [
        '' => 'All',
        'TODAY' => 'Today',
        'FOREX' => 'Forex',
        'CRYPTO' => 'Crypto',
        'DERIV' => 'Deriv',
        'STOCKS_CSE' => 'Stocks · CSE',
        'STOCKS_INDIA' => 'Stocks · India',
        'STOCKS_INTERNATIONAL' => 'Stocks · International',
    ];
@endphp

<div class="panel trade-market-filter-panel">
    <div class="trade-market-filter-head">
        <div>
            <span>QUICK MARKET VIEW</span>
            <strong>Filter Trade Ideas</strong>
        </div>
        @if($marketFilter !== '' || $status !== '')
            <a class="trade-market-clear" href="{{ route($baseRoute) }}">Clear all</a>
        @endif
    </div>

    <div class="trade-market-buttons" role="navigation" aria-label="Trade idea market filters">
        @foreach($marketButtons as $value => $label)
            @php
                $params = array_filter([
                    'market_filter' => $value !== '' ? $value : null,
                    'status' => $status !== '' ? $status : null,
                ], fn ($item) => $item !== null && $item !== '');
            @endphp

            <a
                class="trade-market-button {{ $marketFilter === $value ? 'is-active' : '' }}"
                href="{{ route($baseRoute, $params) }}"
            >
                {{ $label }}
            </a>
        @endforeach
    </div>

    <form class="trade-status-filter" method="GET" action="{{ route($baseRoute) }}">
        @if($marketFilter !== '')
            <input type="hidden" name="market_filter" value="{{ $marketFilter }}">
        @endif

        <label for="{{ $statusSelectId }}">Status</label>

        <select id="{{ $statusSelectId }}" name="status">
            <option value="">All statuses</option>
            @foreach($statusOptions as $value)
                <option value="{{ $value }}" @selected($status === $value)>
                    {{ str_replace('_', ' ', $value) }}
                </option>
            @endforeach
        </select>

        <button class="secondary-btn" type="submit">Apply Status</button>
    </form>
</div>
