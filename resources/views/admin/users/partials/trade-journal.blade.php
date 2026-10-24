@php
    $formatJournalNumber = static function (
        mixed $value,
        int $precision = 8
    ): string {
        if ($value === null || $value === '') {
            return '—';
        }

        $formatted = number_format(
            (float) $value,
            $precision,
            '.',
            ','
        );

        return rtrim(
            rtrim($formatted, '0'),
            '.'
        );
    };
@endphp

<section
    class="panel users-panel customer-trade-journal"
    id="customer-trade-journal"
>
    <div class="panel-header customer-journal-header">
        <div>
            <div class="panel-eyebrow">TRADING ACTIVITY</div>
            <h2>Trade Journal</h2>
            <p>
                Customer-saved plans, executions and close-trade reviews from
                the active 45-day retention window.
            </p>
        </div>

        <div class="panel-count">
            {{ number_format($tradeJournalStats['total']) }}
            {{ $tradeJournalStats['total'] === 1 ? 'trade' : 'trades' }}
        </div>
    </div>

    <div class="customer-journal-stat-grid">
        <div class="customer-journal-stat">
            <span>Total Saved</span>
            <strong>{{ number_format($tradeJournalStats['total']) }}</strong>
        </div>

        <div class="customer-journal-stat planned">
            <span>Planned</span>
            <strong>{{ number_format($tradeJournalStats['planned']) }}</strong>
        </div>

        <div class="customer-journal-stat open">
            <span>Open</span>
            <strong>{{ number_format($tradeJournalStats['open']) }}</strong>
        </div>

        <div class="customer-journal-stat closed">
            <span>Closed</span>
            <strong>{{ number_format($tradeJournalStats['closed']) }}</strong>
        </div>

        <div class="customer-journal-stat win-rate">
            <span>Closed Win Rate</span>
            <strong>
                {{ $tradeJournalStats['win_rate'] === null
                    ? '—'
                    : number_format($tradeJournalStats['win_rate'], 1).'%'
                }}
            </strong>
        </div>
    </div>

    <div class="users-table-wrap customer-journal-table-wrap">
        <table class="users-table customer-journal-table">
            <thead>
                <tr>
                    <th>Trade</th>
                    <th>Market</th>
                    <th>Direction</th>
                    <th>Status</th>
                    <th>Trade Time</th>
                    <th>Exit</th>
                    <th>Net P&amp;L</th>
                    <th>Review</th>
                </tr>
            </thead>

            <tbody>
                @forelse($tradeJournalEntries as $journalEntry)
                    @php
                        $isBuy = in_array(
                            strtolower($journalEntry->direction),
                            ['buy', 'long'],
                            true
                        );

                        $mistakes = collect($journalEntry->mistake_tags ?? [])
                            ->map(
                                fn ($mistake) =>
                                    \Illuminate\Support\Str::headline(
                                        (string) $mistake
                                    )
                            )
                            ->values();
                    @endphp

                    <tr>
                        <td>
                            <div class="customer-journal-symbol">
                                <strong>{{ $journalEntry->symbol }}</strong>
                                <code title="{{ $journalEntry->uuid }}">
                                    {{ \Illuminate\Support\Str::limit(
                                        $journalEntry->uuid,
                                        13,
                                        '…'
                                    ) }}
                                </code>
                            </div>
                        </td>

                        <td>
                            <span class="customer-journal-market">
                                {{ \Illuminate\Support\Str::headline(
                                    $journalEntry->market
                                ) }}
                            </span>
                        </td>

                        <td>
                            <span
                                class="customer-journal-badge
                                {{ $isBuy ? 'buy' : 'sell' }}"
                            >
                                {{ strtoupper($journalEntry->direction) }}
                            </span>
                        </td>

                        <td>
                            <div class="customer-journal-status-stack">
                                <span
                                    class="customer-journal-badge
                                    status-{{ strtolower($journalEntry->status) }}"
                                >
                                    {{ strtoupper($journalEntry->status) }}
                                </span>

                                @if($journalEntry->result)
                                    <span class="customer-journal-result">
                                        {{ strtoupper($journalEntry->result) }}
                                    </span>
                                @endif
                            </div>
                        </td>

                        <td>
                            <span class="customer-journal-date">
                                {{ $journalEntry->trade_at?->format('d M Y')
                                    ?? '—'
                                }}
                                <small>
                                    {{ $journalEntry->trade_at?->format('H:i')
                                        ?? ''
                                    }}
                                    {{ $journalEntry->user_timezone }}
                                </small>
                            </span>
                        </td>

                        <td>
                            <span class="customer-journal-price">
                                {{ $formatJournalNumber(
                                    $journalEntry->exit_price,
                                    12
                                ) }}
                            </span>
                        </td>

                        <td>
                            <span
                                class="customer-journal-pnl
                                {{ (float) ($journalEntry->net_pnl ?? 0) > 0
                                    ? 'positive'
                                    : ((float) ($journalEntry->net_pnl ?? 0) < 0
                                        ? 'negative'
                                        : '')
                                }}"
                            >
                                {{ $formatJournalNumber(
                                    $journalEntry->net_pnl,
                                    8
                                ) }}
                                {{ $journalEntry->pnl_currency ?? '' }}
                            </span>
                        </td>

                        <td>
                            <details class="customer-journal-details">
                                <summary>View saved details</summary>

                                <div class="customer-journal-detail-content">
                                    <div class="customer-journal-detail-grid">
                                        <div>
                                            <span>Planned Entry</span>
                                            <strong>{{ $formatJournalNumber(
                                                $journalEntry->planned_entry,
                                                12
                                            ) }}</strong>
                                        </div>
                                        <div>
                                            <span>Actual Entry</span>
                                            <strong>{{ $formatJournalNumber(
                                                $journalEntry->actual_entry,
                                                12
                                            ) }}</strong>
                                        </div>
                                        <div>
                                            <span>Stop Loss</span>
                                            <strong>{{ $formatJournalNumber(
                                                $journalEntry->current_sl
                                                    ?? $journalEntry->planned_sl,
                                                12
                                            ) }}</strong>
                                        </div>
                                        <div>
                                            <span>Take Profit</span>
                                            <strong>{{ $formatJournalNumber(
                                                $journalEntry->current_tp
                                                    ?? $journalEntry->planned_tp,
                                                12
                                            ) }}</strong>
                                        </div>
                                        <div>
                                            <span>Gross P&amp;L</span>
                                            <strong>{{ $formatJournalNumber(
                                                $journalEntry->gross_pnl,
                                                8
                                            ) }}</strong>
                                        </div>
                                        <div>
                                            <span>Fees</span>
                                            <strong>{{ $formatJournalNumber(
                                                $journalEntry->fees,
                                                8
                                            ) }}</strong>
                                        </div>
                                        <div>
                                            <span>P&amp;L %</span>
                                            <strong>
                                                {{ $formatJournalNumber(
                                                    $journalEntry->pnl_percent,
                                                    6
                                                ) }}{{ $journalEntry->pnl_percent !== null
                                                    ? '%'
                                                    : ''
                                                }}
                                            </strong>
                                        </div>
                                        <div>
                                            <span>Rating</span>
                                            <strong>
                                                {{ $journalEntry->rating
                                                    ? $journalEntry->rating.'/5'
                                                    : '—'
                                                }}
                                            </strong>
                                        </div>
                                    </div>

                                    <div class="customer-journal-review-grid">
                                        <div>
                                            <span>Setup / Strategy</span>
                                            <p>{{ $journalEntry->setup_strategy ?? '—' }}</p>
                                        </div>
                                        <div>
                                            <span>Entry Reason</span>
                                            <p>{{ $journalEntry->entry_reason ?: '—' }}</p>
                                        </div>
                                        <div>
                                            <span>Emotion Before / After</span>
                                            <p>
                                                {{ \Illuminate\Support\Str::headline(
                                                    $journalEntry->emotion_before
                                                        ?? 'not recorded'
                                                ) }}
                                                →
                                                {{ \Illuminate\Support\Str::headline(
                                                    $journalEntry->emotion_after
                                                        ?? 'not recorded'
                                                ) }}
                                            </p>
                                        </div>
                                        <div>
                                            <span>Followed Plan</span>
                                            <p>{{ $journalEntry->followed_plan_after
                                                ? \Illuminate\Support\Str::headline(
                                                    $journalEntry->followed_plan_after
                                                )
                                                : '—'
                                            }}</p>
                                        </div>
                                        <div class="customer-journal-detail-wide">
                                            <span>Selected Mistakes</span>
                                            <p>{{ $mistakes->isEmpty()
                                                ? 'None selected'
                                                : $mistakes->join(', ')
                                            }}</p>
                                        </div>
                                        <div>
                                            <span>What Went Well</span>
                                            <p>{{ $journalEntry->went_well ?? '—' }}</p>
                                        </div>
                                        <div>
                                            <span>What Went Wrong</span>
                                            <p>{{ $journalEntry->went_wrong ?? '—' }}</p>
                                        </div>
                                        <div>
                                            <span>Lesson Learned</span>
                                            <p>{{ $journalEntry->lesson_learned ?? '—' }}</p>
                                        </div>
                                        <div>
                                            <span>Next Improvement</span>
                                            <p>{{ $journalEntry->improvement_next_trade ?? '—' }}</p>
                                        </div>
                                    </div>
                                </div>
                            </details>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="users-empty">
                            <strong>No Trade Journal entries</strong>
                            <span>
                                This customer has not saved a trade within the
                                active retention window.
                            </span>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($tradeJournalEntries->hasPages())
        <div class="customer-journal-pagination">
            @include(
                'admin.components.pagination',
                ['paginator' => $tradeJournalEntries]
            )
        </div>
    @endif
</section>
