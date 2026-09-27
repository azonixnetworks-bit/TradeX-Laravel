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
            <a class="trade-market-clear" data-trade-idea-filter-link href="{{ route($baseRoute) }}">Clear all</a>
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
                data-trade-idea-filter-link
                href="{{ route($baseRoute, $params) }}"
            >
                {{ $label }}
            </a>
        @endforeach
    </div>

    <form class="trade-status-filter" data-trade-idea-filter-form method="GET" action="{{ route($baseRoute) }}">
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


@once
    @push('scripts')
        <script>
        (function () {
            let activeRequest = null;

            function filterContent() {
                return document.getElementById('trade-ideas-filterable-content');
            }

            async function updateTradeIdeas(url, pushState) {
                const currentContent = filterContent();

                if (!currentContent) {
                    window.location.assign(url);
                    return;
                }

                if (activeRequest) {
                    activeRequest.abort();
                }

                const request = new AbortController();
                activeRequest = request;
                currentContent.setAttribute('aria-busy', 'true');
                currentContent.classList.add('is-filter-loading');

                try {
                    const response = await fetch(url, {
                        credentials: 'same-origin',
                        headers: {'X-Requested-With': 'XMLHttpRequest'},
                        signal: request.signal,
                    });

                    if (!response.ok) {
                        throw new Error('Unable to load filtered Trade Ideas.');
                    }

                    const page = new DOMParser().parseFromString(
                        await response.text(),
                        'text/html'
                    );
                    const replacement = page.getElementById('trade-ideas-filterable-content');

                    if (!replacement) {
                        throw new Error('Filtered Trade Ideas content was unavailable.');
                    }

                    currentContent.replaceWith(replacement);
                    document.title = page.title || document.title;

                    if (pushState) {
                        window.history.pushState({}, '', url);
                    }
                } catch (error) {
                    if (error.name !== 'AbortError') {
                        window.location.assign(url);
                    }
                } finally {
                    if (activeRequest === request) {
                        activeRequest = null;
                        const content = filterContent();
                        if (content) {
                            content.removeAttribute('aria-busy');
                            content.classList.remove('is-filter-loading');
                        }
                    }
                }
            }

            document.addEventListener('click', function (event) {
                const link = event.target.closest('[data-trade-idea-filter-link]');

                if (!link || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                    return;
                }

                event.preventDefault();
                updateTradeIdeas(link.href, true);
            });

            document.addEventListener('submit', function (event) {
                const form = event.target.closest('[data-trade-idea-filter-form]');

                if (!form) {
                    return;
                }

                event.preventDefault();
                const query = new URLSearchParams(new FormData(form));
                const url = new URL(form.action);
                url.search = query.toString();
                updateTradeIdeas(url.toString(), true);
            });

            window.addEventListener('popstate', function () {
                updateTradeIdeas(window.location.href, false);
            });
        }());
        </script>
    @endpush
@endonce
