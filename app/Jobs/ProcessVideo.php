<?php

namespace App\Jobs;

use App\Models\LessonVideo;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\MaxAttemptsExceededException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessVideo implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 7200;

    public bool $failOnTimeout = true;

    public int $uniqueFor = 7500;

    public function __construct(
        public int $videoId,
        public ?string $rendition = null,
        public bool $finalize = false
    ) {
        $this->timeout = max(
            300,
            (int) config(
                'tradex.media.processing_timeout_seconds',
                7200
            )
        );

        $this->uniqueFor = $this->timeout + 300;

        $this->onConnection(
            (string) config(
                'tradex.media.queue_connection',
                'video-processing'
            )
        );

        $this->onQueue(
            (string) config(
                'tradex.media.queue',
                'video-processing'
            )
        );
    }

    public function uniqueId(): string
    {
        return 'lesson-video:'.$this->videoId.':'.($this->rendition ?? 'bootstrap');
    }

    public function backoff(): array
    {
        return [60, 300];
    }

    public function handle(): void
    {
        $video = LessonVideo::query()
            ->find($this->videoId);

        if (! $video instanceof LessonVideo) {
            return;
        }

        if (
            $video->processing_status === LessonVideo::PROCESSING_READY
            && $video->hasHlsStream()
        ) {
            return;
        }

        $diskName = trim(
            (string) $video->storage_disk
        );

        if (! in_array($diskName, ['private', 's3-private'], true)) {
            throw new RuntimeException(
                'Video processing refused an unapproved storage disk.'
            );
        }

        $sourcePath = $this->validatedLogicalPath(
            (string) $video->source_path,
            'source path'
        );

        $disk = Storage::disk(
            $diskName
        );

        if (! $disk->exists($sourcePath)) {
            throw new RuntimeException(
                'Video source object does not exist on the configured protected disk.'
            );
        }

        $video->forceFill([
            'processing_status' => LessonVideo::PROCESSING_PROCESSING,
            'processing_progress' => 8,
            'processing_stage' => 'Preparing protected source video.',
            'processing_detail_json' => [
                'current_step' => 'STAGING_SOURCE',
                'source_file' => basename($sourcePath),
                'last_update_at' => now()->toIso8601String(),
            ],
            'processing_started_at' => now(),
            'processing_heartbeat_at' => now(),
            'processing_error' => null,
            'processed_at' => null,
        ])->save();

        $workingDirectory = $this->workingDirectory(
            $video
        );

        try {
            if ($this->rendition === null) {
                File::deleteDirectory(
                    $workingDirectory
                );
            }

            File::ensureDirectoryExists(
                $workingDirectory,
                0700,
                true
            );

            $existingSource = collect(File::files($workingDirectory))->first(fn ($file): bool => str_starts_with($file->getFilename(), 'source.'));
            $sourceFile = $existingSource !== null
                ? $existingSource->getPathname()
                : $this->stageSourceFile($diskName, $sourcePath, $workingDirectory);

            $this->updateProgress($video, 18, 'Protected source staged. Inspecting video metadata.');

            $probe = $this->probeVideo(
                $sourceFile
            );

            $variants = $this->selectVariants(
                $probe['width'],
                $probe['height']
            );

            if ($this->rendition === null) {
                $video->forceFill([
                    'processing_detail_json' => [
                        'current_step' => 'QUEUED_RENDITIONS',
                        'source_file' => basename($sourcePath),
                        'duration_seconds' => $probe['duration_seconds'],
                        'rendition_total' => count($variants),
                        'last_update_at' => now()->toIso8601String(),
                    ],
                ])->save();

                $first = $variants[0];
                self::dispatch($video->id, $first['name'], count($variants) === 1);
                return;
            }

            $variantIndex = array_search($this->rendition, array_column($variants, 'name'), true);
            if ($variantIndex === false) {
                throw new RuntimeException('The requested HLS rendition is not enabled for this source video.');
            }

            $this->updateProgress($video, 25, 'Video inspected. Preparing HLS renditions.');

            $hlsDirectory = $workingDirectory
                .DIRECTORY_SEPARATOR
                .'hls';

            File::ensureDirectoryExists(
                $hlsDirectory,
                0700,
                true
            );

            foreach ([$variantIndex => $variants[$variantIndex]] as $index => $variant) {
                $this->updateProgress(
                    $video,
                    30 + (int) floor(($index / max(1, count($variants))) * 45),
                    sprintf(
                        'Encoding %s HLS rendition (%d/%d).',
                        $variant['name'],
                        $index + 1,
                        count($variants)
                    ),
                    [
                        'current_step' => 'ENCODING_HLS',
                        'source_file' => basename($sourcePath),
                        'rendition' => $variant['name'],
                        'rendition_index' => $index + 1,
                        'rendition_total' => count($variants),
                        'rendition_percent' => 0,
                        'video_position_seconds' => 0,
                        'duration_seconds' => $probe['duration_seconds'],
                    ]
                );

                $this->transcodeVariant(
                    $video,
                    $sourceFile,
                    $hlsDirectory,
                    $variant,
                    $index,
                    count($variants),
                    $probe['duration_seconds'],
                    basename($sourcePath)
                );
            }

            if (! $this->finalize) {
                $nextVariant = $variants[$variantIndex + 1] ?? null;
                if (! is_array($nextVariant)) {
                    $this->finalize = true;
                } else {
                    $this->updateProgress($video, min(75, 30 + (int) floor((($variantIndex + 1) / max(1, count($variants))) * 45)), 'HLS rendition '.$this->rendition.' completed. Queueing '.$nextVariant['name'].'.');
                self::dispatch($video->id, $nextVariant['name'], $variantIndex + 1 === array_key_last($variants));
                return;
                }
            }

            $this->updateProgress($video, 76, 'HLS renditions encoded. Building master playlist.');

            File::put(
                $hlsDirectory
                    .DIRECTORY_SEPARATOR
                    .'master.m3u8',
                $this->buildMasterPlaylist(
                    $variants,
                    $probe['width'],
                    $probe['height']
                )
            );

            $generatedThumbnail = $this->generateThumbnail(
                $sourceFile,
                $workingDirectory,
                $probe['duration_seconds']
            );

            $this->updateProgress($video, 84, 'Thumbnail generated. Publishing protected HLS files.');

            $assetRoot = 'education/videos/'.$video->uuid;
            $hlsRoot = $assetRoot.'/hls';
            $masterPath = $hlsRoot.'/master.m3u8';

            $disk->deleteDirectory(
                $hlsRoot
            );

            $this->uploadDirectory(
                $diskName,
                $hlsDirectory,
                $hlsRoot
            );

            if (! $disk->exists($masterPath)) {
                throw new RuntimeException(
                    'Protected HLS master manifest was not persisted to the configured storage disk.'
                );
            }

            $this->updateProgress($video, 94, 'Protected HLS files uploaded. Finalizing video metadata.');

            $thumbnailPath = null;

            if (
                $generatedThumbnail !== null
                && File::exists($generatedThumbnail)
            ) {
                $thumbnailPath = $assetRoot.'/thumbnail.jpg';

                $this->uploadFile(
                    $diskName,
                    $generatedThumbnail,
                    $thumbnailPath
                );
            }

            $video->forceFill([
                'hls_master_path' => $masterPath,
                'thumbnail_path' => $thumbnailPath,
                'duration_seconds' => $probe['duration_seconds'],
                'file_size_bytes' => $this->safeSourceSize(
                    $diskName,
                    $sourcePath
                ),
                'mime_type' => $this->safeMimeType(
                    $diskName,
                    $sourcePath
                ),
                'processing_status' => LessonVideo::PROCESSING_READY,
                'processing_progress' => 100,
                'processing_stage' => 'HLS processing completed successfully.',
                'processing_detail_json' => [
                    'current_step' => 'COMPLETE',
                    'rendition_total' => count($variants),
                    'last_update_at' => now()->toIso8601String(),
                ],
                'processing_heartbeat_at' => now(),
                'processing_error' => null,
                'processed_at' => now(),
            ])->save();

            Log::info(
                'TradeX lesson video HLS processing completed.',
                [
                    'video_id' => $video->id,
                    'video_uuid' => $video->uuid,
                    'storage_disk' => $diskName,
                    'variant_count' => count($variants),
                ]
            );
        } catch (Throwable $exception) {
            $this->markFailed(
                $video,
                $exception
            );

            throw $exception;
        } finally {
            if ($this->finalize && $video->fresh()?->processing_status === LessonVideo::PROCESSING_READY) {
                File::deleteDirectory(
                    $workingDirectory
                );
            }
        }
    }

    public function failed(
        ?Throwable $exception
    ): void {
        $video = LessonVideo::query()
            ->find($this->videoId);

        if (! $video instanceof LessonVideo) {
            return;
        }

        if ($video->processing_status === LessonVideo::PROCESSING_READY) {
            return;
        }

        $this->markFailed(
            $video,
            $exception
                ?? new RuntimeException(
                    'Video processing job failed without an exception payload.'
                )
        );
    }

    private function workingDirectory(
        LessonVideo $video
    ): string {
        $configuredRoot = trim(
            (string) config(
                'tradex.media.working_directory',
                ''
            )
        );

        $root = $configuredRoot !== ''
            ? $configuredRoot
            : storage_path(
                'app/private/.processing/videos'
            );

        return rtrim(
            $root,
            '\\/'
        )
            .DIRECTORY_SEPARATOR
            .$video->uuid;
    }

    private function stageSourceFile(
        string $diskName,
        string $sourcePath,
        string $workingDirectory
    ): string {
        $extension = strtolower(
            (string) pathinfo(
                $sourcePath,
                PATHINFO_EXTENSION
            )
        );

        if (
            $extension === ''
            || preg_match(
                '/^[a-z0-9]{1,10}$/',
                $extension
            ) !== 1
        ) {
            $extension = 'video';
        }

        $localPath = $workingDirectory
            .DIRECTORY_SEPARATOR
            .'source.'
            .$extension;

        $readStream = Storage::disk(
            $diskName
        )->readStream(
            $sourcePath
        );

        if (! is_resource($readStream)) {
            throw new RuntimeException(
                'Unable to open the protected source video for processing.'
            );
        }

        $writeStream = fopen(
            $localPath,
            'wb'
        );

        if (! is_resource($writeStream)) {
            fclose($readStream);

            throw new RuntimeException(
                'Unable to create the private video-processing staging file.'
            );
        }

        try {
            $bytesCopied = stream_copy_to_stream(
                $readStream,
                $writeStream
            );

            if ($bytesCopied === false) {
                throw new RuntimeException(
                    'Unable to stage the protected source video for processing.'
                );
            }
        } finally {
            fclose($readStream);
            fclose($writeStream);
        }

        if (
            ! File::exists($localPath)
            || File::size($localPath) <= 0
        ) {
            throw new RuntimeException(
                'The staged source video is empty or unreadable.'
            );
        }

        return $localPath;
    }

    private function probeVideo(
        string $sourceFile
    ): array {
        $process = $this->runProcess(
            [
                $this->ffprobeBinary(),
                '-v',
                'error',
                '-select_streams',
                'v:0',
                '-show_entries',
                'stream=width,height',
                '-show_entries',
                'format=duration',
                '-of',
                'json',
                $sourceFile,
            ],
            120,
            'FFprobe could not inspect the uploaded video.'
        );

        $payload = json_decode(
            $process->getOutput(),
            true
        );

        if (! is_array($payload)) {
            throw new RuntimeException(
                'FFprobe returned invalid video metadata.'
            );
        }

        $stream = $payload['streams'][0] ?? null;

        if (! is_array($stream)) {
            throw new RuntimeException(
                'The uploaded file does not contain a readable video stream.'
            );
        }

        $width = (int) ($stream['width'] ?? 0);
        $height = (int) ($stream['height'] ?? 0);
        $duration = (float) (
            $payload['format']['duration']
            ?? 0
        );

        if ($width <= 0 || $height <= 0) {
            throw new RuntimeException(
                'Unable to determine the uploaded video dimensions.'
            );
        }

        return [
            'width' => $width,
            'height' => $height,
            'duration_seconds' => $duration > 0
                ? (int) ceil($duration)
                : null,
        ];
    }

    private function selectVariants(
        int $sourceWidth,
        int $sourceHeight
    ): array {
        $configured = config(
            'tradex.media.hls_variants'
        );

        $profiles = is_array($configured)
            ? $configured
            : [];

        $variants = [];

        $enabledNames = array_values(
            array_filter(
                array_map(
                    static fn (mixed $name): string =>
                        trim((string) $name),
                    (array) config(
                        'tradex.media.enabled_hls_variants',
                        []
                    )
                ),
                static fn (string $name): bool =>
                    $name !== ''
            )
        );

        foreach ($profiles as $profile) {
            if (! is_array($profile)) {
                continue;
            }

            $height = (int) (
                $profile['height']
                ?? 0
            );

            $profileName = trim(
                (string) (
                    $profile['name']
                    ?? $height.'p'
                )
            );

            if (
                $enabledNames !== []
                && ! in_array(
                    $profileName,
                    $enabledNames,
                    true
                )
            ) {
                continue;
            }

            if (
                $height <= 0
                || $height > $sourceHeight
            ) {
                continue;
            }

            $variants[] = $this->normalizeVariant(
                $profile,
                $sourceWidth,
                $sourceHeight,
                $height
            );
        }

        if ($variants === []) {
            $nativeHeight = max(
                2,
                $sourceHeight - ($sourceHeight % 2)
            );

            $variants[] = $this->normalizeVariant(
                [
                    'name' => $nativeHeight.'p',
                    'height' => $nativeHeight,
                    'video_bitrate' => '650k',
                    'audio_bitrate' => '96k',
                    'bandwidth' => 850000,
                    'average_bandwidth' => 746000,
                ],
                $sourceWidth,
                $sourceHeight,
                $nativeHeight
            );
        }

        return $variants;
    }

    private function normalizeVariant(
        array $profile,
        int $sourceWidth,
        int $sourceHeight,
        int $height
    ): array {
        $calculatedWidth = (int) floor(
            (
                ($sourceWidth / $sourceHeight)
                * $height
            ) / 2
        ) * 2;

        $width = max(
            2,
            $calculatedWidth
        );

        $safeName = preg_replace(
            '/[^A-Za-z0-9_-]/',
            '',
            (string) (
                $profile['name']
                ?? $height.'p'
            )
        );

        if (
            ! is_string($safeName)
            || $safeName === ''
        ) {
            $safeName = $height.'p';
        }

        return [
            'name' => $safeName,
            'height' => $height,
            'width' => $width,
            'video_bitrate' => (string) (
                $profile['video_bitrate']
                ?? '1000k'
            ),
            'audio_bitrate' => (string) (
                $profile['audio_bitrate']
                ?? '96k'
            ),
            'bandwidth' => max(
                1,
                (int) (
                    $profile['bandwidth']
                    ?? 1200000
                )
            ),
            'average_bandwidth' => max(
                1,
                (int) (
                    $profile['average_bandwidth']
                    ?? 1000000
                )
            ),
        ];
    }

    private function transcodeVariant(
        LessonVideo $video,
        string $sourceFile,
        string $hlsDirectory,
        array $variant,
        int $variantIndex,
        int $variantTotal,
        ?int $durationSeconds,
        string $sourceName
    ): void {
        $variantDirectory = $hlsDirectory
            .DIRECTORY_SEPARATOR
            .$variant['name'];

        File::ensureDirectoryExists(
            $variantDirectory,
            0700,
            true
        );

        $segmentSeconds = max(
            2,
            min(
                10,
                (int) config(
                    'tradex.media.hls_segment_seconds',
                    6
                )
            )
        );

        $playlistPath = $variantDirectory
            .DIRECTORY_SEPARATOR
            .'index.m3u8';

        $segmentPattern = $variantDirectory
            .DIRECTORY_SEPARATOR
            .'segment_%05d.ts';

        $this->runFfmpegTranscode(
            $video,
            [
                $this->ffmpegBinary(),
                '-hide_banner',
                '-loglevel',
                'error',
                '-y',
                '-i',
                $sourceFile,
                '-map',
                '0:v:0',
                '-map',
                '0:a:0?',
                '-vf',
                'scale=-2:'.$variant['height'].':flags='.
                    $this->scalingFilter(),
                '-c:v',
                'libx264',
                '-threads',
                (string) max(
                    1,
                    (int) config(
                        'tradex.media.ffmpeg_threads',
                        2
                    )
                ),
                '-preset',
                (string) config(
                    'tradex.media.ffmpeg_preset',
                    'superfast'
                ),
                '-profile:v',
                'main',
                '-pix_fmt',
                'yuv420p',
                '-crf',
                (string) config(
                    'tradex.media.ffmpeg_crf',
                    21
                ),
                '-maxrate',
                $variant['video_bitrate'],
                '-bufsize',
                $this->doubleBitrate(
                    $variant['video_bitrate']
                ),
                '-c:a',
                'aac',
                '-b:a',
                $variant['audio_bitrate'],
                '-ar',
                '48000',
                '-ac',
                '2',
                '-force_key_frames',
                'expr:gte(t,n_forced*'.$segmentSeconds.')',
                '-sc_threshold',
                '0',
                '-progress',
                'pipe:1',
                '-nostats',
                '-f',
                'hls',
                '-hls_time',
                (string) $segmentSeconds,
                '-hls_playlist_type',
                'vod',
                '-hls_flags',
                'independent_segments',
                '-hls_segment_filename',
                $segmentPattern,
                $playlistPath,
            ],
            max(
                300,
                $this->timeout - 30
            ),
            'FFmpeg failed while generating an HLS rendition.',
            $variant,
            $variantIndex,
            $variantTotal,
            $durationSeconds,
            $sourceName
        );

        if (! File::exists($playlistPath)) {
            throw new RuntimeException(
                'FFmpeg completed without creating the expected HLS playlist.'
            );
        }
    }

    private function runFfmpegTranscode(
        LessonVideo $video,
        array $command,
        int $timeoutSeconds,
        string $failureMessage,
        array $variant,
        int $variantIndex,
        int $variantTotal,
        ?int $durationSeconds,
        string $sourceName
    ): void {
        $process = new Process(
            $command
        );

        $process->setTimeout(
            max(
                30,
                $timeoutSeconds
            )
        );

        $startedAt = microtime(true);
        $lastPersistedAt = 0.0;
        $stdoutBuffer = '';
        $progressState = [];

        $persist = function () use (
            $video,
            $variant,
            $variantIndex,
            $variantTotal,
            $durationSeconds,
            $sourceName,
            $startedAt,
            &$lastPersistedAt,
            &$progressState
        ): void {
            $now = microtime(true);
            $updateEvery = max(
                1,
                (int) config(
                    'tradex.media.progress_update_seconds',
                    2
                )
            );

            if (
                $lastPersistedAt > 0
                && ($now - $lastPersistedAt) < $updateEvery
                && ($progressState['progress'] ?? null) !== 'end'
            ) {
                return;
            }

            $positionSeconds = $this->ffmpegPositionSeconds(
                $progressState
            );

            $renditionPercent =
                $durationSeconds !== null
                && $durationSeconds > 0
                    ? min(
                        100,
                        max(
                            0,
                            (int) floor(
                                ($positionSeconds / $durationSeconds)
                                * 100
                            )
                        )
                    )
                    : null;

            $variantSpan =
                45 / max(1, $variantTotal);

            $overallProgress =
                30
                + (int) floor(
                    ($variantIndex * $variantSpan)
                    + (
                        (($renditionPercent ?? 0) / 100)
                        * $variantSpan
                    )
                );

            $elapsedSeconds = max(
                0,
                (int) floor(
                    $now - $startedAt
                )
            );

            $speed = trim(
                (string) (
                    $progressState['speed']
                    ?? ''
                )
            );

            $estimatedRemaining = null;

            if (
                $renditionPercent !== null
                && $renditionPercent > 0
                && $renditionPercent < 100
            ) {
                $estimatedRemaining = max(
                    0,
                    (int) round(
                        $elapsedSeconds
                        * (100 - $renditionPercent)
                        / $renditionPercent
                    )
                );
            }

            $this->updateProgress(
                $video,
                min(75, $overallProgress),
                sprintf(
                    'Encoding %s HLS rendition (%d/%d) — %s.',
                    $variant['name'],
                    $variantIndex + 1,
                    $variantTotal,
                    $renditionPercent !== null
                        ? $renditionPercent.'%'
                        : 'running'
                ),
                [
                    'current_step' => 'ENCODING_HLS',
                    'source_file' => $sourceName,
                    'rendition' => $variant['name'],
                    'rendition_index' => $variantIndex + 1,
                    'rendition_total' => $variantTotal,
                    'rendition_percent' => $renditionPercent,
                    'video_position_seconds' =>
                        (int) floor($positionSeconds),
                    'duration_seconds' => $durationSeconds,
                    'speed' => $speed !== ''
                        ? $speed
                        : null,
                    'elapsed_seconds' => $elapsedSeconds,
                    'estimated_remaining_seconds' =>
                        $estimatedRemaining,
                ]
            );

            $lastPersistedAt = $now;
        };

        $process->run(
            function (
                string $type,
                string $buffer
            ) use (
                &$stdoutBuffer,
                &$progressState,
                $persist
            ): void {
                if ($type !== Process::OUT) {
                    return;
                }

                $stdoutBuffer .= $buffer;

                while (
                    ($lineEnd = strpos(
                        $stdoutBuffer,
                        "\n"
                    )) !== false
                ) {
                    $line = trim(
                        substr(
                            $stdoutBuffer,
                            0,
                            $lineEnd
                        )
                    );

                    $stdoutBuffer = substr(
                        $stdoutBuffer,
                        $lineEnd + 1
                    );

                    if (
                        $line === ''
                        || ! str_contains(
                            $line,
                            '='
                        )
                    ) {
                        continue;
                    }

                    [$key, $value] = explode(
                        '=',
                        $line,
                        2
                    );

                    $progressState[
                        trim($key)
                    ] = trim($value);

                    if (
                        in_array(
                            trim($key),
                            [
                                'out_time_ms',
                                'out_time_us',
                                'speed',
                                'progress',
                            ],
                            true
                        )
                    ) {
                        $persist();
                    }
                }
            }
        );

        if (! $process->isSuccessful()) {
            $error = trim(
                $process->getErrorOutput()
            );

            if ($error === '') {
                $error = trim(
                    $process->getOutput()
                );
            }

            throw new RuntimeException(
                $failureMessage
                .(
                    $error !== ''
                        ? ' '.Str::limit(
                            $error,
                            2000
                        )
                        : ''
                )
            );
        }

        $progressState['progress'] = 'end';
        $persist();
    }

    private function ffmpegPositionSeconds(
        array $state
    ): float {
        foreach (
            [
                'out_time_us',
                'out_time_ms',
            ] as $key
        ) {
            if (
                isset($state[$key])
                && is_numeric($state[$key])
            ) {
                return max(
                    0.0,
                    ((float) $state[$key])
                    / 1000000
                );
            }
        }

        return 0.0;
    }

    private function buildMasterPlaylist(
        array $variants,
        int $sourceWidth,
        int $sourceHeight
    ): string {
        $lines = [
            '#EXTM3U',
            '#EXT-X-VERSION:3',
            '#EXT-X-INDEPENDENT-SEGMENTS',
        ];

        foreach ($variants as $variant) {
            $width = (int) $variant['width'];
            $height = (int) $variant['height'];

            if ($width <= 0 || $height <= 0) {
                $width = $sourceWidth;
                $height = $sourceHeight;
            }

            $lines[] = '#EXT-X-STREAM-INF:BANDWIDTH='
                .$variant['bandwidth']
                .',AVERAGE-BANDWIDTH='
                .$variant['average_bandwidth']
                .',RESOLUTION='
                .$width
                .'x'
                .$height;

            $lines[] = $variant['name']
                .'/index.m3u8';
        }

        return implode(
            PHP_EOL,
            $lines
        ).PHP_EOL;
    }

    private function generateThumbnail(
        string $sourceFile,
        string $workingDirectory,
        ?int $durationSeconds
    ): ?string {
        $thumbnailPath = $workingDirectory
            .DIRECTORY_SEPARATOR
            .'thumbnail.jpg';

        $seekSeconds = 1;

        if (
            $durationSeconds !== null
            && $durationSeconds > 1
        ) {
            $seekSeconds = max(
                1,
                min(
                    10,
                    (int) floor(
                        $durationSeconds * 0.10
                    )
                )
            );
        }

        try {
            $this->runProcess(
                [
                    $this->ffmpegBinary(),
                    '-hide_banner',
                    '-loglevel',
                    'error',
                    '-y',
                    '-ss',
                    (string) $seekSeconds,
                    '-i',
                    $sourceFile,
                    '-frames:v',
                    '1',
                    '-vf',
                    'scale=640:-2:force_original_aspect_ratio=decrease',
                    '-q:v',
                    '3',
                    $thumbnailPath,
                ],
                180,
                'FFmpeg could not generate the video thumbnail.'
            );
        } catch (Throwable $exception) {
            Log::warning(
                'TradeX lesson video thumbnail generation failed.',
                [
                    'video_id' => $this->videoId,
                    'error' => Str::limit(
                        $exception->getMessage(),
                        1000
                    ),
                ]
            );

            return null;
        }

        return File::exists($thumbnailPath)
            ? $thumbnailPath
            : null;
    }

    private function uploadDirectory(
        string $diskName,
        string $localDirectory,
        string $destinationRoot
    ): void {
        foreach (File::allFiles($localDirectory) as $file) {
            $relativePath = str_replace(
                '\\',
                '/',
                $file->getRelativePathname()
            );

            $destination = $destinationRoot
                .'/'
                .$relativePath;

            $this->uploadFile(
                $diskName,
                $file->getPathname(),
                $destination
            );
        }
    }

    private function uploadFile(
        string $diskName,
        string $localFile,
        string $destination
    ): void {
        $stream = fopen(
            $localFile,
            'rb'
        );

        if (! is_resource($stream)) {
            throw new RuntimeException(
                'Unable to open a generated media file for protected storage.'
            );
        }

        try {
            $stored = Storage::disk(
                $diskName
            )->put(
                $destination,
                $stream,
                [
                    'visibility' => 'private',
                ]
            );
        } finally {
            fclose($stream);
        }

        if (! $stored) {
            throw new RuntimeException(
                'Unable to save generated media to protected storage.'
            );
        }
    }

    private function safeSourceSize(
        string $diskName,
        string $sourcePath
    ): ?int {
        try {
            $size = Storage::disk(
                $diskName
            )->size(
                $sourcePath
            );

            return $size >= 0
                ? (int) $size
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function safeMimeType(
        string $diskName,
        string $sourcePath
    ): ?string {
        try {
            $mimeType = Storage::disk(
                $diskName
            )->mimeType(
                $sourcePath
            );

            if (
                ! is_string($mimeType)
                || trim($mimeType) === ''
            ) {
                return null;
            }

            return Str::limit(
                trim($mimeType),
                120,
                ''
            );
        } catch (Throwable) {
            return null;
        }
    }

    private function runProcess(
        array $command,
        int $timeoutSeconds,
        string $failureMessage
    ): Process {
        $process = new Process(
            $command
        );

        $process->setTimeout(
            max(
                30,
                $timeoutSeconds
            )
        );

        $process->run();

        if (! $process->isSuccessful()) {
            $error = trim(
                $process->getErrorOutput()
            );

            if ($error === '') {
                $error = trim(
                    $process->getOutput()
                );
            }

            throw new RuntimeException(
                $failureMessage
                .(
                    $error !== ''
                        ? ' '.Str::limit(
                            $error,
                            2000
                        )
                        : ''
                )
            );
        }

        return $process;
    }

    private function scalingFilter(): string
    {
        $filter = strtolower(
            trim(
                (string) config(
                    'tradex.media.scaling_filter',
                    'bicubic'
                )
            )
        );

        return in_array(
            $filter,
            [
                'fast_bilinear',
                'bilinear',
                'bicubic',
                'lanczos',
            ],
            true
        )
            ? $filter
            : 'bicubic';
    }

    private function ffmpegBinary(): string
    {
        $binary = trim(
            (string) config(
                'tradex.media.ffmpeg_binary',
                'ffmpeg'
            )
        );

        return $binary !== ''
            ? $binary
            : 'ffmpeg';
    }

    private function ffprobeBinary(): string
    {
        $binary = trim(
            (string) config(
                'tradex.media.ffprobe_binary',
                'ffprobe'
            )
        );

        return $binary !== ''
            ? $binary
            : 'ffprobe';
    }

    private function doubleBitrate(
        string $bitrate
    ): string {
        if (
            preg_match(
                '/^(\d+)([kKmM])$/',
                trim($bitrate),
                $matches
            ) !== 1
        ) {
            return $bitrate;
        }

        return ((int) $matches[1] * 2)
            .strtolower($matches[2]);
    }

    private function validatedLogicalPath(
        string $path,
        string $label
    ): string {
        $normalized = str_replace(
            '\\',
            '/',
            trim($path)
        );

        $hasScheme = preg_match(
            '/^[A-Za-z][A-Za-z0-9+.-]*:/',
            $normalized
        ) === 1;

        $hasTraversal = preg_match(
            '~(?:^|/)\.\.(?:/|$)~',
            $normalized
        ) === 1;

        $hasControlCharacters = preg_match(
            '/[\x00-\x1F\x7F]/',
            $normalized
        ) === 1;

        if (
            $normalized === ''
            || str_starts_with($normalized, '/')
            || $hasScheme
            || $hasTraversal
            || str_contains($normalized, '?')
            || str_contains($normalized, '#')
            || $hasControlCharacters
        ) {
            throw new RuntimeException(
                'Video processing refused an unsafe '.$label.'.'
            );
        }

        return $normalized;
    }

    private function markFailed(
        LessonVideo $video,
        Throwable $exception
    ): void {
        $freshVideo = LessonVideo::query()
            ->find($video->id);

        if (! $freshVideo instanceof LessonVideo) {
            return;
        }

        if (
            $freshVideo->processing_status
            === LessonVideo::PROCESSING_READY
        ) {
            return;
        }

        $previousError = trim(
            (string) $freshVideo->processing_error
        );
        $retryLimitReached = $exception instanceof MaxAttemptsExceededException;
        $displayError = $retryLimitReached && $previousError !== ''
            ? $previousError
            : Str::limit(
                $exception->getMessage(),
                8000,
                ''
            );

        $freshVideo->forceFill([
            'processing_status' => LessonVideo::PROCESSING_FAILED,
            'processing_stage' => $retryLimitReached && $previousError !== ''
                ? 'Processing stopped after automatic retries. The original FFmpeg error is shown below.'
                : 'Processing failed. Review the error and retry.',
            'processing_detail_json' => [
                'current_step' => 'FAILED',
                'last_update_at' => now()->toIso8601String(),
                'retry_limit_reached' => $retryLimitReached,
                'failed_rendition' => $this->rendition,
            ],
            'processing_heartbeat_at' => now(),
            'processing_error' => $displayError,
            'processed_at' => now(),
        ])->save();

        Log::error(
            'TradeX lesson video HLS processing failed.',
            [
                'video_id' => $freshVideo->id,
                'video_uuid' => $freshVideo->uuid,
                'error' => Str::limit(
                    $exception->getMessage(),
                    2000
                ),
            ]
        );
    }

    private function updateProgress(
        LessonVideo $video,
        int $progress,
        string $stage,
        array $detail = []
    ): void {
        $detail['last_update_at'] =
            now()->toIso8601String();

        $video->forceFill([
            'processing_progress' =>
                min(99, max(0, $progress)),
            'processing_stage' =>
                Str::limit(trim($stage), 160, ''),
            'processing_detail_json' =>
                $detail !== []
                    ? $detail
                    : $video->processing_detail_json,
            'processing_heartbeat_at' =>
                now(),
        ])->save();
    }
}
