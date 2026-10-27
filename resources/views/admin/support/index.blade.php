@extends('admin.layouts.app')

@section('title', 'Customer Support')
@section('page-title', 'Customer Support')

@section('content')
<style>
.support-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:14px;margin:18px 0}.support-stat,.support-panel{background:linear-gradient(145deg,#101820,#090d11);border:1px solid #3a4650;border-radius:16px;padding:18px}.support-stat strong{display:block;font-size:30px;color:#f4c94d}.support-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:end;margin-bottom:16px}.support-toolbar label{display:grid;gap:5px;color:#aeb8c2}.support-toolbar input,.support-toolbar select,.support-form textarea,.support-form select{background:#06090c;color:#fff;border:1px solid #43505c;border-radius:9px;padding:10px}.support-table{width:100%;border-collapse:collapse}.support-table th,.support-table td{padding:12px;border-bottom:1px solid #28313a;text-align:left}.support-badge{display:inline-block;padding:4px 9px;border:1px solid #8a6b18;border-radius:999px;color:#f4c94d;font-size:12px}.support-muted{color:#9aa5af}.support-actions{display:flex;gap:10px;justify-content:space-between;align-items:center}.support-btn{display:inline-block;background:#f4c94d;color:#090909;border:0;border-radius:9px;padding:10px 14px;font-weight:700;text-decoration:none}@media(max-width:800px){.support-grid{grid-template-columns:1fr}.support-table{min-width:850px}.support-scroll{overflow:auto}}
</style>
<div class="page-header"><div><div class="page-eyebrow">SUPPORT · SECURE CASE QUEUE</div><h1>Customer <span>Support</span></h1><p>Persistent tickets, live-chat queue and SLA visibility. Internal notes are never exposed to customers.</p></div>@if(auth('admin')->user()->hasPermission('support.sla.manage') || auth('admin')->user()->hasPermission('support.categories.manage'))<a class="support-btn" href="{{ route('admin.support.settings') }}">Support Settings</a>@endif</div>

@if(session('status'))<div class="admin-form-callout"><strong>{{ session('status') }}</strong></div>@endif

<div class="support-grid">
    <div class="support-stat"><span>Active cases</span><strong>{{ $counts['open'] }}</strong></div>
    <div class="support-stat"><span>Unassigned</span><strong>{{ $counts['unassigned'] }}</strong></div>
    <div class="support-stat"><span>First-response overdue</span><strong>{{ $counts['overdue'] }}</strong></div>
</div>

@if(auth('admin')->user()->hasPermission('support.chat.participate'))
<form method="POST" action="{{ route('admin.support.availability') }}" class="support-toolbar support-panel">@csrf @method('PATCH')
    <label>Agent availability<select name="availability"><option value="online" @selected($agentProfile?->availability === 'online')>Online</option><option value="away" @selected($agentProfile?->availability === 'away')>Away</option><option value="offline" @selected(!$agentProfile || $agentProfile->availability === 'offline')>Offline</option></select></label>
    <label>Capacity<input type="number" name="capacity" value="{{ $agentProfile?->capacity ?? 3 }}" min="1" max="20"></label>
    <button class="support-btn" type="submit">Update presence</button>
    <span class="support-muted">Presence expires automatically without heartbeat; it is never treated as permanently online.</span>
</form>
@endif

<section class="support-panel">
<form method="GET" class="support-toolbar">
    <label>Search<input name="q" value="{{ request('q') }}" placeholder="Reference or subject"></label>
    <label>Status<select name="status"><option value="">All</option>@foreach(['open','in_progress','waiting_for_customer','resolved','closed'] as $status)<option value="{{ $status }}" @selected(request('status')===$status)>{{ str_replace('_',' ',ucfirst($status)) }}</option>@endforeach</select></label>
    <label>Priority<select name="priority"><option value="">All</option>@foreach(['critical','high','normal','low'] as $priority)<option value="{{ $priority }}" @selected(request('priority')===$priority)>{{ ucfirst($priority) }}</option>@endforeach</select></label>
    <button class="support-btn" type="submit">Filter</button>
</form>
<div class="support-scroll"><table class="support-table"><thead><tr><th>Case</th><th>Customer</th><th>Category</th><th>Priority</th><th>Status</th><th>Agent</th><th>SLA</th><th></th></tr></thead><tbody>
@forelse($tickets as $ticket)
<tr><td><strong>{{ $ticket->public_reference }}</strong><br><span class="support-muted">{{ $ticket->subject }}</span></td><td>{{ $ticket->user->name }}<br><span class="support-muted">{{ $ticket->user->email }}</span></td><td>{{ $ticket->category?->label ?? '—' }}</td><td><span class="support-badge">{{ strtoupper($ticket->priority) }}</span></td><td>{{ str_replace('_',' ',ucfirst($ticket->status)) }}</td><td>{{ $ticket->assignedAdmin?->name ?? 'Unassigned' }}</td><td>@if(!$ticket->first_responded_at && $ticket->first_response_due_at)<span style="color:{{ $ticket->first_response_due_at->isPast() ? '#ff6375' : '#aeb8c2' }}">{{ $ticket->first_response_due_at->diffForHumans() }}</span>@else Responded @endif</td><td><a class="support-btn" href="{{ route('admin.support.show',$ticket) }}">Open</a></td></tr>
@empty<tr><td colspan="8">No support cases match this queue.</td></tr>@endforelse
</tbody></table></div>
<div style="margin-top:16px">{{ $tickets->links() }}</div>
</section>
@if($agentProfile && in_array($agentProfile->availability, ['online','away'], true))
<script>
(() => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const beat = () => fetch(@json(route('admin.support.presence.heartbeat')), {method:'POST',headers:{'X-CSRF-TOKEN':token,'Accept':'application/json'}}).catch(() => {});
    beat(); window.setInterval(beat, 30000);
})();
</script>
@endif
@endsection
