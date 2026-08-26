<?php

namespace App\Services\Admin\Notifications;

use App\Models\AdminUser;
use App\Models\AuditLog;
use App\Models\NotificationMessage;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AdminNotificationService
{
    private const RESOURCE_ACTION_TABLES = [
        NotificationMessage::ACTION_EDUCATION_COURSE =>
            'courses',

        NotificationMessage::ACTION_EDUCATION_LESSON =>
            'lessons',

        NotificationMessage::ACTION_TRADE_IDEA =>
            'trade_ideas',

        NotificationMessage::ACTION_OUR_CHANNEL_VIDEO =>
            'channel_videos',
    ];

    public function index(
        array $filters
    ): array {
        $query =
            NotificationMessage::query()
                ->with([
                    'createdByAdmin:id,name,email',
                ])
                ->withCount([
                    'receipts',

                    'receipts as read_receipts_count' =>
                        fn ($receiptQuery) =>
                            $receiptQuery
                                ->whereNotNull(
                                    'read_at'
                                ),
                ]);

        $search = trim(
            (string) (
                $filters['search']
                ?? ''
            )
        );

        if ($search !== '') {
            $query->where(
                function (
                    Builder $builder
                ) use ($search): void {
                    $builder
                        ->where(
                            'title',
                            'like',
                            '%'.$search.'%'
                        )
                        ->orWhere(
                            'uuid',
                            'like',
                            '%'.$search.'%'
                        );
                }
            );
        }

        $this->applyExactFilter(
            $query,
            'audience_access_level',
            $filters['audience']
                ?? null,
            [
                NotificationMessage::AUDIENCE_ALL,
                NotificationMessage::AUDIENCE_FREE,
                NotificationMessage::AUDIENCE_PREMIUM,
            ]
        );

        $this->applyExactFilter(
            $query,
            'category',
            $filters['category']
                ?? null,
            NotificationMessage::CATEGORIES
        );

        $this->applyExactFilter(
            $query,
            'priority',
            $filters['priority']
                ?? null,
            NotificationMessage::PRIORITIES
        );

        $status =
            strtoupper(
                trim(
                    (string) (
                        $filters['status']
                        ?? ''
                    )
                )
            );

        $this->applyStatus(
            $query,
            $status
        );

        $messages =
            $query
                ->orderByDesc('id')
                ->paginate(
                    $this->perPage(
                        $filters
                    )
                )
                ->withQueryString();

        return [
            'messages' =>
                $messages,

            'stats' =>
                $this->stats(),

            'statuses' => [
                'DRAFT',
                'SCHEDULED',
                'LIVE',
                'EXPIRED',
                'INACTIVE',
            ],

            'audiences' => [
                NotificationMessage::AUDIENCE_ALL,
                NotificationMessage::AUDIENCE_FREE,
                NotificationMessage::AUDIENCE_PREMIUM,
            ],

            'categories' =>
                NotificationMessage::CATEGORIES,

            'priorities' =>
                NotificationMessage::PRIORITIES,
        ];
    }

    public function show(
        NotificationMessage $message
    ): array {
        $message->loadMissing([
            'createdByAdmin:id,name,email',
        ]);

        $recipientCount =
            $message->receipts()
                ->count();

        $readCount =
            $message->receipts()
                ->whereNotNull(
                    'read_at'
                )
                ->count();

        return [
            'notificationMessage' =>
                $message,

            'status' =>
                $this->status(
                    $message
                ),

            'recipientCount' =>
                $recipientCount,

            'readCount' =>
                $readCount,

            'unreadCount' =>
                max(
                    0,
                    $recipientCount
                    - $readCount
                ),

            'isDraft' =>
                $message->published_at
                === null,

            'maxRecipients' =>
                $this->maxRecipients(),
        ];
    }

    public function createDraft(
        AdminUser $admin,
        array $data,
        Request $request
    ): NotificationMessage {
        $this->assertActionContract(
            $data['action_type'],
            $data['action_resource_uuid']
                ?? null
        );

        $message =
            DB::transaction(
                function () use (
                    $admin,
                    $data,
                    $request
                ): NotificationMessage {
                    $message =
                        NotificationMessage::query()
                            ->create([
                                'audience_access_level' =>
                                    $data[
                                        'audience_access_level'
                                    ],

                                'category' =>
                                    $data['category'],

                                'priority' =>
                                    $data['priority'],

                                'title' =>
                                    $data['title'],

                                'body' =>
                                    $data['body'],

                                'action_type' =>
                                    $data[
                                        'action_type'
                                    ],

                                'action_resource_uuid' =>
                                    $data[
                                        'action_resource_uuid'
                                    ]
                                    ?? null,

                                'is_active' =>
                                    true,

                                'published_at' =>
                                    null,

                                'expires_at' =>
                                    $data[
                                        'expires_at'
                                    ]
                                    ?? null,

                                'created_by_admin_id' =>
                                    $admin->getKey(),
                            ]);

                    $this->audit(
                        admin:
                            $admin,
                        request:
                            $request,
                        action:
                            'ADMIN_NOTIFICATION_DRAFT_CREATED',
                        message:
                            $message,
                        extra: [
                            'state' =>
                                'DRAFT',
                        ]
                    );

                    return $message;
                },
                3
            );

        return $message;
    }

    public function updateDraft(
        AdminUser $admin,
        NotificationMessage $message,
        array $data,
        Request $request
    ): void {
        $this->assertActionContract(
            $data['action_type'],
            $data['action_resource_uuid']
                ?? null
        );

        DB::transaction(
            function () use (
                $admin,
                $message,
                $data,
                $request
            ): void {
                $locked =
                    NotificationMessage::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $message->getKey()
                        );

                $this->assertDraftMutable(
                    $locked
                );

                $oldMetadata =
                    $this->safeMetadata(
                        $locked
                    );

                $locked->forceFill([
                    'audience_access_level' =>
                        $data[
                            'audience_access_level'
                        ],

                    'category' =>
                        $data['category'],

                    'priority' =>
                        $data['priority'],

                    'title' =>
                        $data['title'],

                    'body' =>
                        $data['body'],

                    'action_type' =>
                        $data[
                            'action_type'
                        ],

                    'action_resource_uuid' =>
                        $data[
                            'action_resource_uuid'
                        ]
                        ?? null,

                    'expires_at' =>
                        $data[
                            'expires_at'
                        ]
                        ?? null,
                ])->save();

                AuditLog::record(
                    actorType:
                        'ADMIN',
                    actorId:
                        $admin->getKey(),
                    action:
                        'ADMIN_NOTIFICATION_DRAFT_UPDATED',
                    module:
                        'NOTIFICATIONS',
                    targetType:
                        'NOTIFICATION_MESSAGE',
                    targetId:
                        $locked->getKey(),
                    oldValues:
                        $oldMetadata,
                    newValues:
                        $this->safeMetadata(
                            $locked
                        ),
                    ipAddress:
                        $request->ip(),
                    userAgent:
                        $request->userAgent()
                );
            },
            3
        );
    }

    public function dispatch(
        AdminUser $admin,
        NotificationMessage $message,
        array $data,
        Request $request
    ): array {
        return DB::transaction(
            function () use (
                $admin,
                $message,
                $data,
                $request
            ): array {
                $locked =
                    NotificationMessage::query()
                        ->lockForUpdate()
                        ->findOrFail(
                            $message->getKey()
                        );

                $this->assertDraftMutable(
                    $locked
                );

                $this->assertActionContract(
                    $locked->action_type,
                    $locked
                        ->action_resource_uuid
                );

                $publishAt =
                    $this->resolvePublishAt(
                        $data[
                            'publish_at'
                        ]
                        ?? null
                    );

                $this->assertExpiryAfterPublish(
                    $locked,
                    $publishAt
                );

                $recipientQuery =
                    $this->recipientQuery(
                        $locked
                    );

                $recipientCount =
                    (clone $recipientQuery)
                        ->count();

                if ($recipientCount < 1) {
                    abort(
                        409,
                        'No currently active users match this broadcast audience.'
                    );
                }

                if (
                    $recipientCount
                    > $this->maxRecipients()
                ) {
                    abort(
                        409,
                        'This broadcast exceeds the configured synchronous recipient safety limit.'
                    );
                }

                $now = now();
                $inserted = 0;
                $chunkSize =
                    $this->insertChunkSize();

                $recipientQuery
                    ->select('id')
                    ->orderBy('id')
                    ->chunkById(
                        $chunkSize,
                        function (
                            $users
                        ) use (
                            $locked,
                            $now,
                            &$inserted
                        ): void {
                            $rows = [];

                            foreach (
                                $users
                                as $user
                            ) {
                                $rows[] = [
                                    'uuid' =>
                                        (string) Str::uuid(),

                                    'notification_message_id' =>
                                        $locked->getKey(),

                                    'user_id' =>
                                        $user->getKey(),

                                    'read_at' =>
                                        null,

                                    'created_at' =>
                                        $now,

                                    'updated_at' =>
                                        $now,
                                ];
                            }

                            if ($rows !== []) {
                                DB::table(
                                    'user_notification_receipts'
                                )->insert(
                                    $rows
                                );

                                $inserted +=
                                    count(
                                        $rows
                                    );
                            }
                        },
                        'id'
                    );

                if (
                    $inserted
                    !== $recipientCount
                ) {
                    abort(
                        409,
                        'Recipient snapshot changed during broadcast preparation. No broadcast was published.'
                    );
                }

                $locked->forceFill([
                    'published_at' =>
                        $publishAt,

                    'is_active' =>
                        true,
                ])->save();

                $scheduled =
                    $publishAt->isFuture();

                $this->audit(
                    admin:
                        $admin,
                    request:
                        $request,
                    action:
                        $scheduled
                            ? 'ADMIN_NOTIFICATION_BROADCAST_SCHEDULED'
                            : 'ADMIN_NOTIFICATION_BROADCAST_SENT',
                    message:
                        $locked,
                    extra: [
                        'state' =>
                            $scheduled
                                ? 'SCHEDULED'
                                : 'LIVE',

                        'recipient_count' =>
                            $recipientCount,

                        'published_at' =>
                            $locked->published_at
                                ?->toISOString(),

                        'expires_at' =>
                            $locked->expires_at
                                ?->toISOString(),
                    ]
                );

                return [
                    'scheduled' =>
                        $scheduled,

                    'recipient_count' =>
                        $recipientCount,

                    'published_at' =>
                        $locked
                            ->published_at,
                ];
            },
            3
        );
    }

    public function assertDraftMutable(
        NotificationMessage $message
    ): void {
        if (
            $message->published_at
            !== null
        ) {
            abort(
                409,
                'Published or scheduled notifications are immutable.'
            );
        }

        if (
            $message->receipts()
                ->exists()
        ) {
            abort(
                409,
                'A notification with recipient receipts cannot be edited or dispatched again.'
            );
        }
    }

    public function status(
        NotificationMessage $message
    ): string {
        if (
            $message->published_at
            === null
        ) {
            return 'DRAFT';
        }

        if (
            ! $message->is_active
        ) {
            return 'INACTIVE';
        }

        if (
            $message->expires_at
            && $message->expires_at
                ->isPast()
        ) {
            return 'EXPIRED';
        }

        if (
            $message->published_at
                ->isFuture()
        ) {
            return 'SCHEDULED';
        }

        return 'LIVE';
    }

    private function recipientQuery(
        NotificationMessage $message
    ): Builder {
        $query =
            User::query()
                ->where(
                    'status',
                    'ACTIVE'
                );

        if (
            $message->audience_access_level
            === NotificationMessage::AUDIENCE_FREE
        ) {
            $query->where(
                'account_type',
                'FREE'
            );
        }

        if (
            $message->audience_access_level
            === NotificationMessage::AUDIENCE_PREMIUM
        ) {
            $query->where(
                'account_type',
                'PREMIUM'
            );
        }

        return $query;
    }

    private function resolvePublishAt(
        mixed $value
    ): CarbonImmutable {
        $now =
            CarbonImmutable::now();

        if (
            $value === null
            || $value === ''
        ) {
            return $now;
        }

        $requested =
            CarbonImmutable::parse(
                (string) $value
            );

        if (
            $requested->lessThan(
                $now->subMinute()
            )
        ) {
            abort(
                422,
                'Scheduled publication time cannot be in the past.'
            );
        }

        if (
            $requested->greaterThan(
                $now->addDays(
                    $this->maxScheduleDays()
                )
            )
        ) {
            abort(
                422,
                'Scheduled publication time exceeds the configured scheduling horizon.'
            );
        }

        return $requested->lessThan(
            $now
        )
            ? $now
            : $requested;
    }

    private function assertExpiryAfterPublish(
        NotificationMessage $message,
        CarbonImmutable $publishAt
    ): void {
        if (
            $message->expires_at
            && $message->expires_at
                ->lessThanOrEqualTo(
                    $publishAt
                )
        ) {
            abort(
                422,
                'Notification expiry must be later than the publication time.'
            );
        }
    }

    private function assertActionContract(
        string $actionType,
        ?string $resourceUuid
    ): void {
        $table =
            self::RESOURCE_ACTION_TABLES[
                $actionType
            ]
            ?? null;

        if ($table === null) {
            if ($resourceUuid !== null) {
                abort(
                    422,
                    'This notification action does not accept a resource UUID.'
                );
            }

            return;
        }

        if ($resourceUuid === null) {
            abort(
                422,
                'This notification action requires a resource UUID.'
            );
        }

        $exists =
            DB::table(
                $table
            )
                ->where(
                    'uuid',
                    $resourceUuid
                )
                ->exists();

        if (! $exists) {
            abort(
                422,
                'The selected notification action resource does not exist.'
            );
        }
    }

    private function applyStatus(
        Builder $query,
        string $status
    ): void {
        if ($status === '') {
            return;
        }

        $now = now();

        match ($status) {
            'DRAFT' =>
                $query->whereNull(
                    'published_at'
                ),

            'SCHEDULED' =>
                $query
                    ->where(
                        'is_active',
                        true
                    )
                    ->where(
                        'published_at',
                        '>',
                        $now
                    ),

            'LIVE' =>
                $query
                    ->where(
                        'is_active',
                        true
                    )
                    ->whereNotNull(
                        'published_at'
                    )
                    ->where(
                        'published_at',
                        '<=',
                        $now
                    )
                    ->where(
                        function (
                            Builder $builder
                        ) use ($now): void {
                            $builder
                                ->whereNull(
                                    'expires_at'
                                )
                                ->orWhere(
                                    'expires_at',
                                    '>',
                                    $now
                                );
                        }
                    ),

            'EXPIRED' =>
                $query
                    ->whereNotNull(
                        'published_at'
                    )
                    ->whereNotNull(
                        'expires_at'
                    )
                    ->where(
                        'expires_at',
                        '<=',
                        $now
                    ),

            'INACTIVE' =>
                $query->where(
                    'is_active',
                    false
                ),

            default =>
                null,
        };
    }

    private function applyExactFilter(
        Builder $query,
        string $column,
        mixed $value,
        array $allowed
    ): void {
        $normalized =
            strtoupper(
                trim(
                    (string) $value
                )
            );

        if (
            in_array(
                $normalized,
                $allowed,
                true
            )
        ) {
            $query->where(
                $column,
                $normalized
            );
        }
    }

    private function stats(): array
    {
        $now = now();

        return [
            'total' =>
                NotificationMessage::query()
                    ->count(),

            'drafts' =>
                NotificationMessage::query()
                    ->whereNull(
                        'published_at'
                    )
                    ->count(),

            'scheduled' =>
                NotificationMessage::query()
                    ->where(
                        'is_active',
                        true
                    )
                    ->where(
                        'published_at',
                        '>',
                        $now
                    )
                    ->count(),

            'live' =>
                NotificationMessage::query()
                    ->where(
                        'is_active',
                        true
                    )
                    ->whereNotNull(
                        'published_at'
                    )
                    ->where(
                        'published_at',
                        '<=',
                        $now
                    )
                    ->where(
                        function (
                            Builder $builder
                        ) use ($now): void {
                            $builder
                                ->whereNull(
                                    'expires_at'
                                )
                                ->orWhere(
                                    'expires_at',
                                    '>',
                                    $now
                                );
                        }
                    )
                    ->count(),

            'expired' =>
                NotificationMessage::query()
                    ->whereNotNull(
                        'published_at'
                    )
                    ->whereNotNull(
                        'expires_at'
                    )
                    ->where(
                        'expires_at',
                        '<=',
                        $now
                    )
                    ->count(),
        ];
    }

    private function safeMetadata(
        NotificationMessage $message
    ): array {
        return [
            'notification_uuid' =>
                $message->uuid,

            'audience_access_level' =>
                $message
                    ->audience_access_level,

            'category' =>
                $message->category,

            'priority' =>
                $message->priority,

            'action_type' =>
                $message->action_type,

            'has_action_resource' =>
                $message
                    ->action_resource_uuid
                !== null,

            'has_expiry' =>
                $message->expires_at
                !== null,
        ];
    }

    private function audit(
        AdminUser $admin,
        Request $request,
        string $action,
        NotificationMessage $message,
        array $extra = []
    ): void {
        /*
         * Deliberately excludes title, body, user IDs and action resource UUID.
         */
        AuditLog::record(
            actorType:
                'ADMIN',
            actorId:
                $admin->getKey(),
            action:
                $action,
            module:
                'NOTIFICATIONS',
            targetType:
                'NOTIFICATION_MESSAGE',
            targetId:
                $message->getKey(),
            newValues:
                array_merge(
                    $this->safeMetadata(
                        $message
                    ),
                    $extra
                ),
            ipAddress:
                $request->ip(),
            userAgent:
                $request->userAgent()
        );
    }

    private function perPage(
        array $filters
    ): int {
        $default =
            max(
                1,
                (int) config(
                    'tradex_admin_notifications.default_per_page',
                    20
                )
            );

        $max =
            max(
                $default,
                (int) config(
                    'tradex_admin_notifications.max_per_page',
                    100
                )
            );

        $requested =
            (int) (
                $filters['per_page']
                ?? $default
            );

        return max(
            1,
            min(
                $requested,
                $max
            )
        );
    }

    private function insertChunkSize(): int
    {
        return max(
            100,
            (int) config(
                'tradex_admin_notifications.insert_chunk_size',
                1000
            )
        );
    }

    private function maxRecipients(): int
    {
        return max(
            1,
            (int) config(
                'tradex_admin_notifications.max_recipients_per_broadcast',
                50000
            )
        );
    }

    private function maxScheduleDays(): int
    {
        return max(
            1,
            (int) config(
                'tradex_admin_notifications.max_schedule_days',
                365
            )
        );
    }
}
