# TradeX Dedicated HLS Video Worker

TradeX lesson video encoding must run in a worker dedicated to the
`video-processing` queue so FFmpeg CPU usage cannot delay normal customer jobs.

## Queue

The video job already targets:

```env
TRADEX_VIDEO_QUEUE=video-processing
```

Do not include `video-processing` in the normal/default worker queue list.

## Recommended worker command

For database queue:

```bash
php artisan queue:work database \
  --queue=video-processing \
  --sleep=1 \
  --tries=2 \
  --timeout=7200 \
  --max-jobs=20 \
  --max-time=21600
```

After Redis is verified and enabled:

```bash
php artisan queue:work redis \
  --queue=video-processing \
  --sleep=1 \
  --tries=2 \
  --timeout=7200 \
  --max-jobs=20 \
  --max-time=21600
```

Run this process under Supervisor, systemd, or the hosting provider's supported
persistent process manager.

## Normal worker separation

Normal customer jobs should use a separate worker, for example:

```bash
php artisan queue:work redis \
  --queue=default,notifications,channel-publishing \
  --sleep=1 \
  --tries=3 \
  --timeout=300
```

The normal worker must not consume `video-processing`.

## HLS performance defaults

TradeX now supports these environment values:

```env
TRADEX_VIDEO_FFMPEG_PRESET=ultrafast
TRADEX_VIDEO_FFMPEG_THREADS=1
TRADEX_VIDEO_PROGRESS_UPDATE_SECONDS=2
TRADEX_VIDEO_HLS_VARIANTS=360p,720p,1080p
```

The default rendition set intentionally omits 480p to remove one complete
sequential transcode while retaining low, medium/high and Full HD options.

Increase `TRADEX_VIDEO_FFMPEG_THREADS` only after observing server CPU load.
On shared/cPanel hosting, excessive FFmpeg threads can make customer web requests
slower even when the video job itself finishes faster.

## Live progress

During FFmpeg encoding, the worker writes a heartbeat and rendition telemetry
approximately every two seconds. The admin UI displays:

- source file
- current rendition and rendition number
- rendition percentage
- encoded video position
- FFmpeg speed
- elapsed time
- estimated remaining time
- worker heartbeat

If no heartbeat is received for 30 seconds while the record remains PROCESSING,
the admin UI marks the worker as potentially stalled.

## Deployment order

1. Deploy code.
2. Run migrations.
3. Clear and rebuild Laravel caches.
4. Stop old queue workers.
5. Start the dedicated video worker.
6. Start normal workers without `video-processing`.
7. Upload a short test video first.
8. Confirm heartbeat/progress updates and protected HLS playback.
9. Benchmark CPU before increasing FFmpeg threads.

## Rollback

If server CPU becomes saturated:

1. set `TRADEX_VIDEO_FFMPEG_THREADS=1`
2. set `TRADEX_VIDEO_FFMPEG_PRESET=veryfast` if preferred
3. restart the video worker
4. keep the dedicated queue separation in place
