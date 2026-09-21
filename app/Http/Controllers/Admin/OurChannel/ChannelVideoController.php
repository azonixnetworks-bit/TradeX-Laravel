<?php

namespace App\Http\Controllers\Admin\OurChannel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OurChannel\StoreChannelVideoRequest;
use App\Http\Requests\Admin\OurChannel\UpdateChannelVideoPublicationRequest;
use App\Http\Requests\Admin\OurChannel\UpdateChannelVideoRequest;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\ChannelVideo;
use App\Models\ChannelVideoPublication;
use App\Services\OurChannel\ChannelPublicationService;
use App\Support\YouTubeVideo;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class ChannelVideoController extends Controller
{
    public function index(): View
    {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'our_channel.view'
        );

        $videos = ChannelVideo::query()
            ->with('publications')
            ->ordered()
            ->get();

        $publicationRows = $videos
            ->flatMap(
                fn (ChannelVideo $video) =>
                    $video->publications
            );

        $stats = [
            'total' => $videos->count(),
            'published' => $videos
                ->where(
                    'status',
                    ChannelVideo::STATUS_PUBLISHED
                )
                ->count(),
            'external_published' => $publicationRows
                ->where(
                    'status',
                    ChannelVideoPublication::STATUS_PUBLISHED
                )
                ->count(),
            'attention' => $publicationRows
                ->whereIn(
                    'status',
                    [
                        ChannelVideoPublication::STATUS_FAILED,
                        ChannelVideoPublication::STATUS_ACTION_REQUIRED,
                    ]
                )
                ->count(),
        ];

        $permissions = [
            'view' => $admin->hasPermission(
                'our_channel.view'
            ),
            'manage' => $admin->hasPermission(
                'our_channel.manage'
            ),
        ];

        return view(
            'admin.our_channel.index',
            compact(
                'videos',
                'stats',
                'permissions'
            )
        );
    }

    public function create(): View
    {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'our_channel.manage'
        );

        return view(
            'admin.our_channel.create',
            [
                'platforms' =>
                    ChannelVideoPublication::platformLabels(),

                'platformStates' =>
                    $this->platformStates(),
            ]
        );
    }

    public function store(
        StoreChannelVideoRequest $request,
        ChannelPublicationService $publicationService
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'our_channel.manage'
        );

        $validated = $request->validated();

        $youtubeUrl = $this->nullableText(
            $validated['youtube_url']
            ?? null
        );

        $videoId = $this->extractVideoId(
            $youtubeUrl
        );

        if ($videoId !== null) {
            $this->assertVideoIdAvailable(
                $videoId
            );
        }

        $uuid = Str::uuid()->toString();
        $source = $this->storeSourceUpload(
            $request,
            $uuid
        );

        try {
            $video = DB::transaction(
                function () use (
                    $validated,
                    $videoId,
                    $youtubeUrl,
                    $source,
                    $uuid,
                    $request,
                    $admin,
                    $publicationService
                ): ChannelVideo {
                    $publishNow =
                        ($validated['intent'] ?? 'DRAFT')
                        === 'PUBLISH';

                    $video = ChannelVideo::query()->create([
                        'uuid' => $uuid,
                        'title' => $validated['title'],
                        'short_description' =>
                            $validated['short_description']
                            ?? null,
                        'description' =>
                            $validated['description']
                            ?? null,
                        'hashtags' =>
                            $validated['hashtags']
                            ?? null,
                        'youtube_video_id' => $videoId,
                        'youtube_url' => $videoId !== null
                            ? YouTubeVideo::canonicalUrl(
                                $videoId
                            )
                            : $youtubeUrl,
                        'source_disk' =>
                            $source['disk']
                            ?? null,
                        'source_path' =>
                            $source['path']
                            ?? null,
                        'source_original_name' =>
                            $source['original_name']
                            ?? null,
                        'source_mime_type' =>
                            $source['mime_type']
                            ?? null,
                        'source_size_bytes' =>
                            $source['size_bytes']
                            ?? null,
                        'source_sha256' =>
                            $source['sha256']
                            ?? null,
                        'language_code' =>
                            $validated['language_code'],
                        'status' => $publishNow
                            ? ChannelVideo::STATUS_PUBLISHED
                            : ChannelVideo::STATUS_DRAFT,
                        'published_at' => $publishNow
                            ? now()
                            : null,
                        'publish_requested_at' => null,
                        'is_featured' => (bool) (
                            $validated['is_featured']
                            ?? false
                        ),
                        'sort_order' => (int) (
                            $validated['sort_order']
                            ?? 0
                        ),
                        'created_by_admin_id' => $admin->id,
                        'updated_by_admin_id' => $admin->id,
                    ]);

                    $publicationService->createTargets(
                        $video,
                        $validated['platforms']
                        ?? []
                    );

                    AuditLog::record(
                        actorType: 'ADMIN',
                        actorId: $admin->id,
                        action: $publishNow
                            ? 'CHANNEL_VIDEO_CREATED_AND_PUBLISH_REQUESTED'
                            : 'CHANNEL_VIDEO_CREATED',
                        module: 'OUR_CHANNEL',
                        targetType: 'CHANNEL_VIDEO',
                        targetId: $video->id,
                        newValues: $this->auditSnapshot(
                            $video
                        ),
                        ipAddress: $request->ip(),
                        userAgent: $request->userAgent()
                    );

                    return $video;
                }
            );
        } catch (Throwable $exception) {
            $this->cleanupSource(
                $source,
                $uuid
            );

            throw $exception;
        }

        if (
            ($validated['intent'] ?? 'DRAFT')
            === 'PUBLISH'
        ) {
            $publicationService->queueForVideo(
                $video->fresh('publications')
            );
        }

        return redirect()
            ->route('admin.our_channel.index')
            ->with(
                'success',
                ($validated['intent'] ?? 'DRAFT')
                    === 'PUBLISH'
                    ? 'Video saved. Enabled platform publishing jobs were queued.'
                    : 'Our Channel video created successfully as draft.'
            );
    }

    public function edit(
        ChannelVideo $channelVideo
    ): View {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'our_channel.manage'
        );

        return view(
            'admin.our_channel.edit',
            compact('channelVideo')
        );
    }

    public function update(
        UpdateChannelVideoRequest $request,
        ChannelVideo $channelVideo
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'our_channel.manage'
        );

        $validated = $request->validated();

        $youtubeUrl = $this->nullableText(
            $validated['youtube_url']
            ?? null
        );

        $videoId = $this->extractVideoId(
            $youtubeUrl
        );

        if ($videoId !== null) {
            $this->assertVideoIdAvailable(
                $videoId,
                $channelVideo->id
            );
        }

        try {
            DB::transaction(function () use (
                $validated,
                $videoId,
                $youtubeUrl,
                $request,
                $admin,
                $channelVideo
            ): void {
                $locked = ChannelVideo::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $channelVideo->id
                    );

                $before = $this->auditSnapshot(
                    $locked
                );

                $locked->fill([
                    'title' => $validated['title'],
                    'short_description' =>
                        $validated['short_description']
                        ?? null,
                    'description' =>
                        $validated['description']
                        ?? null,
                    'hashtags' =>
                        $validated['hashtags']
                        ?? null,
                    'youtube_video_id' => $videoId,
                    'youtube_url' => $videoId !== null
                        ? YouTubeVideo::canonicalUrl(
                            $videoId
                        )
                        : $youtubeUrl,
                    'language_code' =>
                        $validated['language_code'],
                    'is_featured' => (bool) (
                        $validated['is_featured']
                        ?? false
                    ),
                    'sort_order' => (int) (
                        $validated['sort_order']
                        ?? 0
                    ),
                    'updated_by_admin_id' => $admin->id,
                ]);

                if (! $locked->isDirty()) {
                    return;
                }

                $locked->save();

                [
                    $oldValues,
                    $newValues,
                ] = $this->auditChanges(
                    $before,
                    $this->auditSnapshot(
                        $locked->fresh()
                    )
                );

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->id,
                    action: 'CHANNEL_VIDEO_UPDATED',
                    module: 'OUR_CHANNEL',
                    targetType: 'CHANNEL_VIDEO',
                    targetId: $locked->id,
                    oldValues: $oldValues,
                    newValues: $newValues,
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );
            });
        } catch (QueryException $exception) {
            if (
                str_contains(
                    strtolower(
                        $exception->getMessage()
                    ),
                    'youtube_video_id'
                )
            ) {
                throw ValidationException::withMessages([
                    'youtube_url' =>
                        'This YouTube video has already been added to Our Channel.',
                ]);
            }

            throw $exception;
        }

        return redirect()
            ->route('admin.our_channel.index')
            ->with(
                'success',
                'Our Channel video updated successfully.'
            );
    }

    public function publication(
        UpdateChannelVideoPublicationRequest $request,
        ChannelVideo $channelVideo,
        ChannelPublicationService $publicationService
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'our_channel.manage'
        );

        $action = $request->publicationAction();
        $reason = $request->auditReason();

        [
            $message,
            $queueExternal,
        ] = DB::transaction(
            function () use (
                $channelVideo,
                $action,
                $reason,
                $request,
                $admin
            ): array {
                $locked = ChannelVideo::query()
                    ->lockForUpdate()
                    ->findOrFail(
                        $channelVideo->id
                    );

                $before = $this->auditSnapshot(
                    $locked
                );

                if (
                    $action
                    === UpdateChannelVideoPublicationRequest::ACTION_PUBLISH
                ) {
                    abort_unless(
                        $locked->status
                            === ChannelVideo::STATUS_DRAFT,
                        422,
                        'Only a draft Our Channel video can be published.'
                    );

                    $locked->status =
                        ChannelVideo::STATUS_PUBLISHED;
                    $locked->published_at = now();

                    $auditAction =
                        'CHANNEL_VIDEO_PUBLISHED';

                    $message =
                        'Our Channel video published. Enabled external platforms were queued.';

                    $queueExternal = true;
                } else {
                    abort_unless(
                        $locked->status
                            === ChannelVideo::STATUS_PUBLISHED,
                        422,
                        'Only a published Our Channel video can be unpublished.'
                    );

                    $locked->status =
                        ChannelVideo::STATUS_DRAFT;
                    $locked->published_at = null;

                    $auditAction =
                        'CHANNEL_VIDEO_UNPUBLISHED';

                    $message =
                        'TradeX catalogue video unpublished. Existing external platform posts are not deleted.';

                    $queueExternal = false;
                }

                $locked->updated_by_admin_id =
                    $admin->id;
                $locked->save();

                [
                    $oldValues,
                    $newValues,
                ] = $this->auditChanges(
                    $before,
                    $this->auditSnapshot(
                        $locked->fresh()
                    )
                );

                if ($reason !== null) {
                    $newValues['reason'] = $reason;
                }

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->id,
                    action: $auditAction,
                    module: 'OUR_CHANNEL',
                    targetType: 'CHANNEL_VIDEO',
                    targetId: $locked->id,
                    oldValues: $oldValues,
                    newValues: $newValues,
                    ipAddress: $request->ip(),
                    userAgent: $request->userAgent()
                );

                return [
                    $message,
                    $queueExternal,
                ];
            }
        );

        if ($queueExternal) {
            $publicationService->queueForVideo(
                $channelVideo->fresh(
                    'publications'
                )
            );
        }

        return redirect()
            ->route('admin.our_channel.index')
            ->with(
                'success',
                $message
            );
    }

    public function retryPublication(
        Request $request,
        ChannelVideo $channelVideo,
        ChannelVideoPublication $publication,
        ChannelPublicationService $publicationService
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'our_channel.manage'
        );

        abort_unless(
            $publication->channel_video_id
                === $channelVideo->id,
            404
        );

        abort_if(
            $publication->isPublished(),
            422,
            'A published external post cannot be retried.'
        );

        $publicationService->retry(
            $publication
        );

        $publication->refresh();

        AuditLog::record(
            actorType: 'ADMIN',
            actorId: $admin->id,
            action: 'CHANNEL_VIDEO_PUBLICATION_RETRY_REQUESTED',
            module: 'OUR_CHANNEL',
            targetType: 'CHANNEL_VIDEO_PUBLICATION',
            targetId: $publication->id,
            newValues: [
                'channel_video_id' =>
                    $channelVideo->id,
                'platform' =>
                    $publication->platform,
                'status' =>
                    $publication->status,
            ],
            ipAddress: $request->ip(),
            userAgent: $request->userAgent()
        );

        return redirect()
            ->route('admin.our_channel.index')
            ->with(
                'success',
                $publication->status
                    === ChannelVideoPublication::STATUS_PENDING
                    ? $publication->platformLabel()
                        .' publishing retry queued.'
                    : $publication->platformLabel()
                        .' still requires setup or manual action.'
            );
    }

    public function destroy(
        Request $request,
        ChannelVideo $channelVideo
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'our_channel.manage'
        );

        $disk = trim(
            (string) $channelVideo->source_disk
        );
        $uuid = $channelVideo->uuid;

        DB::transaction(function () use (
            $request,
            $channelVideo,
            $admin
        ): void {
            $locked = ChannelVideo::query()
                ->lockForUpdate()
                ->findOrFail(
                    $channelVideo->id
                );

            abort_if(
                $locked->status
                    === ChannelVideo::STATUS_PUBLISHED,
                422,
                'Unpublish the Our Channel video before deleting it.'
            );

            abort_if(
                $locked->publications()
                    ->where(
                        'status',
                        ChannelVideoPublication::STATUS_PUBLISHED
                    )
                    ->exists(),
                422,
                'This video has published external posts. Keep the TradeX record for audit/history.'
            );

            $before = $this->auditSnapshot(
                $locked
            );

            $locked->updated_by_admin_id =
                $admin->id;
            $locked->save();
            $locked->delete();

            AuditLog::record(
                actorType: 'ADMIN',
                actorId: $admin->id,
                action: 'CHANNEL_VIDEO_DELETED',
                module: 'OUR_CHANNEL',
                targetType: 'CHANNEL_VIDEO',
                targetId: $locked->id,
                oldValues: $before,
                newValues: [
                    'deleted_at' =>
                        $locked->deleted_at?->toISOString(),
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
        });

        if (
            in_array(
                $disk,
                [
                    'private',
                    's3-private',
                ],
                true
            )
        ) {
            Storage::disk($disk)->deleteDirectory(
                'our-channel/'.$uuid
            );
        }

        return redirect()
            ->route('admin.our_channel.index')
            ->with(
                'success',
                'Our Channel video deleted successfully.'
            );
    }

    private function storeSourceUpload(
        StoreChannelVideoRequest $request,
        string $uuid
    ): ?array {
        $file = $request->file('video_file');

        if (! $file instanceof UploadedFile) {
            return null;
        }

        $disk = trim(
            (string) config(
                'tradex_channels.source_disk',
                'private'
            )
        );

        if (
            ! in_array(
                $disk,
                [
                    'private',
                    's3-private',
                ],
                true
            )
        ) {
            throw ValidationException::withMessages([
                'video_file' =>
                    'The configured TradeX channel media disk is not approved.',
            ]);
        }

        $mime = strtolower(
            trim(
                (string) $file->getMimeType()
            )
        );

        $extension = $mime === 'video/quicktime'
            ? 'mov'
            : 'mp4';

        $directory =
            'our-channel/'
            .$uuid
            .'/source';

        $filename =
            'master.'
            .$extension;

        $storedPath = Storage::disk($disk)
            ->putFileAs(
                $directory,
                $file,
                $filename,
                [
                    'visibility' => 'private',
                ]
            );

        if (
            ! is_string($storedPath)
            || trim($storedPath) === ''
        ) {
            throw ValidationException::withMessages([
                'video_file' =>
                    'TradeX could not store the protected master video.',
            ]);
        }

        $realPath = $file->getRealPath();

        $sha256 = is_string($realPath)
            && $realPath !== ''
            ? hash_file(
                'sha256',
                $realPath
            )
            : false;

        $originalName = basename(
            str_replace(
                '\\',
                '/',
                $file->getClientOriginalName()
            )
        );

        return [
            'disk' => $disk,
            'path' => $storedPath,
            'original_name' => mb_substr(
                $originalName,
                0,
                255
            ),
            'mime_type' => $mime,
            'size_bytes' => max(
                0,
                (int) $file->getSize()
            ),
            'sha256' => is_string($sha256)
                ? $sha256
                : null,
        ];
    }

    private function cleanupSource(
        ?array $source,
        string $uuid
    ): void {
        if (! is_array($source)) {
            return;
        }

        $disk = trim(
            (string) (
                $source['disk']
                ?? ''
            )
        );

        if (
            in_array(
                $disk,
                [
                    'private',
                    's3-private',
                ],
                true
            )
        ) {
            Storage::disk($disk)->deleteDirectory(
                'our-channel/'.$uuid
            );
        }
    }

    private function extractVideoId(
        ?string $youtubeUrl
    ): ?string {
        if (
            $youtubeUrl === null
            || trim($youtubeUrl) === ''
        ) {
            return null;
        }

        $videoId = YouTubeVideo::extractVideoId(
            $youtubeUrl
        );

        if ($videoId === null) {
            throw ValidationException::withMessages([
                'youtube_url' =>
                    'Enter a valid HTTPS YouTube video URL.',
            ]);
        }

        return $videoId;
    }

    private function assertVideoIdAvailable(
        string $videoId,
        ?int $ignoreId = null
    ): void {
        $query = ChannelVideo::withTrashed()
            ->where(
                'youtube_video_id',
                $videoId
            );

        if ($ignoreId !== null) {
            $query->whereKeyNot(
                $ignoreId
            );
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'youtube_url' =>
                    'This YouTube video has already been added to Our Channel.',
            ]);
        }
    }

    private function nullableText(
        mixed $value
    ): ?string {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed !== ''
            ? $trimmed
            : null;
    }

    private function platformStates(): array
    {
        return [
            ChannelVideoPublication::PLATFORM_YOUTUBE => [
                'mode' => 'AUTO',
                'enabled' => (bool) config(
                    'tradex_channels.platforms.youtube.enabled',
                    false
                ),
            ],
            ChannelVideoPublication::PLATFORM_INSTAGRAM => [
                'mode' => 'AUTO',
                'enabled' => (bool) config(
                    'tradex_channels.platforms.instagram.enabled',
                    false
                ),
            ],
            ChannelVideoPublication::PLATFORM_FACEBOOK => [
                'mode' => 'AUTO',
                'enabled' => (bool) config(
                    'tradex_channels.platforms.facebook.enabled',
                    false
                ),
            ],
            ChannelVideoPublication::PLATFORM_TELEGRAM => [
                'mode' => 'AUTO',
                'enabled' => (bool) config(
                    'tradex_channels.platforms.telegram.enabled',
                    false
                ),
            ],
            ChannelVideoPublication::PLATFORM_TIKTOK => [
                'mode' => 'CONFIRM',
                'enabled' => false,
            ],
            ChannelVideoPublication::PLATFORM_WHATSAPP_CHANNEL => [
                'mode' => 'MANUAL',
                'enabled' => false,
            ],
        ];
    }

    private function authenticatedAdmin(): AdminUser
    {
        $admin = auth('admin')->user();

        abort_unless(
            $admin instanceof AdminUser,
            401
        );

        return $admin;
    }

    private function requirePermission(
        AdminUser $admin,
        string $permission
    ): void {
        abort_unless(
            $admin->hasPermission(
                $permission
            ),
            403
        );
    }

    private function auditSnapshot(
        ChannelVideo $video
    ): array {
        return [
            'uuid' => $video->uuid,
            'title' => $video->title,
            'short_description' =>
                $video->short_description,
            'description_sha256' =>
                $video->description !== null
                    ? hash(
                        'sha256',
                        $video->description
                    )
                    : null,
            'description_length' =>
                $video->description !== null
                    ? mb_strlen(
                        $video->description
                    )
                    : 0,
            'hashtags_sha256' =>
                $video->hashtags !== null
                    ? hash(
                        'sha256',
                        $video->hashtags
                    )
                    : null,
            'youtube_video_id' =>
                $video->youtube_video_id,
            'youtube_url' =>
                $video->youtube_url,
            'has_source_media' =>
                $video->hasSourceMedia(),
            'source_mime_type' =>
                $video->source_mime_type,
            'source_size_bytes' =>
                $video->source_size_bytes,
            'source_sha256' =>
                $video->source_sha256,
            'language_code' =>
                $video->language_code,
            'status' =>
                $video->status,
            'published_at' =>
                $video->published_at?->toISOString(),
            'publish_requested_at' =>
                $video->publish_requested_at?->toISOString(),
            'is_featured' =>
                $video->is_featured,
            'sort_order' =>
                $video->sort_order,
            'created_by_admin_id' =>
                $video->created_by_admin_id,
            'updated_by_admin_id' =>
                $video->updated_by_admin_id,
        ];
    }

    private function auditChanges(
        array $before,
        array $after
    ): array {
        $oldValues = [];
        $newValues = [];

        foreach (
            array_unique(
                array_merge(
                    array_keys($before),
                    array_keys($after)
                )
            )
            as $key
        ) {
            $old = $before[$key] ?? null;
            $new = $after[$key] ?? null;

            if ($old === $new) {
                continue;
            }

            $oldValues[$key] = $old;
            $newValues[$key] = $new;
        }

        return [
            $oldValues,
            $newValues,
        ];
    }
}
