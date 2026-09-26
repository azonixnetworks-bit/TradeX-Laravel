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
    <p data-processing-stage>{{ $stage }}</p>
    <p class="video-processing-error" data-processing-error @if(!$video->processing_error) hidden @endif>{{ $video->processing_error }}</p>
</div>
