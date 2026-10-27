@extends('admin.layouts.app')
@section('title', $ticket->public_reference)
@section('page-title', 'Support Case')
@section('content')
<style>
.case-layout{display:grid;grid-template-columns:minmax(0,2fr) minmax(280px,1fr);gap:18px}.case-card{background:linear-gradient(145deg,#101820,#080b0e);border:1px solid #394550;border-radius:16px;padding:18px;margin-bottom:16px}.case-message{border-left:3px solid #d7ae3f;background:#0c1217;border-radius:8px;padding:13px;margin:10px 0}.case-message.internal{border-color:#9d61ff;background:#171021}.case-message.customer{border-color:#2dd4bf}.case-meta{display:flex;justify-content:space-between;gap:12px;color:#9ba7b3;font-size:12px}.case-form textarea,.case-form select{width:100%;box-sizing:border-box;background:#05080b;color:#fff;border:1px solid #43505c;border-radius:9px;padding:11px;margin:8px 0}.case-btn{background:#f4c94d;color:#090909;border:0;border-radius:9px;padding:10px 15px;font-weight:700;cursor:pointer}.case-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}.case-label{color:#9ba7b3;font-size:12px}.case-value{font-weight:700}.case-attachment{display:inline-block;color:#f4c94d;margin:6px 8px 0 0}@media(max-width:900px){.case-layout{grid-template-columns:1fr}.case-grid{grid-template-columns:1fr}}
</style>
<div class="page-header"><div><div class="page-eyebrow">SUPPORT · {{ $ticket->public_reference }}</div><h1>{{ $ticket->subject }}</h1><p>{{ $ticket->category?->label }} · {{ strtoupper($ticket->priority) }} · {{ str_replace('_',' ',strtoupper($ticket->status)) }}</p></div><a class="case-btn" href="{{ route('admin.support.index') }}">Back to queue</a></div>
@if(session('status'))<div class="admin-form-callout"><strong>{{ session('status') }}</strong></div>@endif
<div class="case-layout"><main>
<section class="case-card"><h2>Conversation</h2><div id="case-conversation">
@foreach($ticket->messages as $message)
<article class="case-message {{ $message->visibility === 'internal' ? 'internal' : $message->sender_type }}" data-cursor="{{ $message->id }}">
<div class="case-meta"><strong>{{ $message->visibility === 'internal' ? 'Internal note' : ucfirst($message->sender_type) }}</strong><time>{{ $message->created_at->format('Y-m-d H:i') }}</time></div>
<p style="white-space:pre-wrap">{{ $message->body }}</p>
@foreach($message->attachments as $attachment)<a class="case-attachment" href="{{ route('admin.support.attachments.download',$attachment) }}">Download {{ $attachment->original_name }}</a>@endforeach
</article>
@endforeach
</div></section>
@if($canReply)<form class="case-card case-form" method="POST" action="{{ route('admin.support.reply',$ticket) }}">@csrf<h3>Public customer reply</h3><textarea name="message" rows="5" required maxlength="10000"></textarea><button class="case-btn">Send reply</button></form>@endif
@if($canNote)<form class="case-card case-form" method="POST" action="{{ route('admin.support.notes.store',$ticket) }}">@csrf<h3>Internal note</h3><p class="case-label">Admin-only. Never returned by the customer API or customer email.</p><textarea name="message" rows="4" required maxlength="10000"></textarea><button class="case-btn">Save internal note</button></form>@endif
</main><aside>
<section class="case-card"><h3>Customer</h3><div class="case-grid"><div><div class="case-label">Name</div><div class="case-value">{{ $ticket->user->name }}</div></div><div><div class="case-label">Account</div><div class="case-value">{{ $ticket->user->account_type }}</div></div><div><div class="case-label">Verified email</div><div class="case-value">{{ $ticket->user->email_verified_at ? $ticket->user->email : 'Not verified' }}</div></div><div><div class="case-label">Country</div><div class="case-value">{{ $ticket->user->country_code ?: '—' }}</div></div></div></section>
<section class="case-card"><h3>Assignment</h3><p>{{ $ticket->assignedAdmin?->name ?? 'Unassigned' }}</p>@if(!$ticket->assigned_admin_id && $canClaim)<form method="POST" action="{{ route('admin.support.claim',$ticket) }}">@csrf<button class="case-btn">Claim ticket</button></form>@endif</section>
@if($canManage)<form class="case-card case-form" method="POST" action="{{ route('admin.support.status.update',$ticket) }}">@csrf @method('PATCH')<h3>Lifecycle</h3><select name="status">@foreach(['in_progress','waiting_for_customer','resolved','closed'] as $status)<option value="{{ $status }}">{{ str_replace('_',' ',ucfirst($status)) }}</option>@endforeach</select><button class="case-btn">Update status</button></form>@endif
<section class="case-card"><h3>SLA</h3><p><span class="case-label">First response:</span><br>{{ $ticket->first_responded_at?->format('Y-m-d H:i') ?? $ticket->first_response_due_at?->format('Y-m-d H:i') ?? 'Not configured' }}</p><p><span class="case-label">Resolution due:</span><br>{{ $ticket->resolution_due_at?->format('Y-m-d H:i') ?? 'Not configured' }}</p></section>
</aside></div>
@if($ticket->latestChatSession && in_array($ticket->latestChatSession->state, ['assigned','active'], true) && $ticket->assigned_admin_id === auth('admin')->id())
<script>
(() => {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const beat = () => fetch(@json(route('admin.support.heartbeat', $ticket)), {method:'POST',headers:{'X-CSRF-TOKEN':token,'Accept':'application/json'}}).catch(() => {});
    beat(); window.setInterval(beat, 30000);
})();
</script>
@endif
<script>
(() => {
    const conversation = document.getElementById('case-conversation');
    if (!conversation) return;
    let cursor = Math.max(0, ...Array.from(conversation.querySelectorAll('[data-cursor]')).map((item) => Number(item.dataset.cursor) || 0));
    const poll = async () => {
        try {
            const response = await fetch(@json(route('admin.support.events', $ticket)) + '?after=' + cursor, {headers:{'Accept':'application/json'}});
            if (!response.ok) return;
            const payload = await response.json();
            for (const message of payload.data?.items ?? []) {
                if (conversation.querySelector(`[data-cursor="${message.cursor}"]`)) continue;
                const article = document.createElement('article');
                article.className = 'case-message ' + (message.visibility === 'internal' ? 'internal' : message.sender_type);
                article.dataset.cursor = message.cursor;
                const meta = document.createElement('div');
                meta.className = 'case-meta';
                const sender = document.createElement('strong');
                sender.textContent = message.visibility === 'internal' ? 'Internal note' : message.sender_type.charAt(0).toUpperCase() + message.sender_type.slice(1);
                const time = document.createElement('time');
                time.textContent = message.created_at ? new Date(message.created_at).toLocaleString() : '';
                meta.append(sender, time);
                const body = document.createElement('p');
                body.style.whiteSpace = 'pre-wrap';
                body.textContent = message.message;
                article.append(meta, body);
                for (const attachment of message.attachments ?? []) {
                    const link = document.createElement('a');
                    link.className = 'case-attachment';
                    link.href = attachment.download_url;
                    link.textContent = 'Download ' + attachment.name;
                    article.append(link);
                }
                conversation.append(article);
                cursor = Math.max(cursor, Number(message.cursor) || cursor);
            }
        } catch (_) {}
    };
    window.setInterval(poll, 3000);
})();
</script>
@endsection
