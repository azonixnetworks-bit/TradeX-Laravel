@extends('admin.layouts.app')

@section('title', 'Audit Log Detail')
@section('page-title', 'Audit Log Detail')

@section('content')
<div class="page-header">
    <div><div class="page-eyebrow">AUDIT EVENT #{{ $log->getKey() }}</div><h1>Audit <span>Detail</span></h1><p>{{ $log->action }}</p></div>
    <a class="secondary-btn" href="{{ route('admin.audit.index') }}">Back to Audit Logs</a>
</div>

<section class="panel" style="padding:18px;margin-bottom:18px;"><strong>Sanitized rendering:</strong> {{ $redaction_notice }}</section>

<div class="user-stat-grid">
    <div class="user-stat-card"><span>Event ID</span><strong>#{{ $log->getKey() }}</strong></div>
    <div class="user-stat-card"><span>Actor</span><strong>{{ $actor['type'] }}</strong><small>@if($actor['id'])#{{ $actor['id'] }}@endif @if($actor['name']){{ $actor['name'] }}@endif</small></div>
    <div class="user-stat-card"><span>Module</span><strong>{{ $log->module ?: '—' }}</strong></div>
    <div class="user-stat-card"><span>Created</span><strong>{{ $log->created_at?->format('Y-m-d') ?? '—' }}</strong><small>{{ $log->created_at?->format('H:i:s') ?? '' }}</small></div>
</div>

<section class="panel users-panel" style="margin-bottom:18px;">
    <div class="panel-header"><div><div class="panel-eyebrow">EVENT METADATA</div><h2>Recorded Context</h2></div></div>
    <div class="users-table-wrap"><table class="users-table"><tbody>
        <tr><th>Action</th><td><strong>{{ $log->action }}</strong></td></tr>
        <tr><th>Module</th><td>{{ $log->module ?: '—' }}</td></tr>
        <tr><th>Actor Type</th><td>{{ $actor['type'] }}</td></tr>
        <tr><th>Actor ID</th><td>{{ $actor['id'] ?: '—' }}</td></tr>
        <tr><th>Actor Name</th><td>{{ $actor['name'] ?: 'Unresolved / unavailable' }}</td></tr>
        <tr><th>Actor Email</th><td>{{ $actor['email'] ?: '—' }}</td></tr>
        <tr><th>Target Type</th><td>{{ $log->target_type ?: '—' }}</td></tr>
        <tr><th>Target ID</th><td>{{ $log->target_id ?: '—' }}</td></tr>
        <tr><th>IP Address</th><td>{{ $log->ip_address ?: '—' }}</td></tr>
        <tr><th>User Agent</th><td>{{ $log->user_agent ?: '—' }}</td></tr>
    </tbody></table></div>
</section>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:18px;">
    <section class="panel" style="padding:22px;"><div class="panel-eyebrow">OLD VALUES</div><h2>Before State</h2>@if($old_values_json !== null)<pre style="white-space:pre-wrap;word-break:break-word;overflow:auto;">{{ $old_values_json }}</pre>@else<p>No old-values payload was recorded.</p>@endif</section>
    <section class="panel" style="padding:22px;"><div class="panel-eyebrow">NEW VALUES</div><h2>After State</h2>@if($new_values_json !== null)<pre style="white-space:pre-wrap;word-break:break-word;overflow:auto;">{{ $new_values_json }}</pre>@else<p>No new-values payload was recorded.</p>@endif</section>
</div>

<section class="panel" style="padding:18px;margin-top:18px;"><div class="panel-eyebrow">ACCESS AUDIT</div><p>Opening this event detail records a separate `AUDIT_LOG_DETAIL_ACCESSED` event containing only safe metadata about the inspected event. The inspected audit row itself is never modified.</p></section>
@endsection
