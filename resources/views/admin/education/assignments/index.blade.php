{{-- TradeX Admin — ADMIN-03 Assignment Management --}}

@extends('admin.layouts.app')

@section('title', $lesson->title . ' Assignments | TradeX Education')
@section('page-title', 'Education')

@push('styles')
<style>
    .edu-context{display:flex;justify-content:space-between;gap:18px;padding:20px 22px;margin-bottom:22px;align-items:flex-start}
    .edu-context h2{margin:6px 0;font-size:19px}.edu-context p{margin:0;opacity:.6;font-size:12px;line-height:1.6}.edu-meta{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
    .edu-body{padding:24px;border-top:1px solid rgba(255,255,255,.06)}.assignment-list{display:grid;gap:14px}
    .assignment-card{display:grid;grid-template-columns:58px minmax(0,1fr) 160px 200px;gap:16px;align-items:center;padding:17px;border:1px solid rgba(255,255,255,.07);border-radius:14px;background:rgba(255,255,255,.022)}
    .assignment-order{display:grid;place-items:center;width:52px;height:52px;border-radius:13px;background:rgba(218,178,88,.09);color:#ddb65e;font-weight:800;font-size:13px}
    .assignment-copy strong{display:block;margin-bottom:5px;font-size:14px}.assignment-copy .heading{display:block;margin-bottom:6px;color:#ddb65e;font-size:11px;font-weight:700}.assignment-copy p{margin:0;font-size:12px;line-height:1.55;opacity:.56}
    .tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:9px}.tag{padding:5px 8px;border-radius:999px;border:1px solid rgba(255,255,255,.08);font-size:9px;font-weight:800}.tag.whatsapp{color:#79dda5;border-color:rgba(121,221,165,.22);background:rgba(121,221,165,.06)}.tag.required{color:#ddb65e;border-color:rgba(218,178,88,.2);background:rgba(218,178,88,.06)}
    .metrics{display:grid;gap:8px}.metric{padding:9px 10px;border:1px solid rgba(255,255,255,.06);border-radius:10px;background:rgba(255,255,255,.018)}.metric span{display:block;margin-bottom:3px;font-size:9px;opacity:.48;text-transform:uppercase}.metric strong{font-size:12px}
    .actions{display:grid;gap:8px}.actions form{margin:0}.actions textarea{width:100%;box-sizing:border-box;min-height:54px;margin-bottom:6px;padding:8px 9px;border:1px solid rgba(255,255,255,.08);border-radius:9px;background:rgba(255,255,255,.025);color:inherit;font:inherit;font-size:10px;resize:vertical}
    .action-link,.action-btn,.disabled{display:block;width:100%;box-sizing:border-box;border-radius:9px;padding:9px 10px;text-align:center;font:inherit;font-size:10px;font-weight:700;text-decoration:none}.action-link{border:1px solid rgba(255,255,255,.09);background:rgba(255,255,255,.035);color:inherit}.action-btn{cursor:pointer}.publish{border:1px solid rgba(218,178,88,.25);background:rgba(218,178,88,.09);color:#e0ba65}.unpublish{border:1px solid rgba(255,179,71,.21);background:rgba(255,179,71,.07);color:#ffc173}.delete{border:1px solid rgba(255,91,91,.24);background:rgba(255,91,91,.06);color:#ff8585}.disabled{border:1px solid rgba(255,255,255,.06);opacity:.48}
    .empty{padding:45px 20px;text-align:center}.empty strong{display:block;margin-bottom:7px}.empty span{opacity:.58;font-size:12px}.empty .primary-btn{display:inline-block;margin-top:17px}
    @media(max-width:1120px){.assignment-card{grid-template-columns:58px minmax(0,1fr) 145px}.actions{grid-column:2/-1;grid-template-columns:repeat(3,minmax(0,1fr))}}@media(max-width:760px){.edu-context{flex-direction:column}.edu-meta{justify-content:flex-start}.assignment-card{grid-template-columns:52px minmax(0,1fr)}.metrics,.actions{grid-column:2}.metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.actions{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="page-header">
    <div><div class="page-eyebrow">EDUCATION MANAGEMENT</div><h1>Lesson <span>Assignments</span></h1><p>Manage assignment instructions and approved external submission channels. Learner files are never uploaded into this admin workflow.</p></div>
    <div><a href="{{ route('admin.education.courses.modules.lessons.index', ['course'=>$course,'module'=>$module]) }}" class="secondary-btn">← Back to Lessons</a></div>
</div>

<section class="panel edu-context">
    <div><div class="panel-eyebrow">{{ $course->code }} · {{ $module->code }} · {{ $lesson->code }}</div><h2>{{ $lesson->title }}</h2><p>{{ $lesson->short_description ?: 'No lesson summary has been added.' }}</p></div>
    <div class="edu-meta"><span class="user-status {{ $lesson->status === 'PUBLISHED' ? 'status-active' : ($lesson->status === 'ARCHIVED' ? 'status-blocked' : 'status-pending') }}"><span class="user-status-dot"></span>{{ $lesson->status }}</span></div>
</section>

<div class="user-stat-grid">
    <div class="user-stat-card"><span>Total Assignments</span><strong>{{ number_format($stats['total']) }}</strong></div>
    <div class="user-stat-card"><span>Published</span><strong class="text-green">{{ number_format($stats['published']) }}</strong></div>
    <div class="user-stat-card"><span>Draft</span><strong class="text-orange">{{ number_format($stats['draft']) }}</strong></div>
    <div class="user-stat-card"><span>Required</span><strong class="text-gold">{{ number_format($stats['required']) }}</strong></div>
    <div class="user-stat-card"><span>WhatsApp</span><strong>{{ number_format($stats['whatsapp']) }}</strong></div>
</div>

<section class="panel users-panel">
    <div class="panel-header">
        <div><div class="panel-eyebrow">EXTERNAL SUBMISSION POLICY</div><h2>Assignments</h2></div>
        @if($permissions['create'] ?? false)<a href="{{ route('admin.education.courses.modules.lessons.assignments.create', ['course'=>$course,'module'=>$module,'lesson'=>$lesson]) }}" class="primary-btn">+ New Assignment</a>@endif
    </div>
    <div class="edu-body">
        @forelse($assignments as $assignment)
            @if($loop->first)<div class="assignment-list">@endif
            @php
                $statusClass = match($assignment->status){'PUBLISHED'=>'status-active','ARCHIVED'=>'status-blocked',default=>'status-pending'};
                $canDelete = ($permissions['delete'] ?? false) && $assignment->status !== 'PUBLISHED';
            @endphp
            <article class="assignment-card">
                <div class="assignment-order">{{ str_pad((string)$loop->iteration,2,'0',STR_PAD_LEFT) }}</div>
                <div class="assignment-copy">
                    <strong>{{ $assignment->title }}</strong>
                    @if($assignment->heading)<span class="heading">{{ $assignment->heading }}</span>@endif
                    <p>{{ $assignment->description ? \Illuminate\Support\Str::limit($assignment->description, 180) : 'No assignment description has been added.' }}</p>
                    <div class="tags">
                        <span class="tag {{ $assignment->submission_method === 'WHATSAPP' ? 'whatsapp' : '' }}">{{ $assignment->submission_method }}</span>
                        @if($assignment->is_required)<span class="tag required">REQUIRED</span>@else<span class="tag">OPTIONAL</span>@endif
                    </div>
                </div>
                <div class="metrics">
                    <div class="metric"><span>Status</span><div class="user-status {{ $statusClass }}"><span class="user-status-dot"></span>{{ $assignment->status }}</div></div>
                    <div class="metric"><span>Reference Image</span><strong>{{ $assignment->hasReferenceImage() ? 'YES' : 'NO' }}</strong></div>
                    <div class="metric"><span>Order</span><strong>{{ number_format($assignment->sort_order) }}</strong></div>
                    <div class="metric"><span>Published</span><strong>{{ $assignment->published_at ? $assignment->published_at->format('d M Y') : '—' }}</strong></div>
                </div>
                <div class="actions">
                    @if($permissions['update'] ?? false)<a class="action-link" href="{{ route('admin.education.courses.modules.lessons.assignments.edit', ['course'=>$course,'module'=>$module,'lesson'=>$lesson,'assignment'=>$assignment]) }}">Edit Assignment</a>@endif
                    @if(($permissions['publish'] ?? false) && $assignment->status === 'DRAFT')
                        <form method="POST" action="{{ route('admin.education.courses.modules.lessons.assignments.publication', ['course'=>$course,'module'=>$module,'lesson'=>$lesson,'assignment'=>$assignment]) }}" onsubmit="return confirm('Publish this assignment?');">@csrf @method('PATCH')<input type="hidden" name="action" value="PUBLISH"><textarea name="reason" maxlength="1000" placeholder="Optional audit reason"></textarea><button class="action-btn publish" type="submit">Publish Assignment</button></form>
                    @elseif(($permissions['publish'] ?? false) && $assignment->status === 'PUBLISHED')
                        <form method="POST" action="{{ route('admin.education.courses.modules.lessons.assignments.publication', ['course'=>$course,'module'=>$module,'lesson'=>$lesson,'assignment'=>$assignment]) }}" onsubmit="return confirm('Unpublish this assignment?');">@csrf @method('PATCH')<input type="hidden" name="action" value="UNPUBLISH"><textarea name="reason" maxlength="1000" placeholder="Optional audit reason"></textarea><button class="action-btn unpublish" type="submit">Unpublish Assignment</button></form>
                    @endif
                    @if($canDelete)
                        <form method="POST" action="{{ route('admin.education.courses.modules.lessons.assignments.destroy', ['course'=>$course,'module'=>$module,'lesson'=>$lesson,'assignment'=>$assignment]) }}" onsubmit="return confirm('Delete this assignment?');">@csrf @method('DELETE')<button class="action-btn delete" type="submit">Delete Assignment</button></form>
                    @elseif($permissions['delete'] ?? false)<div class="disabled">Unpublish before deletion.</div>@endif
                </div>
            </article>
            @if($loop->last)</div>@endif
        @empty
            <div class="empty"><strong>No assignments added yet</strong><span>Create the first assignment. TradeX stores assignment instructions only; learner submission stays external.</span>@if($permissions['create'] ?? false)<a href="{{ route('admin.education.courses.modules.lessons.assignments.create', ['course'=>$course,'module'=>$module,'lesson'=>$lesson]) }}" class="primary-btn">+ Create First Assignment</a>@endif</div>
        @endforelse
    </div>
</section>
@endsection
