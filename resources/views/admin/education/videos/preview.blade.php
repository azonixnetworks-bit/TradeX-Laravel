{{-- TradeX Admin — ADMIN-03B Protected HLS Preview --}}

@extends('admin.layouts.app')

@section('title', $video->title . ' Preview | TradeX Education')
@section('page-title', 'Education')

@push('styles')
<style>
    .preview-shell{max-width:1220px;margin:0 auto}
    .preview-context{display:flex;justify-content:space-between;gap:18px;align-items:flex-start;padding:19px 21px;margin-bottom:20px}
    .preview-context h2{margin:6px 0;font-size:19px}.preview-context p{margin:0;font-size:11px;opacity:.58;line-height:1.6}
    .preview-tags{display:flex;gap:7px;flex-wrap:wrap;justify-content:flex-end}
    .preview-tag{padding:6px 9px;border:1px solid rgba(255,255,255,.08);border-radius:999px;font-size:9px;font-weight:800;letter-spacing:.04em}
    .preview-tag.ready{color:#77d9a6;border-color:rgba(119,217,166,.22);background:rgba(119,217,166,.06)}
    .player-panel{overflow:hidden}.player-head{display:flex;justify-content:space-between;gap:15px;align-items:center;padding:18px 21px;border-bottom:1px solid rgba(255,255,255,.06)}
    .player-head h2{margin:4px 0 0;font-size:17px}.player-head p{margin:5px 0 0;font-size:10px;opacity:.52}
    .player-stage{padding:22px}.video-frame{position:relative;overflow:hidden;border:1px solid rgba(255,255,255,.08);border-radius:16px;background:#000;aspect-ratio:16/9;display:grid;place-items:center}
    .video-frame video{width:100%;height:100%;display:block;background:#000;object-fit:contain}
    .watermark{position:absolute;right:16px;bottom:54px;z-index:3;pointer-events:none;padding:7px 10px;border-radius:8px;background:rgba(0,0,0,.48);border:1px solid rgba(255,255,255,.13);font-size:9px;font-weight:800;letter-spacing:.08em;color:rgba(255,255,255,.7)}
    .security-strip{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:9px;margin-top:14px}
    .security-card{padding:12px;border:1px solid rgba(255,255,255,.06);border-radius:11px;background:rgba(255,255,255,.018)}
    .security-card span{display:block;margin-bottom:4px;font-size:8px;text-transform:uppercase;letter-spacing:.07em;opacity:.45}.security-card strong{font-size:10px}
    .player-message{margin-top:12px;padding:11px 12px;border:1px solid rgba(255,193,115,.18);border-radius:10px;background:rgba(255,193,115,.05);font-size:10px;line-height:1.55;display:none}
    .player-message.error{display:block;color:#ff9a9a;border-color:rgba(255,91,91,.22);background:rgba(255,91,91,.05)}
    .player-message.info{display:block;color:#ffc173}
    .notice{margin-top:18px;padding:14px 16px;border:1px solid rgba(218,178,88,.16);border-radius:11px;background:rgba(218,178,88,.04);font-size:10px;line-height:1.65;opacity:.78}
    @media(max-width:800px){.preview-context,.player-head{flex-direction:column}.preview-tags{justify-content:flex-start}.security-strip{grid-template-columns:repeat(2,minmax(0,1fr))}.player-stage{padding:14px}}
    @media(max-width:520px){.security-strip{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
<div class="page-header">
    <div>
        <div class="page-eyebrow">EDUCATION MANAGEMENT · ADMIN-03B</div>
        <h1>Protected <span>HLS Preview</span></h1>
        <p>Admin-only validation of the protected adaptive video stream before learner delivery.</p>
    </div>

    <div>
        <a
            class="secondary-btn"
            href="{{ route('admin.education.courses.modules.lessons.videos.index', ['course'=>$course,'module'=>$module,'lesson'=>$lesson]) }}"
        >
            ← Back to Videos
        </a>
    </div>
</div>

<div class="preview-shell">
    <section class="panel preview-context">
        <div>
            <div class="panel-eyebrow">{{ $course->code }} · {{ $module->code }} · {{ $lesson->code }}</div>
            <h2>{{ $video->title }}</h2>
            <p>{{ $video->short_description ?: 'No video summary has been added.' }}</p>
        </div>

        <div class="preview-tags">
            <span class="preview-tag ready">{{ $video->processing_status }}</span>
            <span class="preview-tag">{{ $video->status }}</span>
            <span class="preview-tag">{{ $video->storage_disk }}</span>
        </div>
    </section>

    <section class="panel player-panel">
        <div class="player-head">
            <div>
                <div class="panel-eyebrow">PROTECTED MEDIA GATEWAY</div>
                <h2>Adaptive Stream Validation</h2>
                <p>HLS manifests and segments are served only through the authenticated TradeX admin route.</p>
            </div>

            <span class="account-badge free">ADMIN PREVIEW</span>
        </div>

        <div class="player-stage">
            <div
                class="video-frame"
                id="tradex-hls-frame"
                oncontextmenu="return false;"
            >
                <video
                    id="tradex-hls-video"
                    controls
                    controlsList="nodownload noremoteplayback"
                    disablePictureInPicture
                    playsinline
                    preload="metadata"
                ></video>

                <div class="watermark">
                    TRADEX · ADMIN PREVIEW
                </div>
            </div>

            <div
                id="tradex-player-message"
                class="player-message"
                role="status"
                aria-live="polite"
            ></div>

            <div class="security-strip">
                <div class="security-card">
                    <span>Source Exposure</span>
                    <strong>BLOCKED</strong>
                </div>

                <div class="security-card">
                    <span>Media Access</span>
                    <strong>ADMIN AUTH</strong>
                </div>

                <div class="security-card">
                    <span>Permission</span>
                    <strong>EDUCATION.VIEW</strong>
                </div>

                <div class="security-card">
                    <span>Browser Cache</span>
                    <strong>NO-STORE</strong>
                </div>
            </div>

            <div class="notice">
                This screen is an administrative QA preview, not the learner playback API.
                Learner entitlement, active-device validation, playback session/token rotation,
                dynamic learner watermarking and progress/resume tracking will be connected
                after the user/mobile API authentication layer is available.
            </div>
        </div>
    </section>
</div>
@endsection

@push('scripts')
<script
    src="https://cdn.jsdelivr.net/npm/hls.js@1/dist/hls.min.js"
    referrerpolicy="no-referrer"
></script>

<script>
(() => {
    'use strict';

    const video = document.getElementById('tradex-hls-video');
    const message = document.getElementById('tradex-player-message');
    const source = @json($manifestUrl);

    if (!video || !message) {
        return;
    }

    const showMessage = (text, type = 'info') => {
        message.textContent = text;
        message.className = 'player-message ' + type;
    };

    video.addEventListener('contextmenu', (event) => {
        event.preventDefault();
    });

    if (
        window.Hls &&
        window.Hls.isSupported()
    ) {
        const hls = new window.Hls({
            enableWorker: true,
            debug: false,
            maxBufferLength: 30,
            maxMaxBufferLength: 60,
        });

        hls.loadSource(source);
        hls.attachMedia(video);

        hls.on(
            window.Hls.Events.MANIFEST_PARSED,
            () => {
                showMessage(
                    'Protected HLS manifest loaded. Press play to validate the stream.',
                    'info'
                );
            }
        );

        hls.on(
            window.Hls.Events.ERROR,
            (_event, data) => {
                if (!data || !data.fatal) {
                    return;
                }

                showMessage(
                    'Protected HLS playback failed. Check the browser Network tab and Laravel log/queue output.',
                    'error'
                );

                if (
                    data.type === window.Hls.ErrorTypes.NETWORK_ERROR
                ) {
                    hls.startLoad();
                    return;
                }

                if (
                    data.type === window.Hls.ErrorTypes.MEDIA_ERROR
                ) {
                    hls.recoverMediaError();
                    return;
                }

                hls.destroy();
            }
        );

        window.addEventListener(
            'beforeunload',
            () => hls.destroy(),
            { once: true }
        );

        return;
    }

    if (
        video.canPlayType(
            'application/vnd.apple.mpegurl'
        )
    ) {
        video.src = source;
        showMessage(
            'Native protected HLS playback is available in this browser.',
            'info'
        );
        return;
    }

    showMessage(
        'This browser cannot initialize HLS playback. The protected media route remains available, but use a browser with HLS/MSE support for preview.',
        'error'
    );
})();
</script>
@endpush
