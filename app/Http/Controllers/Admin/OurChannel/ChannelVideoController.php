<?php

namespace App\Http\Controllers\Admin\OurChannel;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OurChannel\StoreChannelVideoRequest;
use App\Http\Requests\Admin\OurChannel\UpdateChannelVideoPublicationRequest;
use App\Http\Requests\Admin\OurChannel\UpdateChannelVideoRequest;
use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\ChannelVideo;
use App\Support\YouTubeVideo;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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
            ->ordered()
            ->get();

        $stats = [
            'total' => $videos->count(),
            'published' => $videos
                ->where(
                    'status',
                    ChannelVideo::STATUS_PUBLISHED
                )
                ->count(),
            'draft' => $videos
                ->where(
                    'status',
                    ChannelVideo::STATUS_DRAFT
                )
                ->count(),
            'featured' => $videos
                ->where(
                    'is_featured',
                    true
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
            'admin.our_channel.create'
        );
    }

    public function store(
        StoreChannelVideoRequest $request
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'our_channel.manage'
        );

        $validated = $request->validated();

        $videoId = $this->extractVideoIdOrFail(
            $validated['youtube_url']
        );

        $this->assertVideoIdAvailable(
            $videoId
        );

        try {
            DB::transaction(function () use (
                $validated,
                $videoId,
                $request,
                $admin
            ): void {
                $video = ChannelVideo::query()->create([
                    'title' => $validated['title'],
                    'short_description' => $validated['short_description'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'youtube_video_id' => $videoId,
                    'youtube_url' => YouTubeVideo::canonicalUrl(
                        $videoId
                    ),
                    'language_code' => $validated['language_code'],
                    'status' => ChannelVideo::STATUS_DRAFT,
                    'published_at' => null,
                    'is_featured' => (bool) ($validated['is_featured'] ?? false),
                    'sort_order' => (int) ($validated['sort_order'] ?? 0),
                    'created_by_admin_id' => $admin->id,
                    'updated_by_admin_id' => $admin->id,
                ]);

                AuditLog::record(
                    actorType: 'ADMIN',
                    actorId: $admin->id,
                    action: 'CHANNEL_VIDEO_CREATED',
                    module: 'OUR_CHANNEL',
                    targetType: 'CHANNEL_VIDEO',
                    targetId: $video->id,
                    newValues: $this->auditSnapshot(
                        $video
                    ),
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
                    'youtube_url' => 'This YouTube video has already been added to Our Channel.',
                ]);
            }

            throw $exception;
        }

        return redirect()
            ->route(
                'admin.our_channel.index'
            )
            ->with(
                'success',
                'Our Channel video created successfully as draft.'
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
            compact(
                'channelVideo'
            )
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

        $videoId = $this->extractVideoIdOrFail(
            $validated['youtube_url']
        );

        $this->assertVideoIdAvailable(
            $videoId,
            $channelVideo->id
        );

        try {
            DB::transaction(function () use (
                $validated,
                $videoId,
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
                    'short_description' => $validated['short_description'] ?? null,
                    'description' => $validated['description'] ?? null,
                    'youtube_video_id' => $videoId,
                    'youtube_url' => YouTubeVideo::canonicalUrl(
                        $videoId
                    ),
                    'language_code' => $validated['language_code'],
                    'is_featured' => (bool) ($validated['is_featured'] ?? false),
                    'sort_order' => (int) ($validated['sort_order'] ?? 0),
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
                    'youtube_url' => 'This YouTube video has already been added to Our Channel.',
                ]);
            }

            throw $exception;
        }

        return redirect()
            ->route(
                'admin.our_channel.index'
            )
            ->with(
                'success',
                'Our Channel video updated successfully.'
            );
    }

    public function publication(
        UpdateChannelVideoPublicationRequest $request,
        ChannelVideo $channelVideo
    ): RedirectResponse {
        $admin = $this->authenticatedAdmin();

        $this->requirePermission(
            $admin,
            'our_channel.manage'
        );

        $action = $request->publicationAction();
        $reason = $request->auditReason();

        $message = DB::transaction(function () use (
            $channelVideo,
            $action,
            $reason,
            $request,
            $admin
        ): string {
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

                $locked->status = ChannelVideo::STATUS_PUBLISHED;
                $locked->published_at = now();

                $auditAction = 'CHANNEL_VIDEO_PUBLISHED';
                $message = 'Our Channel video published successfully.';
            } else {
                abort_unless(
                    $locked->status
                        === ChannelVideo::STATUS_PUBLISHED,
                    422,
                    'Only a published Our Channel video can be unpublished.'
                );

                $locked->status = ChannelVideo::STATUS_DRAFT;
                $locked->published_at = null;

                $auditAction = 'CHANNEL_VIDEO_UNPUBLISHED';
                $message = 'Our Channel video unpublished successfully.';
            }

            $locked->updated_by_admin_id = $admin->id;
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

            return $message;
        });

        return redirect()
            ->route(
                'admin.our_channel.index'
            )
            ->with(
                'success',
                $message
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

            $before = $this->auditSnapshot(
                $locked
            );

            $locked->updated_by_admin_id = $admin->id;
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
                    'deleted_at' => $locked->deleted_at?->toISOString(),
                ],
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );
        });

        return redirect()
            ->route(
                'admin.our_channel.index'
            )
            ->with(
                'success',
                'Our Channel video deleted successfully.'
            );
    }

    private function extractVideoIdOrFail(
        string $youtubeUrl
    ): string {
        $videoId = YouTubeVideo::extractVideoId(
            $youtubeUrl
        );

        if ($videoId === null) {
            throw ValidationException::withMessages([
                'youtube_url' => 'Enter a valid HTTPS YouTube video URL.',
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
                'youtube_url' => 'This YouTube video has already been added to Our Channel.',
            ]);
        }
    }

    private function authenticatedAdmin(): AdminUser
    {
        $admin = auth(
            'admin'
        )->user();

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
            'short_description' => $video->short_description,
            'description_sha256' => $video->description !== null
                ? hash(
                    'sha256',
                    $video->description
                )
                : null,
            'description_length' => $video->description !== null
                ? mb_strlen(
                    $video->description
                )
                : 0,
            'youtube_video_id' => $video->youtube_video_id,
            'youtube_url' => $video->youtube_url,
            'language_code' => $video->language_code,
            'status' => $video->status,
            'published_at' => $video->published_at?->toISOString(),
            'is_featured' => $video->is_featured,
            'sort_order' => $video->sort_order,
            'created_by_admin_id' => $video->created_by_admin_id,
            'updated_by_admin_id' => $video->updated_by_admin_id,
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
