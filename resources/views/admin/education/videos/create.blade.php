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
    .upload-progress{display:none;margin-top:17px;padding:16px;border:1px solid rgba(218,178,88,.25);border-radius:12px;background:rgba(218,178,88,.045)}.upload-progress.is-visible{display:block}.upload-progress-head{display:flex;align-items:baseline;justify-content:space-between;gap:16px}.upload-progress-head strong{font-size:12px}.upload-percent{color:#e4bd61;font-size:22px;font-weight:900;letter-spacing:-.04em}.upload-progress-track{height:9px;margin:11px 0 10px;overflow:hidden;border-radius:999px;background:rgba(255,255,255,.08)}.upload-progress-fill{width:0;height:100%;border-radius:inherit;background:linear-gradient(90deg,#bd851f,#f2ce72);transition:width .2s ease}.upload-progress-meta{display:flex;flex-wrap:wrap;gap:5px 15px;color:rgba(235,240,249,.62);font-size:10px}.upload-progress-status{margin-top:10px;color:rgba(235,240,249,.76);font-size:11px;line-height:1.45}.upload-cancel{display:none;margin-top:12px;padding:7px 10px;border:1px solid rgba(255,119,119,.34);border-radius:8px;background:rgba(255,86,86,.06);color:#ffc0c0;font:inherit;font-size:10px;font-weight:800;cursor:pointer}.upload-cancel.is-visible{display:inline-flex}
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

    <form id="video-upload-form"
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
                            id="video-file-input"
                            type="file"
                            name="video_file"
                            accept=".mp4,.mov,.m4v,.webm,.mkv,video/mp4,video/quicktime,video/webm"
                            required
                        >
                        <span class="upload-sub">Or import a private cPanel upload: place it under <code>video-imports/</code> on the Private Local Storage disk, then enter its relative path below.</span>
                        <input type="text" name="source_path" value="{{ old('source_path') }}" maxlength="500" placeholder="video-imports/my-lesson.mp4">                        <div id="video-upload-progress" class="upload-progress" role="status" aria-live="polite">
                            <div class="upload-progress-head">
                                <strong id="upload-progress-label">Preparing secure upload</strong>
                                <span id="upload-progress-percent" class="upload-percent">0%</span>
                            </div>
                            <div class="upload-progress-track"><div id="upload-progress-fill" class="upload-progress-fill"></div></div>
                            <div class="upload-progress-meta">
                                <span id="upload-progress-size">0 B / 0 B</span>
                                <span id="upload-progress-speed">Measuring speed…</span>
                                <span id="upload-progress-eta">Calculating ETA…</span>
                            </div>
                            <div id="upload-progress-status" class="upload-progress-status">The page can stay open while the file transfers.</div>
                            <button id="cancel-video-upload" class="upload-cancel" type="button">Cancel upload</button>
                        </div>
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
<script>
(() => {
    const form = document.getElementById('video-upload-form');
    const fileInput = document.getElementById('video-file-input');
    const progress = document.getElementById('video-upload-progress');
    const fill = document.getElementById('upload-progress-fill');
    const percent = document.getElementById('upload-progress-percent');
    const label = document.getElementById('upload-progress-label');
    const size = document.getElementById('upload-progress-size');
    const speed = document.getElementById('upload-progress-speed');
    const eta = document.getElementById('upload-progress-eta');
    const status = document.getElementById('upload-progress-status');
    const cancel = document.getElementById('cancel-video-upload');
    const submit = form?.querySelector('button[type="submit"]');
    let activeRequest = null;

    if (!form || !fileInput || !window.XMLHttpRequest) return;

    const formatBytes = (bytes) => {
        if (!Number.isFinite(bytes) || bytes <= 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        const index = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        return `${(bytes / (1024 ** index)).toFixed(index < 2 ? 0 : 2)} ${units[index]}`;
    };
    const formatDuration = (seconds) => {
        if (!Number.isFinite(seconds) || seconds < 1) return 'Less than 1 min remaining';
        const minutes = Math.floor(seconds / 60);
        return minutes >= 60
            ? `${Math.floor(minutes / 60)}h ${minutes % 60}m remaining`
            : `${minutes}m ${Math.max(1, Math.round(seconds % 60))}s remaining`;
    };
    const update = (loaded, total, startedAt) => {
        const ratio = total > 0 ? Math.min(1, loaded / total) : 0;
        const value = Math.round(ratio * 100);
        const elapsed = Math.max(.001, (performance.now() - startedAt) / 1000);
        const bytesPerSecond = loaded / elapsed;
        fill.style.width = `${value}%`;
        percent.textContent = `${value}%`;
        size.textContent = `${formatBytes(loaded)} / ${formatBytes(total)}`;
        speed.textContent = `${formatBytes(bytesPerSecond)}/s`;
        eta.textContent = bytesPerSecond > 0 ? formatDuration((total - loaded) / bytesPerSecond) : 'Calculating ETA…';
    };

    const responseErrorMessage = (request) => {
        const statusCode = Number(request.status || 0);

        if (statusCode === 413) {
            return 'The server rejected this file before PHP received it (HTTP 413). The web-server request-size limit must be increased.';
        }

        if (statusCode === 419) {
            return 'Your admin session expired (HTTP 419). Refresh the page, sign in again if required, then retry.';
        }

        let payload = null;
        try {
            payload = JSON.parse(request.responseText || '');
        } catch (_) {
            // Non-JSON server responses deliberately remain private.
        }

        const firstValidationMessage = payload?.errors
            ? Object.values(payload.errors).flat().find((message) => typeof message === 'string' && message.trim() !== '')
            : null;

        if (firstValidationMessage) {
            return firstValidationMessage;
        }

        if (typeof payload?.message === 'string' && payload.message.trim() !== '') {
            return payload.message.trim();
        }

        if (statusCode > 0) {
            return `The server rejected the upload (HTTP ${statusCode}). No video record was created.`;
        }

        return 'Upload could not be completed. No video record was created.';
    };

    form.addEventListener('submit', (event) => {
        if (activeRequest || !fileInput.files?.[0]) return;
        event.preventDefault();
        const file = fileInput.files[0];
        const startedAt = performance.now();
        const request = new XMLHttpRequest();
        const formData = new FormData(form);
        activeRequest = request;
        progress.classList.add('is-visible');
        cancel.classList.add('is-visible');
        label.textContent = 'Uploading protected video';
        status.textContent = 'Keep this page open. Your upload can be cancelled safely before it completes.';
        submit.disabled = true;
        submit.textContent = 'Uploading…';
        fileInput.disabled = true;
        update(0, file.size, startedAt);

        request.open('POST', form.action, true);
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        request.upload.addEventListener('progress', (uploadEvent) => {
            if (uploadEvent.lengthComputable) update(uploadEvent.loaded, uploadEvent.total, startedAt);
        });
        request.upload.addEventListener('load', () => {
            fill.style.width = '100%';
            percent.textContent = '100%';
            label.textContent = 'Upload transfer complete';
            status.textContent = 'Securely saving the source file and placing HLS processing in the background queue…';
            cancel.classList.remove('is-visible');
        });
        request.addEventListener('load', () => {
            if (request.status >= 200 && request.status < 400) {
                window.location.assign(request.responseURL || form.action);
                return;
            }
            status.textContent = responseErrorMessage(request);
            label.textContent = 'Upload failed';
            cancel.classList.remove('is-visible');
            submit.disabled = false;
            submit.textContent = 'Upload & Queue HLS';
            fileInput.disabled = false;
            activeRequest = null;
        });
        request.addEventListener('error', () => {
            status.textContent = 'Connection interrupted. No video record was created; select the file and retry.';
            label.textContent = 'Upload interrupted';
            cancel.classList.remove('is-visible');
            submit.disabled = false;
            submit.textContent = 'Upload & Queue HLS';
            fileInput.disabled = false;
            activeRequest = null;
        });
        request.addEventListener('abort', () => {
            status.textContent = 'Upload cancelled. No video record was created.';
            label.textContent = 'Upload cancelled';
            cancel.classList.remove('is-visible');
            submit.disabled = false;
            submit.textContent = 'Upload & Queue HLS';
            fileInput.disabled = false;
            activeRequest = null;
        });
        request.send(formData);
    });
    cancel.addEventListener('click', () => activeRequest?.abort());
})();
</script>
@endsection
