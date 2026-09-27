<?php

namespace App\Services\Notifications;

use App\Exceptions\AuthApiException;
use App\Models\NotificationMessage;
use App\Models\User;
use App\Models\UserNotificationReceipt;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class NotificationApiService
{
    public function index(
        User $user,
        array $filters
    ): array {
        $query = $this->availableQuery(
            $user
        );

        if (
            filled(
                $filters['status']
                ?? null
            )
        ) {
            if (
                $filters['status']
                === 'READ'
            ) {
                $query->whereNotNull(
                    'user_notification_receipts.read_at'
                );
            }

            if (
                $filters['status']
                === 'UNREAD'
            ) {
                $query->whereNull(
                    'user_notification_receipts.read_at'
                );
            }
        }

        if (
            filled(
                $filters['category']
                ?? null
            )
        ) {
            $query->where(
                'notification_messages.category',
                $filters['category']
            );
        }

        $paginator = $query
            ->orderByDesc(
                'notification_messages.published_at'
            )
            ->orderByDesc(
                'user_notification_receipts.id'
            )
            ->paginate(
                $this->perPage(
                    $filters
                )
            )
            ->withQueryString();

        return [
            'items' =>
                collect(
                    $paginator->items()
                )
                    ->map(
                        fn (
                            UserNotificationReceipt $receipt
                        ): array =>
                            $this->present(
                                $receipt
                            )
                    )
                    ->values()
                    ->all(),

            'pagination' =>
                $this->pagination(
                    $paginator
                ),

            'unread_count' =>
                $this->unreadCount(
                    $user
                ),

            'filters' => [
                'status' =>
                    $filters['status']
                    ?? null,

                'category' =>
                    $filters['category']
                    ?? null,
            ],
        ];
    }

    public function summary(
        User $user
    ): array {
        $ttlSeconds = max(
            0,
            (int) config(
                'tradex.performance.notification_summary_cache_seconds',
                5
            )
        );

        if ($ttlSeconds === 0) {
            return $this->buildSummary(
                $user
            );
        }

        return Cache::remember(
            $this->summaryCacheKey($user),
            now()->addSeconds($ttlSeconds),
            fn (): array =>
                $this->buildSummary(
                    $user
                )
        );
    }

    private function buildSummary(
        User $user
    ): array {
        $total =
            $this->availableQuery(
                $user
            )->count();

        $unread =
            $this->unreadCount(
                $user
            );

        return [
            'total_available' =>
                $total,

            'unread_count' =>
                $unread,

            'has_unread' =>
                $unread > 0,
        ];
    }

    public function show(
        User $user,
        string $receiptUuid
    ): array {
        return $this->present(
            $this->resolve(
                $user,
                $receiptUuid
            )
        );
    }

    public function markRead(
        User $user,
        string $receiptUuid
    ): array {
        $receipt = DB::transaction(
            function () use (
                $user,
                $receiptUuid
            ): UserNotificationReceipt {
                $resolved =
                    $this->resolve(
                        $user,
                        $receiptUuid
                    );

                $locked =
                    UserNotificationReceipt::query()
                        ->whereKey(
                            $resolved->getKey()
                        )
                        ->lockForUpdate()
                        ->firstOrFail();

                if ($locked->read_at === null) {
                    $locked->forceFill([
                        'read_at' =>
                            now(),
                    ])->save();
                }

                return $locked->fresh(
                    'notification'
                );
            },
            3
        );

        $this->forgetSummaryCache(
            $user
        );

        return [
            'notification' =>
                $this->present(
                    $receipt
                ),

            'unread_count' =>
                $this->unreadCount(
                    $user
                ),
        ];
    }

    public function markAllRead(
        User $user
    ): array {
        $ids = $this->availableQuery(
            $user
        )
            ->whereNull(
                'user_notification_receipts.read_at'
            )
            ->pluck(
                'user_notification_receipts.id'
            );

        $updated = 0;

        if ($ids->isNotEmpty()) {
            $updated =
                UserNotificationReceipt::query()
                    ->whereIn(
                        'id',
                        $ids
                    )
                    ->whereNull(
                        'read_at'
                    )
                    ->update([
                        'read_at' =>
                            now(),

                        'updated_at' =>
                            now(),
                    ]);
        }

        $this->forgetSummaryCache(
            $user
        );

        return [
            'marked_read_count' =>
                $updated,

            'unread_count' =>
                $this->unreadCount(
                    $user
                ),
        ];
    }

    private function summaryCacheKey(
        User $user
    ): string {
        return implode(':', [
            'tradex',
            'notifications',
            'summary',
            'v1',
            (string) $user->getKey(),
            (string) $user->account_type,
        ]);
    }

    private function forgetSummaryCache(
        User $user
    ): void {
        Cache::forget(
            $this->summaryCacheKey($user)
        );
    }

    private function resolve(
        User $user,
        string $receiptUuid
    ): UserNotificationReceipt {
        $receipt = $this->availableQuery(
            $user
        )
            ->where(
                'user_notification_receipts.uuid',
                $receiptUuid
            )
            ->first();

        if (
            ! $receipt
            instanceof UserNotificationReceipt
        ) {
            throw new AuthApiException(
                404,
                'NOTIFICATION_NOT_AVAILABLE',
                'This notification is not available.'
            );
        }

        return $receipt;
    }

    private function availableQuery(
        User $user
    ): Builder {
        $audiences = [
            NotificationMessage::AUDIENCE_ALL,
        ];

        if ($user->isFree()) {
            $audiences[] =
                NotificationMessage::AUDIENCE_FREE;
        }

        if ($user->canAccessPremium()) {
            $audiences[] =
                NotificationMessage::AUDIENCE_PREMIUM;
        }

        return UserNotificationReceipt::query()
            ->select(
                'user_notification_receipts.*'
            )
            ->join(
                'notification_messages',
                'notification_messages.id',
                '=',
                'user_notification_receipts.notification_message_id'
            )
            ->with(
                'notification'
            )
            ->where(
                'user_notification_receipts.user_id',
                $user->getKey()
            )
            ->where(
                'notification_messages.is_active',
                true
            )
            ->whereNotNull(
                'notification_messages.published_at'
            )
            ->where(
                'notification_messages.published_at',
                '<=',
                now()
            )
            ->whereIn(
                'notification_messages.audience_access_level',
                $audiences
            )
            ->where(
                function (
                    Builder $query
                ): void {
                    $query
                        ->whereNull(
                            'notification_messages.expires_at'
                        )
                        ->orWhere(
                            'notification_messages.expires_at',
                            '>',
                            now()
                        );
                }
            );
    }

    private function unreadCount(
        User $user
    ): int {
        return $this->availableQuery(
            $user
        )
            ->whereNull(
                'user_notification_receipts.read_at'
            )
            ->count();
    }

    private function present(
        UserNotificationReceipt $receipt
    ): array {
        $receipt->loadMissing(
            'notification'
        );

        $message =
            $receipt->notification;

        if (
            ! $message
            instanceof NotificationMessage
        ) {
            throw new AuthApiException(
                404,
                'NOTIFICATION_NOT_AVAILABLE',
                'This notification is not available.'
            );
        }

        return [
            'uuid' =>
                $receipt->uuid,

            'notification_uuid' =>
                $message->uuid,

            'category' =>
                $message->category,

            'priority' =>
                $message->priority,

            'title' =>
                $message->title,

            'body' =>
                $message->body,

            'action' => [
                'type' =>
                    $message->action_type,

                'resource_uuid' =>
                    $message
                        ->action_resource_uuid,

                /*
                | No arbitrary URL is stored or returned by API-10.
                | Mobile client maps action type + optional resource UUID
                | to an approved in-app screen.
                */
            ],

            'is_read' =>
                $receipt->isRead(),

            'read_at' =>
                $receipt->read_at
                    ?->toISOString(),

            'published_at' =>
                $message->published_at
                    ?->toISOString(),

            'expires_at' =>
                $message->expires_at
                    ?->toISOString(),
        ];
    }

    private function perPage(
        array $filters
    ): int {
        return max(
            1,
            min(
                (int) (
                    $filters['per_page']
                    ?? config(
                        'tradex_notifications_api.default_per_page',
                        20
                    )
                ),
                max(
                    1,
                    (int) config(
                        'tradex_notifications_api.max_per_page',
                        50
                    )
                )
            )
        );
    }

    private function pagination(
        LengthAwarePaginator $paginator
    ): array {
        return [
            'current_page' =>
                $paginator->currentPage(),

            'last_page' =>
                $paginator->lastPage(),

            'per_page' =>
                $paginator->perPage(),

            'total' =>
                $paginator->total(),
        ];
    }
}
