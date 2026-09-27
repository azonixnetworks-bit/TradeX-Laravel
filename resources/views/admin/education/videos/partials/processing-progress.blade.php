@php
    $repairRequired =
        $video->processing_status === \App\Models\LessonVideo::PROCESSING_READY
        && ! $video->hasPlaybackReference();

    $progress = match(true) {
        $repairRequired => min(99, max(1, (int) $video->processing_progress)),
        $video->processing_status === \App\Models\LessonVideo::PROCESSING_READY => 100,
        $video->processing_status === \App\Models\LessonVideo::PROCESSING_FAILED => max(1, (int) $video->processing_progress),
        $video->processing_status === \App\Models\LessonVideo::PROCESSING_PROCESSING => max(10, (int) $video->processing_progress),
        default => max(3, (int) $video->processing_progress),
    };

    $stage = $repairRequired
        ? 'Protected playback reference is missing. Run Repair Protected HLS before previewing or publishing.'
        : ($video->processing_stage ?: match($video->processing_status) {
            \App\Models\LessonVideo::PROCESSING_READY => 'HLS processing completed successfully.',
            \App\Models\LessonVideo::PROCESSING_FAILED => 'Processing failed. Review the error and retry.',
            \App\Models\LessonVideo::PROCESSING_PROCESSING => 'The background worker is processing this video.',
            default => 'Waiting for the background video worker to start.',
        });

    $stateClass = $repairRequired
        ? 'failed'
        : strtolower($video->processing_status);

    $label = $repairRequired
        ? 'HLS repair required'
        : ($video->processing_status === \App\Models\LessonVideo::PROCESSING_READY
            ? 'Successfully completed'
            : $video->processing_status);

    $detail = is_array($video->processing_detail_json)
        ? $video->processing_detail_json
        : [];
@endphp
<div class="video-processing-progress is-{{ $stateClass }}"
     data-video-processing
     data-status-url="{{ route('admin.education.courses.modules.lessons.videos.processing-status', compact('course', 'module', 'lesson', 'video')) }}"
     data-terminal="{{ in_array($video->processing_status, [\App\Models\LessonVideo::PROCESSING_READY, \App\Models\LessonVideo::PROCESSING_FAILED], true) ? 'true' : 'false' }}">
    <div class="video-processing-heading">
        <strong data-processing-label>{{ $label }}</strong>
        <span data-processing-percent>{{ $progress }}%</span>
    </div>

    <div class="video-processing-track" role="progressbar" aria-label="Video processing progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress }}">
        <span data-processing-fill style="width: {{ $progress }}%"></span>
    </div>

    <p class="video-processing-stage" data-processing-stage>{{ $stage }}</p>

    <div class="video-processing-detail" data-processing-detail>
        <div>
            <span>Current file</span>
            <strong data-processing-file>{{ $detail['source_file'] ?? '—' }}</strong>
        </div>
        <div>
            <span>Rendition</span>
            <strong data-processing-rendition>
                @if(!empty($detail['rendition']))
                    {{ $detail['rendition'] }}
                    @if(!empty($detail['rendition_index']) && !empty($detail['rendition_total']))
                        · {{ $detail['rendition_index'] }}/{{ $detail['rendition_total'] }}
                    @endif
                @else
                    —
                @endif
            </strong>
        </div>
        <div>
            <span>Rendition progress</span>
            <strong data-processing-rendition-percent>
                {{ isset($detail['rendition_percent']) && $detail['rendition_percent'] !== null ? $detail['rendition_percent'].'%' : '—' }}
            </strong>
        </div>
        <div>
            <span>Video position</span>
            <strong data-processing-position>—</strong>
        </div>
        <div>
            <span>Encoding speed</span>
            <strong data-processing-speed>{{ $detail['speed'] ?? '—' }}</strong>
        </div>
        <div>
            <span>Elapsed</span>
            <strong data-processing-elapsed>—</strong>
        </div>
        <div>
            <span>Estimated remaining</span>
            <strong data-processing-remaining>—</strong>
        </div>
        <div>
            <span>Worker heartbeat</span>
            <strong data-processing-heartbeat>{{ $video->processing_heartbeat_at?->diffForHumans() ?? 'Waiting to start' }}</strong>
        </div>
    </div>

    <p class="video-processing-stalled" data-processing-stalled hidden>
        Worker heartbeat has stopped. The video worker may be stalled or unavailable.
    </p>

    <div class="video-processing-error" @if(!$video->processing_error) hidden @endif>
        <strong>Processing error details</strong>
        <code data-processing-error>{{ $video->processing_error }}</code>
    </div>
</div>
