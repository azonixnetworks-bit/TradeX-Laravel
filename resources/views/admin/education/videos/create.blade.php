{{-- TradeX Admin — ADMIN-03A Video Upload + HLS --}}
@extends('admin.layouts.app')
@section('title', 'Upload Video | TradeX Education')
@section('page-title', 'Education')

@push('styles')
<style>
    .form-wrap{max-width:1100px;margin:0 auto}.context{padding:18px 21px;margin-bottom:20px}.context h2{margin:5px 0;font-size:18px}.context p{margin:0;opacity:.58;font-size:12px}
    .form-panel{padding:24px}.grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.field.full{grid-column:1/-1}
    .field label{display:block;margin-bottom:7px;font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:.05em;opacity:.7}.field input,.field textarea,.field select{width:100%;box-sizing:border-box;border:1px solid rgba(255,255,255,.09);border-radius:10px;background:rgba(255,255,255,.025);color:inherit;padding:11px 12px;font:inherit;outline:none}.field textarea{min-height:105px;resize:vertical}.field textarea.long{min-height:170px}.field select option{color:#111}
    .help{display:block;margin-top:6px;font-size:10px;opacity:.48;line-height:1.45}.notice{grid-column:1/-1;padding:13px 14px;border:1px solid rgba(218,178,88,.18);border-radius:11px;background:rgba(218,178,88,.05);font-size:11px;line-height:1.6;opacity:.88}
    .upload-box{position:relative;padding:25px;border:1px dashed rgba(218,178,88,.32);border-radius:14px;background:rgba(218,178,88,.035)}.upload-box input[type=file]{padding:14px;background:rgba(0,0,0,.15);border-style:solid}.upload-title{display:block;margin-bottom:7px;font-size:13px;font-weight:800}.upload-sub{display:block;margin-bottom:14px;font-size:11px;line-height:1.55;opacity:.58}
    .pipeline{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:8px;grid-column:1/-1}.pipeline-step{padding:11px 9px;border:1px solid rgba(255,255,255,.07);border-radius:10px;text-align:center;font-size:9px;font-weight:800;letter-spacing:.04em;opacity:.75}.pipeline-arrow{display:none}
    .check{display:flex;gap:9px;align-items:flex-start;padding:12px;border:1px solid rgba(255,255,255,.07);border-radius:10px}.check input{width:auto;margin-top:2px}
    .actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px;padding-top:19px;border-top:1px solid rgba(255,255,255,.06)}.errors{margin-bottom:17px;padding:13px 15px;border:1px solid rgba(255,91,91,.22);border-radius:10px;background:rgba(255,91,91,.05)}.errors ul{margin:7px 0 0;padding-left:18px;font-size:11px}
    @media(max-width:760px){.grid{grid-template-columns:1fr}.field.full,.notice,.pipeline{grid-column:auto}.pipeline{grid-template-columns:1fr}.actions{flex-direction:column-reverse}.actions a,.actions button{width:100%;box-sizing:border-box;text-align:center}}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">EDUCATION MANAGEMENT · ADMIN-03A</div>
        <h1>Upload <span>Video</span></h1>
        <p>Upload a protected lesson video. TradeX will queue HLS processing automatically.</p>
    </div>
    <div>
        <a class="secondary-btn" href="{{ route('admin.education.courses.modules.lessons.videos.index', ['course'=>$course,'module'=>$module,'lesson'=>$lesson]) }}">← Back to Videos</a>
    </div>
</div>

<div class="form-wrap">
    <section class="panel context">
        <div class="panel-eyebrow">{{ $module->code }} · {{ $lesson->code }}</div>
        <h2>{{ $lesson->title }}</h2>
        <p>{{ $lesson->short_description ?: 'No lesson summary has been added.' }}</p>
    </section>

    @if($errors->any())
        <div class="errors">
            <strong>Please correct the following fields.</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        $maxUploadGb = round(
            ((int) config('tradex.media.max_upload_kb', 4194304)) / 1024 / 1024,
            2
        );
    @endphp

    <form
        method="POST"
        enctype="multipart/form-data"
        action="{{ route('admin.education.courses.modules.lessons.videos.store', ['course'=>$course,'module'=>$module,'lesson'=>$lesson]) }}"
    >
        @csrf

        <section class="panel form-panel">
            <div class="panel-header">
                <div>
                    <div class="panel-eyebrow">PROTECTED VIDEO PIPELINE</div>
                    <h2>Video Upload</h2>
                </div>
            </div>

            <div class="grid">
                <div class="notice">
                    Source video is saved to protected storage. HLS paths, duration, file size, MIME metadata and thumbnail are worker-controlled. No public/signed playback URL is stored by this form.
                </div>

                <div class="field full">
                    <label>Title</label>
                    <input name="title" maxlength="200" required value="{{ old('title') }}">
                </div>

                <div class="field full">
                    <label>Short Description</label>
                    <textarea name="short_description" maxlength="500">{{ old('short_description') }}</textarea>
                </div>

                <div class="field full">
                    <label>Description</label>
                    <textarea class="long" name="description">{{ old('description') }}</textarea>
                </div>

                <div class="field full">
                    <label>Source Video</label>
                    <div class="upload-box">
                        <span class="upload-title">Choose lesson video</span>
                        <span class="upload-sub">
                            Accepted: MP4, MOV, M4V, WebM, MKV · TradeX configured limit: {{ $maxUploadGb }} GiB.
                            PHP upload_max_filesize/post_max_size must also allow the selected file size.
                        </span>
                        <input
                            type="file"
                            name="video_file"
                            accept=".mp4,.mov,.m4v,.webm,.mkv,video/mp4,video/quicktime,video/webm"
                            required
                        >
                    </div>
                </div>

                <div class="field">
                    <label>Protected Storage</label>
                    <select name="storage_disk" required>
                        <option value="private" @selected(old('storage_disk','private') === 'private')>Private Local Storage</option>
                        <option value="s3-private" @selected(old('storage_disk') === 's3-private')>Private S3 Storage</option>
                    </select>
                    <span class="help">Use s3-private only after AWS/S3 credentials are configured.</span>
                </div>

                <div class="field">
                    <label>Display Order</label>
                    <input type="number" min="0" max="4294967295" name="sort_order" value="{{ old('sort_order',0) }}">
                    <span class="help">Lower numbers are displayed first.</span>
                </div>

                <div class="field full">
                    <label for="video-is-downloadable">Download Policy</label>
                    <div class="check">
                        <input type="hidden" name="is_downloadable" value="0">
                        <input id="video-is-downloadable" type="checkbox" name="is_downloadable" value="1" @checked((bool)old('is_downloadable',false))>
                        <span>Allow download only when product policy explicitly permits it. Premium course videos should normally remain disabled.</span>
                    </div>
                </div>

                <div class="pipeline">
                    <div class="pipeline-step">1 · PRIVATE UPLOAD</div>
                    <div class="pipeline-step">2 · QUEUE</div>
                    <div class="pipeline-step">3 · FFPROBE</div>
                    <div class="pipeline-step">4 · HLS + THUMBNAIL</div>
                    <div class="pipeline-step">5 · READY</div>
                </div>
            </div>

            <div class="actions">
                <a class="secondary-btn" href="{{ route('admin.education.courses.modules.lessons.videos.index', ['course'=>$course,'module'=>$module,'lesson'=>$lesson]) }}">Cancel</a>
                <button class="primary-btn" type="submit">Upload & Queue HLS</button>
            </div>
        </section>
    </form>
</div>
@endsection
