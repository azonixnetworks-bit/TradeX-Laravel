<?php

namespace App\Services\Audit;

use Illuminate\Support\Facades\DB;

class UserActivityService
{
    /**
     * Client events are analytics only, never proof of a payment,
     * trade, entitlement, authentication, or admin action.
     */
    public function ingest(int $userId, array $events): int
    {
        $now = now();
        $rows = array_map(static fn (array $event): array => [
            'user_id' => $userId,
            'event_id' => $event['event_id'],
            'module' => $event['module'],
            'event_name' => $event['event_name'],
            'session_id' => $event['session_id'] ?? null,
            'subject_id' => $event['subject_id'] ?? null,
            'occurred_at' => $event['occurred_at'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $events);

        // Unique (user_id,event_id) makes offline retries idempotent.
        return DB::table('user_activity_events')->insertOrIgnore($rows);
    }
}
