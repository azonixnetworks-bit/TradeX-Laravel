@if($activityEvents !== null)
    <section class="panel users-panel" style="margin-bottom:18px;">
        <div class="panel-header">
            <div>
                <div class="panel-eyebrow">SUPER ADMIN AUDIT</div>
                <h2>User Activity</h2>
                <p style="margin:6px 0 0;color:#7f8da5;">
                    Activity recorded only for {{ $user->name }}. Retained for 45 days.
                </p>
            </div>

            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <div class="panel-count">
                    {{ number_format($activityEvents->total()) }} events
                </div>
                <a class="secondary-btn"
                   href="{{ route('admin.activity.index', ['user_id' => $user->getKey()]) }}">
                    Open Activity Analytics
                </a>
            </div>
        </div>

        <div class="users-table-wrap">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Occurred</th>
                        <th>Module</th>
                        <th>Activity</th>
                        <th>Subject</th>
                        <th>Session</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($activityEvents as $activityEvent)
                        <tr>
                            <td>
                                <strong>{{ $activityEvent->occurred_at?->format('d M Y') ?? '—' }}</strong>
                                <span style="display:block;color:#7f8da5;">
                                    {{ $activityEvent->occurred_at?->format('H:i:s') ?? '—' }}
                                </span>
                            </td>
                            <td>
                                <span class="status-badge">
                                    {{ str($activityEvent->module)->replace('_', ' ')->headline() }}
                                </span>
                            </td>
                            <td>
                                <strong>{{ str($activityEvent->event_name)->replace('_', ' ')->headline() }}</strong>
                                <span style="display:block;color:#7f8da5;">
                                    {{ $activityEvent->event_id }}
                                </span>
                            </td>
                            <td>{{ $activityEvent->subject_id ?: '—' }}</td>
                            <td>{{ $activityEvent->session_id ?: '—' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="users-empty">
                                <strong>No activity recorded</strong>
                                <span>This customer has not sent any tracked app activity yet.</span>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($activityEvents->hasPages())
            <div style="padding:18px 20px 0;">
                {{ $activityEvents->links() }}
            </div>
        @endif
    </section>
@endif
