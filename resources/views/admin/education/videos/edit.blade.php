{{-- TradeX Admin — ADMIN-03 Edit Video --}}
@extends('admin.layouts.app')
@section('title', 'Edit ' . $video->title . ' | TradeX Education')
@section('page-title', 'Education')

@push('styles')
<link rel="stylesheet" href="{{ asset('admin/css/video-processing-progress.css') }}?v=20260925-1">
<style>
    .form-wrap{max-width:1100px;margin:0 auto}.context{padding:18px 21px;margin-bottom:20px}.context h2{margin:5px 0;font-size:18px}.context p{margin:0;opacity:.58;font-size:12px}
    .form-panel{padding:24px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.field.full{grid-column:1/-1}
    .field label{display:block;margin-bottom:7px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;opacity:.7}.field input,.field textarea{width:100%;box-sizing:border-box;border:1px solid rgba(255,255,255,.09);border-radius:10px;background:rgba(255,255,255,.025);color:inherit;padding:11px 12px;font:inherit;outline:none}.field textarea{min-height:105px;resize:vertical}.field textarea.long{min-height:170px}
    .help{display:block;margin-top:6px;font-size:10px;opacity:.48;line-height:1.45}.notice{grid-column:1/-1;padding:13px 14px;border:1px solid rgba(218,178,88,.18);border-radius:11px;background:rgba(218,178,88,.05);font-size:11px;line-height:1.6;opacity:.82}
    .check{display:flex;gap:9px;align-items:flex-start;padding:12px;border:1px solid rgba(255,255,255,.07);border-radius:10px}.check input{width:auto;margin-top:2px}
    .actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px;padding-top:19px;border-top:1px solid rgba(255,255,255,.06)}.retry-wrap{display:flex;justify-content:flex-end;margin-top:12px}.retry-btn{border:1px solid rgba(255,193,115,.3);border-radius:10px;padding:10px 14px;background:rgba(255,193,115,.1);color:#ffc173;font:inherit;font-size:11px;font-weight:800;cursor:pointer}.errors{margin-bottom:17px;padding:13px 15px;border:1px solid rgba(255,91,91,.22);border-radius:10px;background:rgba(255,91,91,.05)}.errors ul{margin:7px 0 0;padding-left:18px;font-size:11px}
    @media(max-width:760px){.grid{grid-template-columns:1fr}.field.full,.notice{grid-column:auto}.actions{flex-direction:column-reverse}.actions a,.actions button{width:100%;box-sizing:border-box;text-align:center}}
</style>
@endpush

@section('content')
<div class="page-header"><div><div class="page-eyebrow">EDUCATION MANAGEMENT</div><h1>Edit <span>Video</span></h1><p>Update protected media metadata without changing worker processing state or publication state.</p></div><div><a class="secondary-btn" href="{{ route('admin.education.courses.modules.lessons.videos.index', ['course'=>$course,'module'=>$module,'lesson'=>$lesson]) }}">← Back to Videos</a></div></div>
<div class="form-wrap">
<section class="panel context"><div class="panel-eyebrow">{{ $lesson->code }} · {{ $video->processing_status }}</div><h2>{{ $video->title }}</h2><p>Publication: {{ $video->status }} · Processing: {{ $video->processing_status }}</p>@include('admin.education.videos.partials.processing-progress', compact('video', 'course', 'module', 'lesson'))@if($video->processing_status === \App\Models\LessonVideo::PROCESSING_FAILED)<form class="retry-wrap" method="POST" action="{{ route('admin.education.courses.modules.lessons.videos.retry-processing', compact('course', 'module', 'lesson', 'video')) }}" onsubmit="return confirm('Retry HLS processing with the existing uploaded video file?');">@csrf<button class="retry-btn" type="submit">↻ Retry Processing</button></form>@endif</section>
@if($errors->any())<div class="errors"><strong>Please correct the following fields.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
<form method="POST" action="{{ route('admin.education.courses.modules.lessons.videos.update', ['course'=>$course,'module'=>$module,'lesson'=>$lesson,'video'=>$video]) }}">@csrf @method('PATCH')
<section class="panel form-panel"><div class="panel-header"><div><div class="panel-eyebrow">PROTECTED MEDIA METADATA</div><h2>Video Details</h2></div></div>
<div class="grid">
<div class="notice">Processing fields are read-only and worker-controlled. Signed/public URLs must not be stored. Publication remains a separate education.publish action.</div>
<div class="field full"><label>Title</label><input name="title" maxlength="200" required value="{{ old('title',$video->title) }}"></div>
<div class="field full"><label>Short Description</label><textarea name="short_description" maxlength="500">{{ old('short_description',$video->short_description) }}</textarea></div>
<div class="field full"><label>Description</label><textarea class="long" name="description">{{ old('description',$video->description) }}</textarea></div>
<div class="field"><label>Storage Disk</label><input name="storage_disk" maxlength="50" required value="{{ old('storage_disk',$video->storage_disk) }}"></div>
<div class="field"><label>Source Path</label><input name="source_path" maxlength="500" required value="{{ old('source_path',$video->source_path) }}"></div>
<div class="field"><label>HLS Master Path</label><input name="hls_master_path" maxlength="500" value="{{ old('hls_master_path',$video->hls_master_path) }}"></div>
<div class="field"><label>Streaming Asset Key</label><input name="streaming_asset_key" maxlength="255" value="{{ old('streaming_asset_key',$video->streaming_asset_key) }}"></div>
<div class="field"><label>Thumbnail Path</label><input name="thumbnail_path" maxlength="500" value="{{ old('thumbnail_path',$video->thumbnail_path) }}"></div>
<div class="field"><label>MIME Type</label><input name="mime_type" maxlength="120" value="{{ old('mime_type',$video->mime_type) }}"></div>
<div class="field"><label>Duration Seconds</label><input type="number" min="0" name="duration_seconds" value="{{ old('duration_seconds',$video->duration_seconds) }}"></div>
<div class="field"><label>File Size Bytes</label><input type="number" min="0" name="file_size_bytes" value="{{ old('file_size_bytes',$video->file_size_bytes) }}"></div>
<div class="field"><label>Display Order</label><input type="number" min="0" max="4294967295" name="sort_order" value="{{ old('sort_order',$video->sort_order) }}"></div>
<div class="field"><label for="video-is-downloadable">Download Policy</label><div class="check"><input type="hidden" name="is_downloadable" value="0"><input id="video-is-downloadable" type="checkbox" name="is_downloadable" value="1" @checked((bool)old('is_downloadable',$video->is_downloadable))><span>Allow download only when product policy explicitly permits it.</span></div></div>
</div>
<div class="actions"><a class="secondary-btn" href="{{ route('admin.education.courses.modules.lessons.videos.index', ['course'=>$course,'module'=>$module,'lesson'=>$lesson]) }}">Cancel</a><button class="primary-btn" type="submit">Save Video</button></div>
</section></form></div>
@endsection


@push('scripts')
<script src="{{ asset('admin/js/video-processing-progress.js') }}?v=20260925-1" defer></script>
@endpush
