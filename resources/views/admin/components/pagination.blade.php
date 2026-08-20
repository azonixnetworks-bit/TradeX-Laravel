{{-- TradeX Admin skeleton --}} 
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


        <div class="pagination-links">

            {{-- PREVIOUS --}}

            @if($paginator->onFirstPage())

                <span class="page-button disabled">
                    ‹
                </span>

            @else

                <a
                    href="{{ $paginator->previousPageUrl() }}"
                    class="page-button"
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
                >
                    1
                </a>

                @if($start > 2)

                    <span class="page-dots">
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
                    >
                        {{ $page }}
                    </span>

                @else

                    <a
                        href="{{ $paginator->url($page) }}"
                        class="page-button"
                    >
                        {{ $page }}
                    </a>

                @endif

            @endfor


            @if($end < $last)

                @if($end < $last - 1)

                    <span class="page-dots">
                        …
                    </span>

                @endif

                <a
                    href="{{ $paginator->url($last) }}"
                    class="page-button"
                >
                    {{ $last }}
                </a>

            @endif


            {{-- NEXT --}}

            @if($paginator->hasMorePages())

                <a
                    href="{{ $paginator->nextPageUrl() }}"
                    class="page-button"
                >
                    ›
                </a>

            @else

                <span class="page-button disabled">
                    ›
                </span>

            @endif

        </div>

    </div>

@endif