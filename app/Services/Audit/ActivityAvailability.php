<?php

namespace App\Services\Audit;

use Illuminate\Support\Facades\Schema;

class ActivityAvailability
{
    public function schemaReady(): bool
    {
        return Schema::hasTable('user_activity_events')
            && Schema::hasColumns('user_activity_events', [
                'user_id',
                'event_id',
                'module',
                'event_name',
                'session_id',
                'subject_id',
                'occurred_at',
            ]);
    }

    public function isReady(): bool
    {
        return (bool) config('tradex_activity.enabled', false)
            && $this->schemaReady();
    }
}
