{{-- TradeX Admin pagination --}}
@if($paginator->hasPages())

    <div class="admin-pagination">

        <div class="pagination-info">

            Page

            <strong>
                {{ $paginator->currentPage() }}
            </strong>

            of

            <strong>
                {{ $paginator->lastPage() }}
            </strong>

        </div>


        <div
            class="pagination-links"
            style="flex-wrap:wrap;max-width:100%;"
        >

            {{-- PREVIOUS --}}

            @if($paginator->onFirstPage())

                <span
                    class="page-button disabled"
                    aria-disabled="true"
                    aria-label="Previous page"
                    title="Previous"
                >
                    ‹
                </span>

            @else

                <a
                    href="{{ $paginator->previousPageUrl() }}"
                    class="page-button"
                    aria-label="Previous page"
                    title="Previous"
                    rel="prev"
                >
                    ‹
                </a>

            @endif


            {{-- PAGE NUMBERS --}}

            @php

                $current =
                    $paginator->currentPage();

                $last =
                    $paginator->lastPage();

                $start =
                    max(
                        1,
                        $current - 2
                    );

                $end =
                    min(
                        $last,
                        $current + 2
                    );

            @endphp


            @if($start > 1)

                <a
                    href="{{ $paginator->url(1) }}"
                    class="page-button"
                    aria-label="Go to page 1"
                >
                    1
                </a>

                @if($start > 2)

                    <span
                        class="page-dots"
                        aria-hidden="true"
                    >
                        …
                    </span>

                @endif

            @endif


            @for(
                $page = $start;
                $page <= $end;
                $page++
            )

                @if(
                    $page ===
                    $current
                )

                    <span
                        class="page-button active"
                        aria-current="page"
                        aria-label="Page {{ $page }}"
                    >
                        {{ $page }}
                    </span>

                @else

                    <a
                        href="{{ $paginator->url($page) }}"
                        class="page-button"
                        aria-label="Go to page {{ $page }}"
                    >
                        {{ $page }}
                    </a>

                @endif

            @endfor


            @if($end < $last)

                @if($end < $last - 1)

                    <span
                        class="page-dots"
                        aria-hidden="true"
                    >
                        …
                    </span>

                @endif

                <a
                    href="{{ $paginator->url($last) }}"
                    class="page-button"
                    aria-label="Go to page {{ $last }}"
                >
                    {{ $last }}
                </a>

            @endif


            {{-- NEXT --}}

            @if($paginator->hasMorePages())

                <a
                    href="{{ $paginator->nextPageUrl() }}"
                    class="page-button"
                    aria-label="Next page"
                    title="Next"
                    rel="next"
                >
                    ›
                </a>

            @else

                <span
                    class="page-button disabled"
                    aria-disabled="true"
                    aria-label="Next page"
                    title="Next"
                >
                    ›
                </span>

            @endif

        </div>

    </div>

@endif