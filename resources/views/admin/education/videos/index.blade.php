{{-- TradeX Admin — ADMIN-03 / ADMIN-03B Video Management --}}

@extends('admin.layouts.app')

@section('title', $lesson->title . ' Videos | TradeX Education')
@section('page-title', 'Education')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/video-processing-progress.css') }}?v=20260927-hls-live-1">
<style>
    .edu-context {display:flex;justify-content:space-between;gap:18px;padding:20px 22px;margin-bottom:22px;align-items:flex-start}
    .edu-context h2{margin:6px 0;font-size:19px}.edu-context p{margin:0;opacity:.6;font-size:12px;line-height:1.6}
    .edu-meta{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}
    .edu-body{padding:24px;border-top:1px solid rgba(255,255,255,.06)}
    .video-list{display:grid;gap:14px}.video-card{display:grid;grid-template-columns:58px minmax(0,1fr) 165px 200px;gap:16px;align-items:center;padding:17px;border:1px solid rgba(255,255,255,.07);border-radius:14px;background:rgba(255,255,255,.022)}
    .video-order{display:grid;place-items:center;width:52px;height:52px;border-radius:13px;background:rgba(218,178,88,.09);color:#ddb65e;font-weight:800;font-size:13px}
    .video-copy strong{display:block;margin-bottom:6px;font-size:14px}.video-copy p{margin:0;font-size:12px;line-height:1.55;opacity:.56}
    .video-tags{display:flex;gap:6px;flex-wrap:wrap;margin-top:9px}.video-tag{padding:5px 8px;border-radius:999px;border:1px solid rgba(255,255,255,.08);font-size:9px;font-weight:800;letter-spacing:.04em}
    .video-tag.ready{color:#77d9a6;border-color:rgba(119,217,166,.22);background:rgba(119,217,166,.06)}.video-tag.failed{color:#ff8585;border-color:rgba(255,133,133,.22);background:rgba(255,133,133,.06)}.video-tag.processing{color:#ffc173;border-color:rgba(255,193,115,.22);background:rgba(255,193,115,.06)}
    .video-metrics{display:grid;gap:8px}.video-metric{padding:9px 10px;border:1px solid rgba(255,255,255,.06);border-radius:10px;background:rgba(255,255,255,.018)}
    .video-metric span{display:block;margin-bottom:3px;font-size:9px;opacity:.48;text-transform:uppercase}.video-metric strong{font-size:12px}
    .video-actions{display:grid;gap:8px}.video-actions form{margin:0}.video-actions textarea{width:100%;box-sizing:border-box;min-height:54px;margin-bottom:6px;padding:8px 9px;border:1px solid rgba(255,255,255,.08);border-radius:9px;background:rgba(255,255,255,.025);color:inherit;font:inherit;font-size:10px;resize:vertical}
    .action-link,.action-btn,.action-disabled{display:block;width:100%;box-sizing:border-box;border-radius:9px;padding:9px 10px;text-align:center;font:inherit;font-size:10px;font-weight:700;text-decoration:none}
    .action-link{border:1px solid rgba(255,255,255,.09);background:rgba(255,255,255,.035);color:inherit}.action-btn{cursor:pointer}.preview{border-color:rgba(119,217,166,.22);background:rgba(119,217,166,.06);color:#77d9a6}.retry{border:1px solid rgba(255,193,115,.28);background:rgba(255,193,115,.09);color:#ffc173}.publish{border:1px solid rgba(218,178,88,.25);background:rgba(218,178,88,.09);color:#e0ba65}.unpublish{border:1px solid rgba(255,179,71,.21);background:rgba(255,179,71,.07);color:#ffc173}.delete{border:1px solid rgba(255,91,91,.24);background:rgba(255,91,91,.06);color:#ff8585}.action-disabled{border:1px solid rgba(255,255,255,.06);opacity:.48;line-height:1.45}
    .empty{padding:45px 20px;text-align:center}.empty strong{display:block;margin-bottom:7px}.empty span{opacity:.58;font-size:12px}.empty .primary-btn{display:inline-block;margin-top:17px}
    @media(max-width:1120px){.video-card{grid-template-columns:58px minmax(0,1fr) 150px}.video-actions{grid-column:2/-1;grid-template-columns:repeat(3,minmax(0,1fr))}}
    @media(max-width:760px){.edu-context{flex-direction:column}.edu-meta{justify-content:flex-start}.video-card{grid-template-columns:52px minmax(0,1fr)}.video-metrics,.video-actions{grid-column:2}.video-metrics{grid-template-columns:repeat(2,minmax(0,1fr))}.video-actions{grid-template-columns:1fr}}
</style>
@endpush

@section('content')

<div class="page-header">
    <div>
        <div class="page-eyebrow">EDUCATION MANAGEMENT</div>
        <h1>Lesson <span>Videos</span></h1>
        <p>Manage protected video metadata, processing readiness and publication state.</p>
    </div>
    <div>
        <a href="{{ route('admin.education.courses.modules.lessons.index', ['course'=>$course,'module'=>$module]) }}" class="secondary-btn">← Back to Lessons</a>
    </div>
</div>

<section class="panel edu-context">
    <div>
        <div class="panel-eyebrow">{{ $course->code }} · {{ $module->code }} · {{ $lesson->code }}</div>
        <h2>{{ $lesson->title }}</h2>
        <p>{{ $lesson->short_description ?: 'No lesson summary has been added.' }}</p>
    </div>
    <div class="edu-meta">
        @if($lesson->is_preview)<span class="account-badge free">PREVIEW</span>@endif
        <span class="user-status {{ $lesson->status === 'PUBLISHED' ? 'status-active' : ($lesson->status === 'ARCHIVED' ? 'status-blocked' : 'status-pending') }}"><span class="user-status-dot"></span>{{ $lesson->status }}</span>
    </div>
</section>

<div class="user-stat-grid">
    <div class="user-stat-card"><span>Total Videos</span><strong>{{ number_format($stats['total']) }}</strong></div>
    <div class="user-stat-card"><span>Published</span><strong class="text-green">{{ number_format($stats['published']) }}</strong></div>
    <div class="user-stat-card"><span>Draft</span><strong class="text-orange">{{ number_format($stats['draft']) }}</strong></div>
    <div class="user-stat-card"><span>Ready</span><strong class="text-gold">{{ number_format($stats['ready']) }}</strong></div>
    <div class="user-stat-card"><span>Processing</span><strong>{{ number_format($stats['processing']) }}</strong></div>
    <div class="user-stat-card"><span>Failed</span><strong class="text-red">{{ number_format($stats['failed']) }}</strong></div>
</div>

<section class="panel users-panel">
    <div class="panel-header">
        <div><div class="panel-eyebrow">PROTECTED MEDIA</div><h2>Videos</h2></div>
        @if($permissions['create'] ?? false)
            <a href="{{ route('admin.education.courses.modules.lessons.videos.create', ['course'=>$course,'module'=>$module,'lesson'=>$lesson]) }}" class="primary-btn">+ New Video</a>
        @endif
    </div>

    <div class="edu-body">
        @forelse($videos as $video)
            @if($loop->first)<div class="video-list">@endif
            @php
                $statusClass = match($video->status){'PUBLISHED'=>'status-active','ARCHIVED'=>'status-blocked',default=>'status-pending'};
                $processingClass = match($video->processing_status){'READY'=>'ready','FAILED'=>'failed','PROCESSING'=>'processing',default=>''};
                $repairRequired = $video->processing_status === 'READY' && ! $video->hasPlaybackReference();
                $canPreview = ($permissions['view'] ?? false) && $video->processing_status === 'READY' && $video->hasHlsStream();
                $canPublish = ($permissions['publish'] ?? false) && $video->status === 'DRAFT' && $video->processing_status === 'READY' && $video->hasPlaybackReference();
                $canDelete = ($permissions['delete'] ?? false) && $video->status !== 'PUBLISHED' && $video->processing_status !== 'PROCESSING';
            @endphp
            <article class="video-card">
                <div class="video-order">{{ str_pad((string)$loop->iteration,2,'0',STR_PAD_LEFT) }}</div>
                <div class="video-copy">
                    <strong>{{ $video->title }}</strong>
                    <p>{{ $video->short_description ?: 'No video summary has been added.' }}</p>
                    <div class="video-tags">
                        <span class="video-tag {{ $processingClass }}">{{ $video->processing_status }}</span>
                        <span class="video-tag">{{ $video->storage_disk }}</span>
                        @if($video->is_downloadable)<span class="video-tag">DOWNLOADABLE</span>@endif
                    </div>
                    @include('admin.education.videos.partials.processing-progress', compact('video', 'course', 'module', 'lesson'))
                </div>
                <div class="video-metrics">
                    <div class="video-metric"><span>Status</span><div class="user-status {{ $statusClass }}"><span class="user-status-dot"></span>{{ $video->status }}</div></div>
                    <div class="video-metric"><span>Duration</span><strong>{{ $video->duration_seconds !== null ? gmdate('H:i:s', $video->duration_seconds) : '—' }}</strong></div>
                    <div class="video-metric"><span>Playback Ref</span><strong>{{ $video->hasPlaybackReference() ? 'READY' : 'MISSING' }}</strong></div>
                    <div class="video-metric"><span>Order</span><strong>{{ number_format($video->sort_order) }}</strong></div>
                </div>
                <div class="video-actions">
                    @if($canPreview)
                        <a
                            class="action-link preview"
                            href="{{ route('admin.education.courses.modules.lessons.videos.preview', ['course'=>$course,'module'=>$module,'lesson'=>$lesson,'video'=>$video]) }}"
                        >
                            Preview Protected HLS
                        </a>
                    @elseif(($permissions['view'] ?? false) && $repairRequired)
                        <div class="action-disabled">Preview Protected HLS — repair required</div>
                    @endif

                    @if($permissions['update'] ?? false)
                        <a class="action-link" href="{{ route('admin.education.courses.modules.lessons.videos.edit', ['course'=>$course,'module'=>$module,'lesson'=>$lesson,'video'=>$video]) }}">Edit Video</a>
                        @if($video->processing_status === \App\Models\LessonVideo::PROCESSING_FAILED || $repairRequired)
                            <form method="POST" action="{{ route('admin.education.courses.modules.lessons.videos.retry-processing', compact('course', 'module', 'lesson', 'video')) }}" onsubmit="return confirm('{{ $repairRequired ? 'Repair the missing protected HLS playback reference using the existing source video?' : 'Retry HLS processing with the existing uploaded video file?' }}');">
                                @csrf
                                <button class="action-btn retry" type="submit">{{ $repairRequired ? '↻ Repair Protected HLS' : '↻ Retry Processing' }}</button>
                            </form>
                        @endif
                    @endif

                    @if($canPublish)
                        <form method="POST" action="{{ route('admin.education.courses.modules.lessons.videos.publication', ['course'=>$course,'module'=>$module,'lesson'=>$lesson,'video'=>$video]) }}" onsubmit="return confirm('Publish this video now?');">
                            @csrf @method('PATCH')
                            <input type="hidden" name="action" value="PUBLISH">
                            <textarea name="reason" maxlength="1000" placeholder="Optional audit reason"></textarea>
                            <button class="action-btn publish" type="submit">Publish Video</button>
                        </form>
                    @elseif(($permissions['publish'] ?? false) && $video->status === 'PUBLISHED')
                        <form method="POST" action="{{ route('admin.education.courses.modules.lessons.videos.publication', ['course'=>$course,'module'=>$module,'lesson'=>$lesson,'video'=>$video]) }}" onsubmit="return confirm('Unpublish this video?');">
                            @csrf @method('PATCH')
                            <input type="hidden" name="action" value="UNPUBLISH">
                            <textarea name="reason" maxlength="1000" placeholder="Optional audit reason"></textarea>
                            <button class="action-btn unpublish" type="submit">Unpublish Video</button>
                        </form>
                    @elseif(($permissions['publish'] ?? false) && $video->status === 'DRAFT')
                        <div class="action-disabled">{{ $repairRequired ? 'Protected playback reference is missing. Run Repair Protected HLS before publishing.' : 'Processing must be READY and a protected playback reference must exist before publishing.' }}</div>
                    @endif

                    @if($canDelete)
                        <form method="POST" action="{{ route('admin.education.courses.modules.lessons.videos.destroy', ['course'=>$course,'module'=>$module,'lesson'=>$lesson,'video'=>$video]) }}" onsubmit="return confirm('Delete this video record?');">
                            @csrf @method('DELETE')
                            <button type="submit" class="action-btn delete">Delete Video</button>
                        </form>
                    @elseif($permissions['delete'] ?? false)
                        <div class="action-disabled">{{ $video->status === 'PUBLISHED' ? 'Unpublish before deletion.' : 'Wait for active processing to finish.' }}</div>
                    @endif
                </div>
            </article>
            @if($loop->last)</div>@endif
        @empty
            <div class="empty">
                <strong>No videos added yet</strong>
                <span>Create a protected video record for this lesson. Processing state is controlled by the media pipeline.</span>
                @if($permissions['create'] ?? false)
                    <a href="{{ route('admin.education.courses.modules.lessons.videos.create', ['course'=>$course,'module'=>$module,'lesson'=>$lesson]) }}" class="primary-btn">+ Create First Video</a>
                @endif
            </div>
        @endforelse
    </div>
</section>
@endsection

@push('scripts')
<script src="{{ asset('admin/js/video-processing-progress.js') }}?v=20260927-hls-live-1" defer></script>
@endpush
